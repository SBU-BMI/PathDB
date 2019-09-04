<?php

namespace Drupal\ldap_authentication\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;

/**
 * Provides help messages for users when configured.
 */
class DynamicUserHelpLink extends DeriverBase {

  private $config;

  /**
   * Constructor.
   */
  public function __construct() {
    $this->config = \Drupal::config('ldap_authentication.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($basePluginDefinition) {
    if ($this->config->get('ldapUserHelpLinkText') &&
      $this->config->get('ldapUserHelpLinkUrl')) {
      $basePluginDefinition['title'] = $this->config->get('ldapUserHelpLinkText');
      $basePluginDefinition['route_name'] = 'ldap_authentication.ldap_help_redirect';
      $this->derivatives['ldap_authentication.show_user_help_link'] = $basePluginDefinition;
    }
    return $this->derivatives;
  }

}
