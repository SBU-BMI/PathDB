<?php

namespace Drupal\ldap_authentication\Access;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ldap_authentication\Helper\LdapAuthenticationConfiguration;

/**
 * Checks whether the use is allowed to see the help tab.
 */
class UserHelpTabAccess implements AccessInterface {

  private $config;
  private $currentUser;

  /**
   * Constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AccountInterface $current_user) {
    $this->config = $config_factory->get('ldap_authentication.settings');
    $this->currentUser = $current_user;
  }

  /**
   * Access callback for help tab.
   *
   * @return bool
   *   Whether user is allowed to see tab or not.
   */
  public function accessLdapHelpTab() {
    $mode = $this->config->get('authenticationMode');
    if ($mode == LdapAuthenticationConfiguration::MODE_MIXED) {
      if (ldap_authentication_ldap_authenticated($this->currentUser)) {
        return TRUE;
      }
    }
    elseif ($mode == LdapAuthenticationConfiguration::MODE_EXCLUSIVE) {
      if ($this->currentUser->isAnonymous() || ldap_authentication_ldap_authenticated($this->currentUser)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    if ($this->accessLdapHelpTab()) {
      return AccessResultAllowed::allowed();
    }
    else {
      return AccessResultAllowed::forbidden();
    }
  }

}
