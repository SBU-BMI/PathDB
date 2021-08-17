<?php

namespace Drupal\authorization\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure authorization settings for this site.
 */
class AuthorizationSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'authorization_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'authorization.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('authorization.settings');

    $form['authorization_message'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('If enabled, all authorization and notification messages will be shown to the user.'),
      '#default_value' => $config->get('authorization_message'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('authorization.settings')
      ->set('authorization_message', $form_state->getValue('authorization_message'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
