<?php

namespace Drupal\behance_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

/**
 * @file
 * Contains \Drupal\behance_block\Plugin\Block\BehanceBlock.
 */

/**
 * Provides a 'Behance Block' Block.
 *
 * @Block(
 *   id = "behance_block",
 *   admin_label = @Translation("Behance Block"),
 *   category = @Translation("Integrations"),
 * )
 */
class BehanceBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config_factory.
   *
   * @var \Drupal\Core\Config\config_factoryInterface
   */
  protected $configFactory;

  private $apiKey;
  private $userId;
  private $newTab;
  private $behanceFieldsDate;
  private $behanceProjectsDate;

  /**
   * Class Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $configFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->config_factory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration, $plugin_id, $plugin_definition, $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $this->api_key = $this->config_factory->get('behance_block.settings')->get('api_key');
    $this->user_id = $this->config_factory->get('behance_block.settings')->get('user_id');
    $this->new_tab = $this->config_factory->get('behance_block.settings')->get('new_tab');
    $this->behance_fields_date = $this->config_factory->get('behance_block.settings')->get('behance_fields_date');
    $this->behance_projects_date = $this->config_factory->get('behance_block.settings')->get('behance_projects_date');

    $output = array();

    $is_api_key_set = (isset($this->api_key) && !empty($this->api_key));
    $is_user_id_set = (isset($this->user_id) && !empty($this->user_id));

    // API key and User ID are set - show Behance projects.
    if ($is_api_key_set && $is_user_id_set) {

      $output[] = array(
        '#theme' => 'behance_block',
        '#projects' => $this->content(),
        '#tags' => $this->tags(),
        '#new_tab' => $this->getNewTab(),
        '#cache' => array('max-age' => 0),
        '#attached' => array('library' => array('behance_block/behance_block')),
      );

    }
    // Show error if required values are missing.
    else {

      $output[] = array(
        '#markup' => 'You must set an API key and username in the module settings. <a href="/admin/config/services/behance">Click here</a> to go the module settings.',
        '#cache' => array('max-age' => 0),
        '#attached' => array('library' => array('behance_block/behance_block')),
      );

    }

    return $output;

  }

  /**
   * Returns array with all projects.
   */
  private function content() {

    $projects_json = '';

    // Get projects from JSON cache file.
    $behance_projects_file_exists = file_exists('public://behance_projects.json');
    $behance_projects_file_date = $this->behance_projects_date;

    if (!$behance_projects_file_exists || $behance_projects_file_date != date('d.m.Y')) {
      $this->downloadProjectsJson();
    }

    if (file_exists('public://behance_projects.json')) {

      $json = file_get_contents('public://behance_projects.json');
      $projects_json = json_decode($json, TRUE);

    }

    return $projects_json;

  }

  /**
   * Open links in new tab or not.
   */
  private function getNewTab() {

    if ($this->newTab == 0) {
      return 'target=_self';
    }
    else {
      return 'target=_blank';
    }

  }

  /**
   * Returns all Behance tags in array.
   */
  private function tags() {

    $tags_json_array = array();

    // Get Behance field names from JSON cache file.
    $behance_fields_file_exists = file_exists('public://behance_fields.json');
    $behance_fields_file_date = $this->behance_fields_date;

    if (!$behance_fields_file_exists || $behance_fields_file_date != date('d.m.Y')) {
      $this->downloadFieldsJson();
    }

    if (file_exists('public://behance_fields.json')) {

      $tags = file_get_contents('public://behance_fields.json');
      $tags_json = json_decode($tags, TRUE);

      foreach ($tags_json['fields'] as $value) {
        $tags_json_array[$value['name']] = $value['id'];
      }

    }

    return $tags_json_array;

  }

  /**
   * Get Behance field names (tags) and store them in JSON file.
   */
  private function downloadFieldsJson() {

    // Get response from endpoint and save it.
    $client = new Client();

    try {
      $response = $client->get('http://api.behance.net/v2/fields?api_key=' . $this->api_key);
      $response_code = $response->getStatusCode();
      $behance_fields_json = $response->getBody();
    }
    catch (ClientException $e) {
      $response = $e->getResponse();
      $response_code = $response->getStatusCode();
      watchdog_exception('behance_block', $e);
    }

    if ($response_code == 200) {

      file_put_contents('public://behance_fields.json', $behance_fields_json);

      // Save date when the file is downloaded.
      $this->config_factory->getEditable('behance_block.settings')->set('behance_fields_date', date('d.m.Y'))->save();

    }

  }

  /**
   * Get Behance projects and store them in JSON file.
   */
  private function downloadProjectsJson() {

    // Get response from endpoint and save it.
    $projects_json = $this->getProjectsJson();

    if ($projects_json) {

      file_put_contents('public://behance_projects.json', $projects_json);

      // Save date when the file is downloaded.
      $this->config_factory->getEditable('behance_block.settings')->set('behance_projects_date', date('d.m.Y'))->save();

    }

  }

  /**
   * Get Behance projects from API endpoint.
   */
  private function getProjectsJson() {

    $i = 1;
    $loop_through = TRUE;
    $projects_json_full = array();

    $client = new Client();

    // Loop while you get not empty JSON response.
    while ($loop_through) {

      try {
        $response = $client->get('http://api.behance.net/v2/users/' . $this->user_id . '/projects?api_key=' . $this->api_key . '&per_page=24&page=' . $i);
        $response_code = $response->getStatusCode();
        $projects_json_page = $response->getBody();
        $projects_json = json_decode($projects_json_page, TRUE);
      }
      catch (ClientException $e) {
        $response = $e->getResponse();
        $response_code = $response->getStatusCode();
        watchdog_exception('behance_block', $e);
      }

      if ($response_code == 200) {
        if ($projects_json['projects']) {
          foreach ($projects_json['projects'] as $projects) {
            $projects_json_full[] = $projects;
          }
          $i++;
        }
        else {
          $loop_through = FALSE;
        }
      }
      else {
        $loop_through = FALSE;
      }

    }

    if ($projects_json_full) {
      return json_encode($projects_json_full);
    }
    else {
      return FALSE;
    }

  }

}
