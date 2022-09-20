<?php

declare(strict_types = 1);

namespace Drupal\ldap_servers\Helper;

/**
 * Temporarily stores credentials from user input.
 *
 * This temporary storage is required so that LDAP can work with them in the
 * clear independent of the login form process and to avoid passing them
 * around dozens of functions.
 */
class CredentialsStorage {

  /**
   * User DN.
   *
   * @var string
   */
  private static $userDn;

  /**
   * User Password.
   *
   * @var string
   */
  private static $userPassword;

  /**
   * Validate.
   *
   * @var bool
   */
  private static $validate = FALSE;

  /**
   * Stores the user DN as provided by other LDAP modules.
   *
   * @param string|null $userDn
   *   DN to store.
   */
  public static function storeUserDn(?string $userDn): void {
    self::$userDn = $userDn;
  }

  /**
   * Stores the password from user input.
   *
   * @param string|null $password
   *   Password to store.
   */
  public static function storeUserPassword(?string $password): void {
    self::$userPassword = $password;
  }

  /**
   * Turn testing of user credentials on or off.
   *
   * @param bool $validate
   *   Defaults to false.
   */
  public static function testCredentials(bool $validate): void {
    self::$validate = $validate;
  }

  /**
   * Return the temporarily saved user DN.
   *
   * @return null|string
   *   Login name.
   */
  public static function getUserDn(): ?string {
    return self::$userDn;
  }

  /**
   * Return the temporarily saved user password.
   *
   * @return null|string
   *   Login password.
   */
  public static function getPassword(): ?string {
    return self::$userPassword;
  }

  /**
   * Whether the bind function will use these credentials.
   *
   * @return bool
   *   Defaults to false.
   */
  public static function validateCredentials(): bool {
    return self::$validate;
  }

}
