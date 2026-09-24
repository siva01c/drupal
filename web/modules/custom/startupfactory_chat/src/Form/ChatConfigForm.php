<?php

namespace Drupal\startupfactory_chat\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for chat integration settings.
 */
class ChatConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['startupfactory_chat.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'startupfactory_chat_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('startupfactory_chat.settings');

    $form['ragchat'] = [
      '#type' => 'details',
      '#title' => $this->t('RagChat Connection'),
      '#open' => TRUE,
    ];

    $form['ragchat']['ragchat_base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('RagChat Base URL'),
      '#description' => $this->t('The base URL of the ragchat instance (e.g., http://ragchat.local:8080).'),
      '#default_value' => $config->get('ragchat_base_url') ?? 'http://ragchat.local:8080',
      '#required' => TRUE,
    ];

    $form['ragchat']['ragchat_api_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('RagChat API Token'),
      '#description' => $this->t('API token for authenticating with ragchat. Can also be stored in the Key module as "startupfactory_ragchat_token".'),
      '#default_value' => $config->get('ragchat_api_token') ?? '',
    ];

    $form['jwt'] = [
      '#type' => 'details',
      '#title' => $this->t('JWT Authentication'),
      '#open' => TRUE,
    ];

    $form['jwt']['jwt_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('JWT Secret Key'),
      '#description' => $this->t('Secret key for signing JWT tokens. Leave empty to auto-generate.'),
      '#default_value' => $config->get('jwt_secret') ?? '',
    ];

    $form['chat'] = [
      '#type' => 'details',
      '#title' => $this->t('Chat Widget Settings'),
      '#open' => TRUE,
    ];

    $form['chat']['default_theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Widget Theme'),
      '#options' => [
        'light' => $this->t('Light'),
        'dark' => $this->t('Dark'),
      ],
      '#default_value' => $config->get('default_theme') ?? 'light',
    ];

    $form['chat']['default_greeting'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default Greeting'),
      '#default_value' => $config->get('default_greeting') ?? $this->t('Hi! I can help you configure your project. What would you like to do?'),
    ];

    $form['chat']['default_placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default Input Placeholder'),
      '#default_value' => $config->get('default_placeholder') ?? $this->t('Ask me anything...'),
    ];

    $form['chat']['default_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default Widget Height'),
      '#default_value' => $config->get('default_height') ?? '400px',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('startupfactory_chat.settings')
      ->set('ragchat_base_url', $form_state->getValue('ragchat_base_url'))
      ->set('ragchat_api_token', $form_state->getValue('ragchat_api_token'))
      ->set('jwt_secret', $form_state->getValue('jwt_secret'))
      ->set('default_theme', $form_state->getValue('default_theme'))
      ->set('default_greeting', $form_state->getValue('default_greeting'))
      ->set('default_placeholder', $form_state->getValue('default_placeholder'))
      ->set('default_height', $form_state->getValue('default_height'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
