<?php

namespace Drupal\ldap_user\Helper;

use Drupal\user\UserInterface;

/**
 * Helper class to wrap external_auth service.
 *
 * @TODO: Inject service properly.
 */
class ExternalAuthenticationHelper {

  /**
   * Replaces the authmap table retired in Drupal 8.
   *
   * In Drupal 7 this was user_set_authmap.
   *
   * @param \Drupal\user\UserInterface $account
   *   Drupal user account.
   * @param string $identifier
   *   Authentication name.
   */
  public static function setUserIdentifier(UserInterface $account, $identifier) {
    $authmap = \Drupal::service('externalauth.authmap');
    $authmap->save($account, 'ldap_user', $identifier);
  }

  /**
   * Called from hook_user_delete ldap_user_user_delete.
   *
   * @param int $uid
   *   Drupal user ID.
   */
  public static function deleteUserIdentifier($uid) {
    $authmap = \Drupal::service('externalauth.authmap');
    $authmap->delete($uid);
  }

  /**
   * Replaces the authmap table retired in Drupal 8.
   */
  public static function getUidFromIdentifierMap($identifier) {
    $authmap = \Drupal::service('externalauth.authmap');
    return $authmap->getUid($identifier, 'ldap_user');
  }

  /**
   * Replaces the authmap table retired in Drupal 8.
   *
   * @param int $uid
   *   Drupal user ID.
   *
   * @return string
   *   Authentication name.
   */
  public static function getUserIdentifierFromMap($uid) {
    $authmap = \Drupal::service('externalauth.authmap');
    $authdata = $authmap->getAuthdata($uid, 'ldap_user');
    if (isset($authdata['authname']) && !empty($authdata['authname'])) {
      return $authdata['authname'];
    }
  }

  /**
   * Check if user is excluded.
   *
   * @param mixed $account
   *   A Drupal user object.
   *
   * @return bool
   *   TRUE if user should be excluded from LDAP provision/syncing
   */
  public static function excludeUser($account = NULL) {
    // @TODO 2914053.
    // Always exclude user 1.
    if (is_object($account) && $account->id() == 1) {
      return TRUE;
    }
    // Exclude users who have been manually flagged as excluded.
    if (is_object($account) && $account->get('ldap_user_ldap_exclude')->value == 1) {
      return TRUE;
    }
    // Everyone else is fine.
    return FALSE;
  }

}
