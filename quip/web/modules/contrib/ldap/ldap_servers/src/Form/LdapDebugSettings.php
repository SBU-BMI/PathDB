<?php

declare(strict_types = 1);

namespace Drupal\ldap_servers\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for LDAP debug settings.
 */
class LdapDebugSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ldap_servers_debug_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['ldap_servers.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['#title'] = 'Configure LDAP Preferences';
    $form['watchdog_detail'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled Detailed LDAP Watchdog logging.'),
      '#description' => $this->t('This is generally useful for debugging and reporting issues with the LDAP modules and should not be left enabled in a production environment.'),
      '#default_value' => $this->config('ldap_servers.settings')->get('watchdog_detail'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('ldap_servers.settings')
      ->set('watchdog_detail', $form_state->getValue('watchdog_detail'))
      ->save();
  }

}
