<?php

namespace Drupal\behance_api\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * @file
 * Contains \Drupal\behance_api\Plugin\Block\BehanceBlock.
 */

/**
 * Provides a 'Behance API' Block.
 *
 * @Block(
 *   id = "behance_api",
 *   admin_label = @Translation("Behance API"),
 *   category = @Translation("Custom"),
 * )
 */
class BehanceBlock extends BlockBase {

  private $apiKey;
  private $userId;
  private $newTab;
  private $behanceFieldsDate;
  private $behanceProjectsDate;

   /**
   * {@inheritdoc}
   */
  public function build() {

    $config = $this->config('behance_api.settings');

    $this->api_key = $config->get('behance_api.api_key');
    $this->user_id = $config->get('behance_api.user_id');
    $this->newTab = $config->get('behance_api.newTab');
    $this->behance_fields_date = $config->get('behance_api.behance_fields_date');
    $this->behance_projects_date = $config->get('behance_api.behance_projects_date');

    $output = array();

    $is_api_key_set = (isset($this->api_key) && !empty($this->api_key));
    $is_user_id_set = (isset($this->user_id) && !empty($this->user_id));

    // API key and User ID are set - show Behance projects.
    if ($is_api_key_set && $is_user_id_set) {

      $output[] = array(
        '#theme' => 'behance_api',
        '#projects' => $this->content(),
        '#tags' => $this->tags(),
        '#newTab' => $this->newTab(),
        '#cache' => array('max-age' => 0),
        '#attached' => array('library' => array('behance_api/behance_api'),)
      );

    }
    // Show error if required values are missing.
    else {

      $output[] = array(
        '#markup' => 'You must set an API key and username in the module settings. <a href="/admin/config/services/behance">Click here</a> to go the module settings.',
        '#cache' => array('max-age' => 0),
        '#attached' => array('library' => array('behance_api/behance_api')),
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
  private function newTab() {

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
    $behance_fields_json = $this->fileGetContentsCurl('https://api.behance.net/v2/fields?api_key=' . $this->api_key);
    $behance_fields_array = json_decode($behance_fields_json, TRUE);

    if ($behance_fields_array['http_code'] == 200) {

      file_put_contents('public://behance_fields.json', $behance_fields_json);

      // Save date when the file is downloaded.
      $config = $this->config('behance_api.settings');
      $config->set('behance_fields_date', date('d.m.Y'));
      $config->save();

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
      $config = $this->config('behance_api.settings');
      $config->set('behance_projects_date', date('d.m.Y'));
      $config->save();

    }

  }

  /**
   * Get Behance projects from API endpoint.
   */
  private function getProjectsJson() {

    $i = 1;
    $loop_through = TRUE;
    $projects_json_full = array();

    // Loop while you get not empty JSON response.
    while ($loop_through) {

      $projects_json_page = $this->fileGetContentsCurl('https://api.behance.net/v2/users/' . $this->user_id . '/projects?api_key=' . $this->api_key . '&per_page=24&page=' . $i);

      $projects_json = json_decode($projects_json_page, TRUE);
      $http_code = $projects_json['http_code'];

      if ($http_code == 200) {
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

  /**
   * File get contents with curl.
   */
  private function fileGetContentsCurl($url) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    $data = curl_exec($ch);
    curl_close($ch);

    return $data;

  }

}
