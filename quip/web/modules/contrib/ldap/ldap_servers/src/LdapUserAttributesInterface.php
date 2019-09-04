<?php

namespace Drupal\ldap_servers;

/**
 * Collection of hardcoded constants in use in ldap_user.
 *
 * This is in ldap_servers instead of ldap_user to avoid dependency issues
 * reported in #2912463.
 */
interface LdapUserAttributesInterface {

  const PROVISION_TO_DRUPAL = 'drupal';
  const PROVISION_TO_LDAP = 'ldap';
  const PROVISION_TO_NONE = 'none';
  const PROVISION_TO_ALL = 'all';

  const PROVISION_DRUPAL_USER_ON_USER_UPDATE_CREATE = 'drupal_on_update_create';
  const PROVISION_DRUPAL_USER_ON_USER_AUTHENTICATION = 'drupal_on_login';
  const PROVISION_DRUPAL_USER_ON_USER_ON_MANUAL_CREATION = 'drupal_on_manual_creation';
  const PROVISION_LDAP_ENTRY_ON_USER_ON_USER_UPDATE_CREATE = 'ldap_on_update_create';
  const PROVISION_LDAP_ENTRY_ON_USER_ON_USER_AUTHENTICATION = 'ldap_on_login';
  const PROVISION_LDAP_ENTRY_ON_USER_ON_USER_DELETE = 'ldap_on_delete';


  const EVENT_CREATE_DRUPAL_USER = 'create_drupal_user';
  const EVENT_SYNC_TO_DRUPAL_USER = 'sync_to_drupal_user';
  const EVENT_CREATE_LDAP_ENTRY = 'create_ldap_entry';
  const EVENT_SYNC_TO_LDAP_ENTRY = 'sync_to_ldap_entry';
  const EVENT_LDAP_ASSOCIATE_DRUPAL_USER = 'ldap_associate_drupal_user';

  const ACCOUNT_CREATION_LDAP_BEHAVIOUR = 'ldap_behaviour';
  const ACCOUNT_CREATION_USER_SETTINGS_FOR_LDAP = 'user_settings_for_ldap';

  const USER_CONFLICT_LOG = 'log';
  const USER_CONFLICT_ATTEMPT_RESOLVE = 'resolve';

  const MANUAL_ACCOUNT_CONFLICT_REJECT = 'conflict_reject';
  const MANUAL_ACCOUNT_CONFLICT_LDAP_ASSOCIATE = 'conflict_associate';
  const MANUAL_ACCOUNT_CONFLICT_SHOW_OPTION_ON_FORM = 'conflict_show_option';
  const MANUAL_ACCOUNT_CONFLICT_NO_LDAP_ASSOCIATE = 'conflict_no_ldap_associate';

}
