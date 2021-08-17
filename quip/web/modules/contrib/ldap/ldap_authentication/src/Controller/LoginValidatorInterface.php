<?php

declare(strict_types = 1);

namespace Drupal\ldap_authentication\Controller;

use Drupal\user\UserInterface;
use Symfony\Component\Ldap\Entry;

/**
 * Handles the actual testing of credentials and authentication of users.
 */
interface LoginValidatorInterface {

  /**
   * Perform the actual logging in.
   */
  public function processLogin(): void;

  /**
   * Check if exclusion criteria match.
   *
   * @param string $authName
   *   Authname.
   * @param \Symfony\Component\Ldap\Entry $ldap_user
   *   LDAP Entry.
   *
   * @return bool
   *   Exclusion result.
   */
  public function checkAllowedExcluded(string $authName, Entry $ldap_user): bool;

  /**
   * Returns the derived user account.
   *
   * @return \Drupal\user\UserInterface|null
   *   User account.
   */
  public function getDrupalUser(): ?UserInterface;

  /**
   * Credentials are tested.
   *
   * @return int
   *   Returns the authentication result.
   */
  public function testCredentials(): int;

}
