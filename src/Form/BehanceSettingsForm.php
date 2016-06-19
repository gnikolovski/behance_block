<?php

namespace Drupal\behance_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * @file
 * Contains \Drupal\behance_api\Form\BehanceSettingsForm.
 */

/**
 * Defines a form that configures behance api settings.
 */
class BehanceSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'behance_api_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return array('behance_api.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('behance_api.settings');

    // API key field.
    $form['api_key'] = array(
      '#type' => 'textfield',
      '#title' => t('API Key'),
      '#required' => TRUE,
      '#description' => t('Enter your API key. If you don\'t have one, visit <a target="_blank" href="https://www.behance.net/dev/register">this page</a> and get your Behance API key.'),
      '#default_value' => $config->get('api_key'),
    );

    // User ID or username field.
    $form['user_id'] = array(
      '#type' => 'textfield',
      '#title' => t('Username or User ID'),
      '#required' => TRUE,
      '#description' => t("Enter portfolio owner's ID or username."),
      '#default_value' => $config->get('user_id'),
    );

    // New tab checkbox.
    $form['new_tab'] = array(
      '#type' => 'checkbox',
      '#title' => t('Open links in new tab'),
      '#description' => t('Check if you want Behance links to be opened in the new tab.'),
      '#default_value' => $config->get('new_tab'),
    );

    return parent::buildForm($form, $form_state);

  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    $api_key = $form_state->getValue('api_key');
    $user_id = $form_state->getValue('user_id');

    $isDataValid = $this->isDataValid($api_key, $user_id);

    if ($isDataValid == 403) {
      $form_state->setErrorByName('api_key', $this->t('Your API key is not valid'));
    }

    if ($isDataValid == 404) {
      $form_state->setErrorByName('user_id', $this->t('User not found'));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->clearCacheFiles();

    $values = $form_state->getValues();

    $this->config('behance_api.settings')
      ->set('api_key', $values['api_key'])
      ->set('user_id', $values['user_id'])
      ->set('new_tab', $values['new_tab'])
      ->save();

    parent::submitForm($form, $form_state);

  }

  /**
   * Check if API key and User ID are valid.
   */
  private function isDataValid($api_key, $user_id) {

    $response_json = $this->fileGetContentsCurl('https://api.behance.net/v2/users/' . $user_id . '?client_id=' . $api_key);

    $response_array = json_decode($response_json, TRUE);

    // Valid (200 = OK).
    if ($response_array['http_code'] == 200) {
      return 200;
    }
    // API key in not valid.
    elseif ($response_array['http_code'] == 403) {
      return 403;
    }
    // User not found.
    elseif ($response_array['http_code'] == 404) {
      return 404;
    }

  }

  /**
   * Delete cache files.
   */
  private function clearCacheFiles() {

    if (file_exists('public://behance_fields.json')) {
      unlink('public://behance_fields.json');
    }

    if (file_exists('public://behance_projects.json')) {
      unlink('public://behance_projects.json');
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
