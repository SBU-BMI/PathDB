<?php

/**
 * @file
 * Summary of hooks and other developer related functions.
 */

declare(strict_types = 1);

use Symfony\Component\Ldap\Entry;

/**
 * Alter the allowed user results.
 *
 * Allow a custom module to examine the user's LDAP details and refuse
 * authentication. The actual $hook_result is passed by reference. See also:
 * http://drupal.org/node/1634930
 *
 * @param \Symfony\Component\Ldap\Entry $ldap_user
 *   An LDAP entry.
 * @param string $name
 *   The Drupal account name or proposed Drupal account name if none exists yet.
 * @param bool $hook_result
 *   TRUE for allow, FALSE for deny. If set to TRUE or FALSE, another module has
 *   already set this and function should be careful about overriding this.
 */
function hook_ldap_authentication_allowuser_results_alter(Entry $ldap_user, string $name, bool &$hook_result) {

  // Other module has denied user, should not override.
  if (!$hook_result) {
    return;
  }

  // Other module has allowed, maybe override.
  if (mymodule_dissapproves($ldap_user, $name)) {
    $hook_result = FALSE;
  }
}
