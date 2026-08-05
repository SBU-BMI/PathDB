<?php

namespace Drupal\jwt_oauth_ccf\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Checks access to the per-user OAuth client credential management pages.
 *
 * Access is granted when the account may administer any credentials, or when it
 * is managing its own account and may manage its own credentials.
 */
class ManageClientsAccessCheck implements AccessInterface {

  /**
   * Checks access for the manage-clients routes.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account requesting access.
   * @param \Drupal\user\UserInterface $user
   *   The user whose credentials are being managed (the {user} route param).
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, UserInterface $user) {
    $is_admin = $account->hasPermission('administer oauth client credentials');
    $is_own = (int) $account->id() === (int) $user->id()
      && $account->hasPermission('manage own oauth client credentials');

    return AccessResult::allowedIf($is_admin || $is_own)
      // Recompute if the viewed account or the viewer's permissions change.
      ->cachePerUser()
      ->addCacheableDependency($user);
  }

}
