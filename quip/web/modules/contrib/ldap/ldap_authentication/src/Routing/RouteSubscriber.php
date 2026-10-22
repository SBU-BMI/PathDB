<?php

declare(strict_types=1);

namespace Drupal\ldap_authentication\Routing;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Authentication route subscriber.
 *
 * @package Drupal\ldap_authentication\Routing
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    if ($route = $collection->get('user.pass')) {
      $route->setRequirement('_custom_access', '\Drupal\ldap_authentication\Routing\RouteSubscriber::validateResetPasswordAllowed');
    }
  }

  /**
   * Checks whether password reset is allowed.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Show password or not.
   */
  public static function validateResetPasswordAllowed(): AccessResultInterface {
    $config = \Drupal::config('ldap_authentication.settings');
    $user = \Drupal::currentUser();

    if ($user->isAuthenticated()) {
      /** @var \Drupal\externalauth\Authmap $authmap */
      $authmap = \Drupal::service('externalauth.authmap');
      $authname = $authmap->get((int) $user->id(), 'ldap_user');

      if ($authname) {
        // It's an LDAP account so access will be denied.
        return AccessResult::forbidden();
      }
    }

    if ($config->get('authenticationMode') === 'mixed') {
      return AccessResult::allowed();
    }

    // Hide reset password for users if LDAP-only authentication and
    // password updates are disabled, otherwise show.
    if ($config->get('passwordOption') === 'allow') {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
