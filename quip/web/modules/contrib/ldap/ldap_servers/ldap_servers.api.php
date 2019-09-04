<?php

/**
 * @file
 * Hooks provided by ldap_servers module.
 */

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
function hook_ldap_servers_user_cron_needed() {
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

/**
 * Perform alterations of LDAP attributes before query is made.
 *
 * To avoid excessive attributes in an LDAP query, modules should
 * alter attributes needed based on $op parameter.
 *
 * @param array $attributes
 *   Array of attributes to be returned from LDAP queries where:
 *     - each key is LDAP attribute name (e.g. mail, cn)
 *     - each value is associative array of form:
 *       - 'conversion' => NULL,
 *       - 'values' => array(0 => 'john', 1 => 'johnny'))
 * @param array $params
 *   Context array with some or all of the following key/values
 *   'sid' => Drupal account object,
 *   'ldap_context' => ,
 *   'direction' =>.
 */
function hook_ldap_attributes_needed_alter(array &$attributes, array $params) {

}

/**
 * Perform alterations of $ldap_user variable.
 *
 * @param array $ldap_entry
 *   LDAP entry result.
 * @param array $params
 *   Context array with some or all of the following key/values
 *   'account' => Drupal account object,
 *   'ldap_context' => ,
 *   'module' =>  module calling alter, e.g. 'ldap_user',
 *   'function' => function calling alter, e.g. 'provisionLdapEntry'.
 */
function hook_ldap_user_alter(array &$ldap_entry, array $params) {

}

/**
 * Allow the results from the LDAP search answer to be modified.
 *
 * The query parameters are provided as context information (read-only).
 *
 * @param array $entries
 *   LDAP entry result.
 * @param array $ldap_query_params
 *   Parameters for the query.
 */
function hook_ldap_server_search_results_alter(array &$entries, array $ldap_query_params) {
  // Look for a specific part of the $results array
  // and maybe change it.
}
