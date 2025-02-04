<?php

declare(strict_types=1);

namespace Drupal\authorization_drupal_roles;

/**
 * Interface Authorization Drupal Roles Interface.
 */
interface AuthorizationDrupalRolesInterface {

  /**
   * Get roles.
   *
   * @param int $user_id
   *   The user ID.
   * @param string $profile_id
   *   The profile ID.
   *
   * @return array
   *   The roles.
   */
  public function getRoles($user_id, string $profile_id);

  /**
   * Set roles.
   *
   * @param int $user_id
   *   The user ID.
   * @param string $profile_id
   *   The profile ID.
   * @param array $roles
   *   The roles.
   */
  public function setRoles($user_id, string $profile_id, array $roles);

}
