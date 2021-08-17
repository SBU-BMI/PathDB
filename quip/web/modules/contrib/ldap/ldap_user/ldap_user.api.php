<?php

/**
 * @file
 * Hooks and functions relevant to developers.
 */

declare(strict_types = 1);

use Drupal\user\UserInterface;
use Symfony\Component\Ldap\Entry;

/**
 * Hook_ldap_user_attrs_alter().
 *
 * Alter list of available Drupal user targets (fields, properties, etc.)
 * for ldap_user provisioning mapping form (admin/config/people/ldap/user)
 *
 * @param array $available_user_attrs
 *   User attributes in the form of:
 *   [<field_type>.<field_name>] => array(
 *     'name' => string for user friendly name for the UI,
 *     'source' => LDAP attribute (even if target of sync.  this should be
 *       refactored at some point to avoid confusion)
 *     'configurable' =>
 *     'configured_mapping'  0 | 1, is this configurable?
 *     'configurable_to_ldap' =>  0 | 1, is this configurable?
 *     'user_tokens' => <user_tokens>
 *     'convert' => 1 | 0 convert from binary to string for storage and
 *       comparison purposes
 *     'direction' => LdapUserAttributesInterface::PROVISION_TO_DRUPAL or
 *       LdapUserAttributesInterface::PROVISION_TO_LDAP
 *       leave empty if configurable
 *     'config_module' => module providing syncing configuration.
 *     'prov_module' => module providing actual syncing of attributes.
 *     'prov_events' => array( )
 * @param array $params
 *   Parameters.
 */
function hook_ldap_user_attributes_alter(array &$available_user_attrs, array &$params) {

  // Search for _ldap_user_attributes_alter for good examples the general trick
  // to implementing this hook is make sure to specify config and sync module if
  // its configurable by ldap_user module, don't specify convert user_tokens,
  // direction.  these will be set by UI and stored values be sure to merge with
  // existing values as ldap_user configured values will already exist in
  // $available_user_attrs.
}

/**
 * Alter the user object in the context of an LDAP entry during synchronization.
 *
 * @param \Drupal\user\UserInterface $account
 *   The edit array (see hook_user_insert). Make changes to this object as
 *   required.
 * @param \Symfony\Component\Ldap\Entry $entry
 *   The LDAP entry for the Drupal user.
 * @param array $context
 *   Contains ldap_server and provisioning events.
 *
 * @see \Drupal\ldap_user\Processor\DrupalUserProcessor::applyAttributesToAccount()
 * @see \Drupal\ldap_user\Processor\DrupalUserProcessor::applyAttributesToAccountOnCreate()
 */
function hook_ldap_user_edit_user_alter(UserInterface $account, Entry $entry, array $context) {
  /** @var \Drupal\ldap_servers\Processor\TokenProcessor $tokenProcessor */
  $tokenProcessor = \Drupal::service('ldap.token_processor');

  $value = $tokenProcessor->ldapEntryReplacementsForDrupalAccount($entry, '[sn]');
  $account->set('myfield', $value);
}
