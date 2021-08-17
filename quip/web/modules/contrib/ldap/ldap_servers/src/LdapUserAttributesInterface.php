<?php

declare(strict_types = 1);

namespace Drupal\ldap_servers;

/**
 * Collection of hardcoded constants in use in ldap_user.
 *
 * This is in ldap_servers instead of ldap_user to avoid dependency issues
 * reported in #2912463.
 */
interface LdapUserAttributesInterface {

  /**
   * Provision config.
   *
   * @var string
   */
  public const PROVISION_TO_DRUPAL = 'drupal';

  /**
   * Provision config.
   *
   * @var string
   */
  public const PROVISION_TO_LDAP = 'ldap';

  /**
   * Provision config.
   *
   * @var string
   */
  public const PROVISION_TO_NONE = 'none';

  /**
   * Provision config.
   *
   * @var string
   */
  const PROVISION_TO_ALL = 'all';

  /**
   * Provision config.
   *
   * @var string
   */
  public const PROVISION_DRUPAL_USER_ON_USER_UPDATE_CREATE = 'drupal_on_update_create';

  /**
   * Provision config.
   *
   * @var string
   */
  public const PROVISION_DRUPAL_USER_ON_USER_AUTHENTICATION = 'drupal_on_login';

  /**
   * Provision config.
   *
   * @var string
   */
  public const PROVISION_DRUPAL_USER_ON_USER_ON_MANUAL_CREATION = 'drupal_on_manual_creation';

  /**
   * Provision config.
   *
   * @var string
   */
  public const PROVISION_LDAP_ENTRY_ON_USER_ON_USER_UPDATE_CREATE = 'ldap_on_update_create';

  /**
   * Provision config.
   *
   * @var string
   */
  public const PROVISION_LDAP_ENTRY_ON_USER_ON_USER_AUTHENTICATION = 'ldap_on_login';

  /**
   * Provision config.
   *
   * @var string
   */
  public const PROVISION_LDAP_ENTRY_ON_USER_ON_USER_DELETE = 'ldap_on_delete';

  /**
   * Event config.
   *
   * @var string
   */
  public const EVENT_CREATE_DRUPAL_USER = 'create_drupal_user';

  /**
   * Event config.
   *
   * @var string
   */
  public const EVENT_SYNC_TO_DRUPAL_USER = 'sync_to_drupal_user';

  /**
   * Event config.
   *
   * @var string
   */
  public const EVENT_CREATE_LDAP_ENTRY = 'create_ldap_entry';

  /**
   * Event config.
   *
   * @var string
   */
  public const EVENT_SYNC_TO_LDAP_ENTRY = 'sync_to_ldap_entry';

  /**
   * Event config.
   *
   * @var string
   */
  public const EVENT_LDAP_ASSOCIATE_DRUPAL_USER = 'ldap_associate_drupal_user';

  /**
   * Event config.
   *
   * @var string
   */
  public const ACCOUNT_CREATION_LDAP_BEHAVIOUR = 'ldap_behaviour';

  /**
   * Config.
   *
   * @var string
   */
  public const ACCOUNT_CREATION_USER_SETTINGS_FOR_LDAP = 'user_settings_for_ldap';

  /**
   * Config.
   *
   * @var string
   */
  public const USER_CONFLICT_LOG = 'log';

  /**
   * Config.
   *
   * @var string
   */
  public const USER_CONFLICT_ATTEMPT_RESOLVE = 'resolve';

  /**
   * Config.
   *
   * @var string
   */
  public const MANUAL_ACCOUNT_CONFLICT_REJECT = 'conflict_reject';

  /**
   * Config.
   *
   * @var string
   */
  public const MANUAL_ACCOUNT_CONFLICT_LDAP_ASSOCIATE = 'conflict_associate';

  /**
   * Config.
   *
   * @var string
   */
  public const MANUAL_ACCOUNT_CONFLICT_SHOW_OPTION_ON_FORM = 'conflict_show_option';

  /**
   * Config.
   *
   * @var string
   */
  public const MANUAL_ACCOUNT_CONFLICT_NO_LDAP_ASSOCIATE = 'conflict_no_ldap_associate';

}
