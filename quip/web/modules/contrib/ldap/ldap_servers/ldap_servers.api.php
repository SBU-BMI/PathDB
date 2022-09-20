<?php

/**
 * @file
 * Hooks provided by ldap_servers module.
 */

declare(strict_types = 1);

use Drupal\ldap_servers\Entity\Server;

/**
 * Periodically affect an LDAP associated user or its corresponding LDAP entry.
 *
 * When cron runs a batch of LDAP associated Drupal accounts
 * will be looked at and marked as tested.  over the course
 * of time all LDAP related users will be looked at.
 *
 * Each module implementing this hook is responsible for
 * altering LDAP entries and Drupal user objects; simply
 * altering the variables will have no affect on the actual
 * LDAP entry or Drupal user
 *
 * @param array $users
 *   Batch of users.
 */
function hook_ldap_servers_user_cron(array &$users) {

}

/**
 * Helper hook to see if a batch of LDAP users needs to be queried.
 *
 * If a module implements hook_ldap_servers_user_cron,
 * but currently does not need to process user cron batches,
 * it should return FALSE.
 */
function hook_ldap_servers_user_cron_needed(): bool {
  return TRUE;
}

/**
 * Alter LDAP entry before entities are provisioned.
 *
 * This should be invoked before provisioning LDAP entries.
 *
 * @param array $ldap_entries
 *   LDAP entries as array keyed on lowercase DN of entry with value of array in
 *   format used in ldap_add or ldap_modify function, e.g.:
 *   ['cn=jkool,ou=guest accounts,dc=ad,dc=myuniversity,dc=edu' => [
 *    "attribute1" => array("value"),
 *    "attribute2" => array("value1", "value2"),
 *   ];.
 * @param \Drupal\ldap_servers\Entity\Server $ldap_server
 *   Server entity that is performing provisioning.
 * @param array $context
 *   Context ith the following key/values:
 *   'action' => add|modify|delete
 *   'corresponding_drupal_data' => if LDAP entries have corresponding drupal
 *     objects, such as LDAP user entries and Drupal user objects; LDAP groups
 *     and Drupal roles; etc this will be array keyed on lowercase DN with
 *     values of objects, e.g.:
 *     ['corresponding_drupal_data'] => [
 *      'cn=jkool,ou=guest accounts,dc=ad,dc=myuniversity,dc=edu' => $userA,
 *      'cn=jfun,ou=guest accounts,dc=ad,dc=myuniversity,dc=edu' => $userB,
 *     ]
 *    'corresponding_drupal_data_type' => 'user', 'role', etc. If it is 'user',
 *     then $context has the account in the 'account' key.
 */
function hook_ldap_entry_pre_provision_alter(array &$ldap_entries, Server $ldap_server, array $context) {

}

/**
 * Allows modules to react to provisioning of LDAP entries.
 *
 * This should be invoked after provisioning LDAP entries. Same signature as
 * hook_ldap_entry_pre_provision_alter with LDAP entries not passed by reference
 * LDAP entries are not queried after provisioning, so $ldap_entries are in form
 * hook_ldap_entry_pre_provision; not actual queryied LDAP entries. If actual
 * LDAP entries are available after provisioning, they will be in
 * $context['provisioned_ldap_entries][<dn>] => LDAP entry array in format of an
 * LDAP LDAP query returned from ldap_get_entries() with 'count' keys.
 *
 * @param array $ldap_entries
 *   LDAP entries.
 * @param \Drupal\ldap_servers\Entity\Server $ldap_server
 *   Server entity that is performing provisioning.
 * @param array $context
 *   Submission context.
 *
 * @see hook_ldap_entry_pre_provision_alter()
 */
function hook_ldap_entry_post_provision(array &$ldap_entries, Server $ldap_server, array $context) {

}
