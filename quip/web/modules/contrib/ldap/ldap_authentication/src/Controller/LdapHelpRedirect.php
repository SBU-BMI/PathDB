<?php

namespace Drupal\ldap_authentication\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;

/**
 * Class LdapHelpRedirect.
 *
 * @package Drupal\ldap_authentication\Controller
 */
class LdapHelpRedirect extends ControllerBase {

  /**
   * Redirect.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   *   Redirect response.
   */
  public function redirectUrl() {
    $url = $this->configFactory
      ->get('ldap_authentication.settings')
      ->get('ldapUserHelpLinkUrl');
    $response = new TrustedRedirectResponse($url);
    $response->addCacheableDependency(new CacheableMetadata())
      ->setCacheMaxAge(0);
    return $response;
  }

}
