<?php

declare(strict_types = 1);

namespace Drupal\ldap_authentication\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;

/**
 * LDAP help redirect controller.
 */
class LdapHelpRedirect extends ControllerBase {

  /**
   * Redirect.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   *   Redirect response.
   */
  public function redirectUrl(): TrustedRedirectResponse {
    $url = $this->config('ldap_authentication.settings')
      ->get('ldapUserHelpLinkUrl');
    $cacheable_metadata = new CacheableMetadata();
    $cacheable_metadata->setCacheMaxAge(0);
    $response = new TrustedRedirectResponse($url);
    $response->addCacheableDependency($cacheable_metadata);
    return $response;
  }

}
