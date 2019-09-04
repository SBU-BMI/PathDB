<?php

namespace Drupal\ldap_help\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for LDAP debug settings.
 */
class LdapDebugSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ldap_help_debug_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ldap_help.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['#title'] = "Configure LDAP Preferences";
    $form['watchdog_detail'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled Detailed LDAP Watchdog logging.'),
      '#description' => $this->t('This is generally useful for debugging and reporting issues with the LDAP modules and should not be left enabled in a production environment.'),
      '#default_value' => $this->config('ldap_help.settings')->get('watchdog_detail'),
    ];
    $form = parent::buildForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('ldap_help.settings')
      ->set('watchdog_detail', $form_state->getValue('watchdog_detail'))
      ->save();
  }

}
