<?php

declare(strict_types=1);

namespace Drupal\authorization_drupal_roles\Service;

use Drupal\authorization_drupal_roles\AuthorizationDrupalRolesInterface;
use Drupal\user\UserDataInterface;

/**
 * Class UserRoleService.
 *
 * @package Drupal\authorization_drupal_roles\Service
 */
class AuthorizationDrupalRolesService implements AuthorizationDrupalRolesInterface {

  /**
   * User data.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * The module for UserData.
   *
   * Override this in any child class to change its module.
   *
   * @var string
   */
  protected $module = 'authorization_drupal_roles';

  /**
   * UserRoleService constructor.
   *
   * @param \Drupal\user\UserDataInterface $user_data
   *   User data.
   */
  public function __construct(UserDataInterface $user_data) {
    $this->userData = $user_data;
  }

  /**
   * {@inheritdoc}
   */
  public function getRoles($user_id, string $profile_id) {
    $roles = [];
    $role_data = $this->userData->get($this->module, $user_id, 'roles') ?? [];
    foreach ($role_data as $role => $profile) {
      if ($profile === $profile_id) {
        $roles[] = $role;
      }
    }

    return $roles;
  }

  /**
   * {@inheritdoc}
   */
  public function setRoles($user_id, string $profile_id, array $roles) {
    $role_data = $this->userData->get($this->module, $user_id, 'roles') ?? [];
    foreach ($role_data as $role => $profile) {
      if ($profile === $profile_id) {
        unset($role_data[$role]);
      }
    }
    foreach ($roles as $role) {
      $role_data[$role] = $profile_id;
    }

    $this->userData->set($this->module, $user_id, 'roles', $role_data);
  }

}
