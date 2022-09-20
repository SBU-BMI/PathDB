<?php

declare(strict_types = 1);

namespace Drupal\ldap_authentication\Access;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\externalauth\Authmap;

/**
 * Checks whether the use is allowed to see the help tab.
 */
class UserHelpTabAccess implements AccessInterface {

  /**
   * Config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Externalauth.
   *
   * @var \Drupal\externalauth\Authmap
   */
  protected $externalAuth;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config Factory.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user.
   * @param \Drupal\externalauth\Authmap $external_auth
   *   External auth.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    AccountInterface $current_user,
    Authmap $external_auth) {
    $this->config = $config_factory->get('ldap_authentication.settings');
    $this->currentUser = $current_user;
    $this->externalAuth = $external_auth;
  }

  /**
   * Access callback for help tab.
   *
   * @return bool
   *   Whether user is allowed to see tab or not.
   */
  public function accessLdapHelpTab(): bool {
    $mode = $this->config->get('authenticationMode');
    if ($mode === 'mixed') {
      if ($this->externalAuth->get($this->currentUser->id(), 'ldap_user')) {
        return TRUE;
      }
    }
    else {
      if ($this->currentUser->isAnonymous() ||
        $this->externalAuth->get($this->currentUser->id(), 'ldap_user')) {
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

    return AccessResultAllowed::forbidden();
  }

}
