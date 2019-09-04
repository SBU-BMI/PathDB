<?php

/**
 * @file
 * Summary of hooks and other developer related functions.
 */

/**
 * Alter the allowed user results.
 *
 * Allow a custom module to examine the user's LDAP details and refuse
 * authentication. The actual $hook_result is passed by reference. See also:
 * http://drupal.org/node/1634930
 *
 * @param array $ldap_user
 *   See README.developers.txt for structure.
 * @param string $name
 *   The Drupal account name or proposed Drupal account name if none exists yet.
 * @param bool $hook_result
 *   TRUE for allow, FALSE for deny. If set to TRUE or FALSE, another module has
 *   already set this and function should be careful about overriding this.
 */
function hook_ldap_authentication_allowuser_results_alter(array $ldap_user, $name, &$hook_result) {

  // Other module has denied user, should not override.
  if ($hook_result === FALSE) {
    return;
  }
  // Other module has allowed, maybe override.
  elseif ($hook_result === TRUE) {
    if (mymodule_dissapproves($ldap_user, $name)) {
      $hook_result = FALSE;
    }
  }

}
