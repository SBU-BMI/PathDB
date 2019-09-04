<?php

namespace Drupal\ldap_user\Processor;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\file\Entity\File;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers\Helper\ConversionHelper;
use Drupal\ldap_servers\Processor\TokenProcessor;
use Drupal\ldap_user\Helper\ExternalAuthenticationHelper;
use Drupal\ldap_servers\LdapUserAttributesInterface;
use Drupal\ldap_user\Helper\LdapConfiguration;
use Drupal\ldap_user\Helper\SemaphoreStorage;
use Drupal\ldap_user\Helper\SyncMappingHelper;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Handles processing of a user from LDAP to Drupal.
 */
class DrupalUserProcessor implements LdapUserAttributesInterface {

  private $config;

  /**
   * The Drupal user account.
   *
   * @var \Drupal\user\Entity\User
   */
  private $account;

  /**
   * The server interacting with.
   *
   * @var \Drupal\ldap_servers\Entity\Server
   */
  private $server;

  /**
   * LDAP server factory.
   *
   * @var \Drupal\ldap_servers\ServerFactory
   */
  private $factory;

  /**
   * LDAP Details logger.
   *
   * @var \Drupal\ldap_servers\Logger\LdapDetailLog
   */
  private $detailLog;

  /**
   * Token processor.
   *
   * @var \Drupal\ldap_servers\Processor\TokenProcessor
   */
  protected $tokenProcessor;

  /**
   * Constructor.
   */
  public function __construct() {
    $this->config = \Drupal::config('ldap_user.settings');
    $this->factory = \Drupal::service('ldap.servers');
    $this->detailLog = \Drupal::service('ldap.detail_log');
    $this->tokenProcessor = \Drupal::service('ldap.token_processor');
  }

  /**
   * Get the user account.
   *
   * @return \Drupal\user\Entity\User
   *   User account.
   */
  public function getUserAccount() {
    return $this->account;
  }

  /**
   * Set LDAP associations of a Drupal account by altering user fields.
   *
   * @param string $drupalUsername
   *   The Drupal username.
   *
   * @return bool
   *   Returns FALSE on invalid user or LDAP accounts.
   */
  public function ldapAssociateDrupalAccount($drupalUsername) {
    if ($this->config->get('drupalAcctProvisionServer')) {
      $ldapServer = $this->factory->getServerByIdEnabled($this->config->get('drupalAcctProvisionServer'));
      $this->account = user_load_by_name($drupalUsername);
      if (!$this->account) {
        \Drupal::logger('ldap_user')->error('Failed to LDAP associate Drupal account %drupal_username because account not found', ['%drupal_username' => $drupalUsername]);
        return FALSE;
      }

      $ldap_user = $ldapServer->matchUsernameToExistingLdapEntry($drupalUsername);
      if (!$ldap_user) {
        \Drupal::logger('ldap_user')->error('Failed to LDAP associate Drupal account %drupal_username because corresponding LDAP entry not found', ['%drupal_username' => $drupalUsername]);
        return FALSE;
      }

      $persistentUid = $ldapServer->userPuidFromLdapEntry($ldap_user['attr']);
      if ($persistentUid) {
        $this->account->set('ldap_user_puid', $persistentUid);
      }
      $this->account->set('ldap_user_puid_property', $ldapServer->get('unique_persistent_attr'));
      $this->account->set('ldap_user_puid_sid', $ldapServer->id());
      $this->account->set('ldap_user_current_dn', $ldap_user['dn']);
      $this->account->set('ldap_user_last_checked', time());
      $this->account->set('ldap_user_ldap_exclude', 0);
      $this->saveAccount();

      $this->syncToDrupalAccount(self::EVENT_CREATE_DRUPAL_USER, $ldap_user);

      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Provision a Drupal user account.
   *
   * Given user data, create a user and apply LDAP attributes or assign to
   * correct user if name has changed through PUID.
   *
   * @param array $userData
   *   A keyed array normally containing 'name' and optionally more.
   *
   * @return bool|\Drupal\user\Entity\User
   *   Return the user on success or FALSE on any problem.
   */
  public function provisionDrupalAccount(array $userData) {

    $this->account = User::create($userData);
    $ldapUser = FALSE;

    // Get an LDAP user from the LDAP server.
    if ($this->config->get('drupalAcctProvisionServer')) {
      $ldapUser = $this->factory->getUserDataFromServerByIdentifier($userData['name'], $this->config->get('drupalAcctProvisionServer'));
    }
    // Still no LDAP user.
    if (!$ldapUser) {
      \Drupal::service('ldap.detail_log')->log(
        '@username: Failed to find associated LDAP entry for username in provision.',
        ['@username' => $userData['name']],
        'ldap-user'
      );
      return FALSE;
    }

    $this->server = $this->factory->getServerByIdEnabled($this->config->get('drupalAcctProvisionServer'));

    // If we don't have an account name already we should set one.
    if (!$this->account->getAccountName()) {
      $this->account->set('name', $ldapUser[$this->server->get('user_attr')]);
    }

    // Can we get details from an LDAP server?
    $params = [
      'account' => $this->account,
      'user_values' => $userData,
      'prov_event' => self::EVENT_CREATE_DRUPAL_USER,
      'module' => 'ldap_user',
      'function' => 'provisionDrupalAccount',
      'direction' => self::PROVISION_TO_DRUPAL,
    ];

    \Drupal::moduleHandler()->alter('ldap_entry', $ldapUser, $params);

    // Look for existing Drupal account with the same PUID. If found, update
    // that user instead of creating a new user.
    $persistentUid = $this->server->userPuidFromLdapEntry($ldapUser['attr']);
    $accountFromPuid = ($persistentUid) ? $this->server->userAccountFromPuid($persistentUid) : FALSE;
    if ($accountFromPuid) {
      $this->updateExistingAccountByPersistentUid($ldapUser, $accountFromPuid);
    }
    else {
      if (!$this->createDrupalUser($ldapUser)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Set flag to exclude user from LDAP association.
   *
   * @param string $drupalUsername
   *   The account username.
   *
   * @return bool
   *   TRUE on success, FALSE on error or failure because of invalid user.
   */
  public function ldapExcludeDrupalAccount($drupalUsername) {
    $account = User::load($drupalUsername);
    if (!$account) {
      \Drupal::logger('ldap_user')->error('Failed to exclude user from LDAP association because Drupal account %username was not found', ['%username' => $drupalUsername]);
      return FALSE;
    }

    $account->set('ldap_user_ldap_exclude', 1);
    $account->save();
    return (boolean) $account;
  }

  /**
   * Alter the user's attributes.
   *
   * @param array $attributes
   *   Attributes to change.
   * @param array $params
   *   Parameters.
   *
   * @return array
   *   Altered attributes.
   */
  public function alterUserAttributes(array $attributes, array $params) {
    // Puid attributes are server specific.
    if (isset($params['sid']) && $params['sid']) {
      if (is_scalar($params['sid'])) {
        $ldapServer = $this->factory->getServerByIdEnabled($params['sid']);
      }
      else {
        $ldapServer = $params['sid'];
      }

      if ($ldapServer) {
        if (!isset($attributes['dn'])) {
          $attributes['dn'] = [];
        }
        // Force dn "attribute" to exist.
        $attributes['dn'] = ConversionHelper::setAttributeMap($attributes['dn']);
        // Add the attributes required by the user configuration when
        // provisioning Drupal users.
        switch ($params['ldap_context']) {
          case 'ldap_user_insert_drupal_user':
          case 'ldap_user_update_drupal_user':
          case 'ldap_user_ldap_associate':
            if ($ldapServer->get('user_attr')) {
              $attributes[$ldapServer->get('user_attr')] = ConversionHelper::setAttributeMap(@$attributes[$ldapServer->get('user_attr')]);
            }
            if ($ldapServer->get('mail_attr')) {
              $attributes[$ldapServer->get('mail_attr')] = ConversionHelper::setAttributeMap(@$attributes[$ldapServer->get('mail_attr')]);
            }
            if ($ldapServer->get('picture_attr')) {
              $attributes[$ldapServer->get('picture_attr')] = ConversionHelper::setAttributeMap(@$attributes[$ldapServer->get('picture_attr')]);
            }
            if ($ldapServer->get('unique_persistent_attr')) {
              $attributes[$ldapServer->get('unique_persistent_attr')] = ConversionHelper::setAttributeMap(@$attributes[$ldapServer->get('unique_persistent_attr')]);
            }
            if ($ldapServer->get('mail_template')) {
              ConversionHelper::extractTokenAttributes($attributes, $ldapServer->get('mail_template'));
            }
            break;
        }

        $ldapContext = empty($params['ldap_context']) ? NULL : $params['ldap_context'];
        $direction = empty($params['direction']) ? $this->ldapContextToProvDirection($ldapContext) : $params['direction'];
        $helper = new SyncMappingHelper();
        $attributesRequiredByOtherModuleMappings = $helper->getLdapUserRequiredAttributes($direction, $ldapContext);
        $attributes = array_merge($attributesRequiredByOtherModuleMappings, $attributes);
        return $attributes;

      }
    }
    return $attributes;
  }

  /**
   * LDAP attributes to alter.
   *
   * @param array $availableUserAttributes
   *   Available attributes.
   * @param array $params
   *   Parameters.
   *
   * @return array
   *   Altered attributes.
   */
  public function alterLdapUserAttributes(array $availableUserAttributes, array $params) {
    if (isset($params['direction'])) {
      $direction = $params['direction'];
    }
    else {
      $direction = self::PROVISION_TO_NONE;
    }

    if ($direction == self::PROVISION_TO_LDAP) {
      $availableUserAttributes['[property.name]'] = [
        'name' => 'Property: Username',
        'source' => '',
        'direction' => self::PROVISION_TO_LDAP,
        'enabled' => TRUE,
        'prov_events' => [
          self::EVENT_CREATE_DRUPAL_USER,
          self::EVENT_SYNC_TO_DRUPAL_USER,
        ],
        'config_module' => 'ldap_user',
        'prov_module' => 'ldap_user',
        'configurable_to_ldap' => TRUE,
      ];

      $availableUserAttributes['[property.mail]'] = [
        'name' => 'Property: Email',
        'source' => '',
        'direction' => self::PROVISION_TO_LDAP,
        'enabled' => TRUE,
        'prov_events' => [
          self::EVENT_CREATE_DRUPAL_USER,
          self::EVENT_SYNC_TO_DRUPAL_USER,
        ],
        'config_module' => 'ldap_user',
        'prov_module' => 'ldap_user',
        'configurable_to_ldap' => TRUE,
      ];

      $availableUserAttributes['[property.picture]'] = [
        'name' => 'Property: picture',
        'source' => '',
        'direction' => self::PROVISION_TO_LDAP,
        'enabled' => TRUE,
        'prov_events' => [
          self::EVENT_CREATE_DRUPAL_USER,
          self::EVENT_SYNC_TO_DRUPAL_USER,
        ],
        'config_module' => 'ldap_user',
        'prov_module' => 'ldap_user',
        'configurable_to_ldap' => TRUE,
      ];

      $availableUserAttributes['[property.uid]'] = [
        'name' => 'Property: Drupal User Id (uid)',
        'source' => '',
        'direction' => self::PROVISION_TO_LDAP,
        'enabled' => TRUE,
        'prov_events' => [
          self::EVENT_CREATE_DRUPAL_USER,
          self::EVENT_SYNC_TO_DRUPAL_USER,
        ],
        'config_module' => 'ldap_user',
        'prov_module' => 'ldap_user',
        'configurable_to_ldap' => TRUE,
      ];

    }

    // 1. Drupal user properties
    // 1.a make sure empty array are present so array + function works.
    foreach (['property.status', 'property.timezone', 'property.signature'] as $property_id) {
      $property_token = '[' . $property_id . ']';
      if (!isset($availableUserAttributes[$property_token]) || !is_array($availableUserAttributes[$property_token])) {
        $availableUserAttributes[$property_token] = [];
      }
    }

    // @todo make these merges so they don't override saved values such as 'enabled'
    $availableUserAttributes['[property.status]'] = $availableUserAttributes['[property.status]'] + [
      'name' => 'Property: Account Status',
      'configurable_to_drupal' => 1,
      'configurable_to_ldap' => 1,
      'user_tokens' => '1=enabled, 0=blocked.',
      'enabled' => FALSE,
      'config_module' => 'ldap_user',
      'prov_module' => 'ldap_user',
    ];

    $availableUserAttributes['[property.timezone]'] = $availableUserAttributes['[property.timezone]'] + [
      'name' => 'Property: User Timezone',
      'configurable_to_drupal' => 1,
      'configurable_to_ldap' => 1,
      'enabled' => FALSE,
      'config_module' => 'ldap_user',
      'prov_module' => 'ldap_user',
    ];

    $availableUserAttributes['[property.signature]'] = $availableUserAttributes['[property.signature]'] + [
      'name' => 'Property: User Signature',
      'configurable_to_drupal' => 1,
      'configurable_to_ldap' => 1,
      'enabled' => FALSE,
      'config_module' => 'ldap_user',
      'prov_module' => 'ldap_user',
    ];

    // 2. Drupal user fields.
    $user_fields = \Drupal::entityManager()->getFieldStorageDefinitions('user');

    foreach ($user_fields as $field_name => $field_instance) {
      $field_id = "[field.$field_name]";
      if (!isset($availableUserAttributes[$field_id]) || !is_array($availableUserAttributes[$field_id])) {
        $availableUserAttributes[$field_id] = [];
      }

      $availableUserAttributes[$field_id] = $availableUserAttributes[$field_id] + [
        'name' => t('Field: @label', ['@label' => $field_instance->getLabel()]),
        'configurable_to_drupal' => 1,
        'configurable_to_ldap' => 1,
        'enabled' => FALSE,
        'config_module' => 'ldap_user',
        'prov_module' => 'ldap_user',
      ];
    }

    if (!LdapConfiguration::provisionsDrupalAccountsFromLdap()) {
      $availableUserAttributes['[property.mail]']['config_module'] = 'ldap_user';
      $availableUserAttributes['[property.name]']['config_module'] = 'ldap_user';
      $availableUserAttributes['[property.picture]']['config_module'] = 'ldap_user';
    }

    if ($direction == self::PROVISION_TO_LDAP) {
      $availableUserAttributes['[password.random]'] = [
        'name' => 'Password: Random password',
        'source' => '',
        'direction' => self::PROVISION_TO_LDAP,
        'enabled' => TRUE,
        'prov_events' => [
          self::EVENT_CREATE_DRUPAL_USER,
          self::EVENT_SYNC_TO_DRUPAL_USER,
        ],
        'config_module' => 'ldap_user',
        'prov_module' => 'ldap_user',
        'configurable_to_ldap' => TRUE,
      ];

      // Use user password when available fall back to random pwd.
      $availableUserAttributes['[password.user-random]'] = [
        'name' => 'Password: Plain user password or random',
        'source' => '',
        'direction' => self::PROVISION_TO_LDAP,
        'enabled' => TRUE,
        'prov_events' => [
          self::EVENT_CREATE_DRUPAL_USER,
          self::EVENT_SYNC_TO_DRUPAL_USER,
        ],
        'config_module' => 'ldap_user',
        'prov_module' => 'ldap_user',
        'configurable_to_ldap' => TRUE,
      ];

      // Use user password, do not modify if unavailable.
      $availableUserAttributes['[password.user-only]'] = [
        'name' => 'Password: Plain user password',
        'source' => '',
        'direction' => self::PROVISION_TO_LDAP,
        'enabled' => TRUE,
        'prov_events' => [
          self::EVENT_CREATE_DRUPAL_USER,
          self::EVENT_SYNC_TO_DRUPAL_USER,
        ],
        'config_module' => 'ldap_user',
        'prov_module' => 'ldap_user',
        'configurable_to_ldap' => TRUE,
      ];

    }

    // TODO: This is possibly an overlap with SyncMappingHelper.
    $mappings = $this->config->get('ldapUserSyncMappings');

    // This is where need to be added to arrays.
    if (!empty($mappings[$direction])) {
      $availableUserAttributes = $this->applyUserAttributes($availableUserAttributes, $mappings, $direction);
    }

    return [$availableUserAttributes, $params];
  }

  /**
   * Test if the user is LDAP associated.
   *
   * @param \Drupal\user\UserInterface $account
   *   The Drupal user.
   *
   * @return bool
   *   Whether the user is LDAP associated.
   */
  public function isUserLdapAssociated(UserInterface $account) {

    $associated = FALSE;

    if (property_exists($account, 'ldap_user_current_dn') &&
      !empty($account->get('ldap_user_current_dn')->value)) {
      $associated = TRUE;
    }
    elseif ($account->id()) {
      $authmap = ExternalAuthenticationHelper::getUserIdentifierFromMap($account->id());
      if (!empty($authmap)) {
        $associated = TRUE;
      }
    }

    return $associated;
  }

  /**
   * Callback for hook_ENTITY_TYPE_insert().
   *
   * Perform any actions required, due to possibly not being the module creating
   * the user.
   *
   * @param \Drupal\user\UserInterface $account
   *   The Drupal user.
   */
  public function newDrupalUserCreated(UserInterface $account) {
    $this->account = $account;

    if (ExternalAuthenticationHelper::excludeUser($account)) {
      return;
    }

    if (is_object($account) && $account->getAccountName()) {
      // Check for first time user.
      if (SemaphoreStorage::get('provision', $account->getAccountName())
        || SemaphoreStorage::get('sync', $account->getAccountName())
        || $this->newAccountRequest($account)) {
        return;
      }
    }

    // The account is already created, so do not provisionDrupalAccount(), just
    // syncToDrupalAccount(), even if action is 'provision'.
    if ($account->isActive() && LdapConfiguration::provisionAvailableToDrupal(self::PROVISION_DRUPAL_USER_ON_USER_UPDATE_CREATE)) {
      $this->syncToDrupalAccount(self::EVENT_CREATE_DRUPAL_USER);
    }

    $this->provisionLdapEntryOnUserCreation($account);
  }

  /**
   * Callback for hook_ENTITY_TYPE_update().
   *
   * @param \Drupal\user\UserInterface $account
   *   The Drupal user.
   */
  public function drupalUserUpdated(UserInterface $account) {
    // For some reason cloning was only necessary on the update hook.
    $this->account = clone $account;
    if (ExternalAuthenticationHelper::excludeUser($this->account)) {
      return;
    }

    // Check for provisioning to LDAP; this will normally occur on
    // hook_user_insert or other event when Drupal user is created.
    if ($this->provisionsLdapEntriesFromDrupalUsers() && LdapConfiguration::provisionAvailableToLdap(self::PROVISION_LDAP_ENTRY_ON_USER_ON_USER_UPDATE_CREATE)) {
      $this->provisionLdapEntryOnUserUpdateCreateEvent();
    }

    if (SemaphoreStorage::get('sync_drupal', $this->account->getAccountName())) {
      return;
    }
    else {
      SemaphoreStorage::set('sync_drupal', $this->account->getAccountName());
      if (LdapConfiguration::provisionsDrupalAccountsFromLdap() && in_array(self::EVENT_SYNC_TO_DRUPAL_USER, array_keys(LdapConfiguration::provisionsDrupalEvents()))) {
        $this->syncToDrupalAccount(self::EVENT_SYNC_TO_DRUPAL_USER);
      }
    }
  }

  /**
   * Handle Drupal user login.
   *
   * @param \Drupal\user\UserInterface $account
   *   The Drupal user.
   */
  public function drupalUserLogsIn(UserInterface $account) {
    $this->account = $account;
    if (ExternalAuthenticationHelper::excludeUser($this->account)) {
      return;
    }

    $this->loginDrupalAccountProvisioning();
    $this->loginLdapEntryProvisioning();
  }

  /**
   * Handle deletion of Drupal user.
   *
   * @param \Drupal\user\UserInterface $account
   *   The Drupal user account.
   */
  public function drupalUserDeleted(UserInterface $account) {
    // Drupal user account is about to be deleted.
    $this->deleteProvisionedLdapEntry($account);
    ExternalAuthenticationHelper::deleteUserIdentifier($account->id());
  }

  /**
   * Apply user attributes.
   *
   * @param array $availableUserAttributes
   *   Available attributes.
   * @param array $mappings
   *   Mappings.
   * @param string $direction
   *   Synchronization direction.
   *
   * @return array
   *   All attributes applied.
   */
  private function applyUserAttributes(array $availableUserAttributes, array $mappings, $direction) {
    foreach ($mappings[$direction] as $target_token => $mapping) {
      if ($direction == self::PROVISION_TO_DRUPAL && isset($mapping['user_attr'])) {
        $key = $mapping['user_attr'];
      }
      elseif ($direction == self::PROVISION_TO_LDAP && isset($mapping['ldap_attr'])) {
        $key = $mapping['ldap_attr'];
      }
      else {
        continue;
      }

      $keys = [
        'ldap_attr',
        'user_attr',
        'convert',
        'direction',
        'enabled',
        'prov_events',
      ];

      foreach ($keys as $subKey) {
        if (isset($mapping[$subKey])) {
          $availableUserAttributes[$key][$subKey] = $mapping[$subKey];
        }
        else {
          $availableUserAttributes[$key][$subKey] = NULL;
        }
        $availableUserAttributes[$key]['config_module'] = 'ldap_user';
        $availableUserAttributes[$key]['prov_module'] = 'ldap_user';
      }
      if ($mapping['user_attr'] == 'user_tokens') {
        $availableUserAttributes['user_attr'] = $mapping['user_tokens'];
      }
    }
    return $availableUserAttributes;
  }

  /**
   * Create a Drupal user.
   *
   * @param array $ldap_user
   *   The LDAP user.
   */
  private function createDrupalUser(array $ldap_user) {
    $this->account->enforceIsNew();
    $this->applyAttributesToAccount($ldap_user, self::PROVISION_TO_DRUPAL, [self::EVENT_CREATE_DRUPAL_USER]);
    $tokens = ['%drupal_username' => $this->account->getAccountName()];
    if (empty($this->account->getAccountName())) {
      drupal_set_message(t('User account creation failed because of invalid, empty derived Drupal username.'), 'error');
      \Drupal::logger('ldap_user')
        ->error('Failed to create Drupal account %drupal_username because Drupal username could not be derived.', $tokens);
      return FALSE;
    }
    if (!$mail = $this->account->getEmail()) {
      drupal_set_message(t('User account creation failed because of invalid, empty derived email address.'), 'error');
      \Drupal::logger('ldap_user')
        ->error('Failed to create Drupal account %drupal_username because email address could not be derived by LDAP User module', $tokens);
      return FALSE;
    }

    if ($account_with_same_email = user_load_by_mail($mail)) {
      \Drupal::logger('ldap_user')
        ->error('LDAP user %drupal_username has email address (%email) conflict with a Drupal user %duplicate_name', [
          '%drupal_username' => $this->account->getAccountName(),
          '%email' => $mail,
          '%duplicate_name' => $account_with_same_email->getAccountName(),
        ]
      );
      drupal_set_message(t('Another user already exists in the system with the same email address. You should contact the system administrator in order to solve this conflict.'), 'error');
      return FALSE;
    }
    $this->saveAccount();
    if (!$this->account) {
      drupal_set_message(t('User account creation failed because of system problems.'), 'error');
    }
    else {
      ExternalAuthenticationHelper::setUserIdentifier($this->account, $this->account->getAccountName());
      return TRUE;
    }
  }

  /**
   * Update Drupal user from PUID.
   *
   * @param array $ldap_user
   *   The LDAP user.
   * @param \Drupal\user\UserInterface $accountFromPuid
   *   The account from the PUID.
   */
  private function updateExistingAccountByPersistentUid(array $ldap_user, UserInterface $accountFromPuid) {
    $this->account = $accountFromPuid;
    ExternalAuthenticationHelper::setUserIdentifier($this->account, $this->account->getAccountName());
    $this->syncToDrupalAccount(self::EVENT_SYNC_TO_DRUPAL_USER, $ldap_user);
  }

  /**
   * Process user picture from LDAP entry.
   *
   * @param array $ldap_entry
   *   The LDAP entry.
   *
   * @return bool|\Drupal\file\Entity\File
   *   Drupal file object image user's thumbnail or FALSE if none present or
   *   an error occurs.
   */
  private function userPictureFromLdapEntry(array $ldap_entry) {
    if ($ldap_entry && $this->server->get('picture_attr')) {
      // Check if LDAP entry has been provisioned.
      if (isset($ldap_entry[$this->server->get('picture_attr')][0])) {
        $ldapUserPicture = $ldap_entry[$this->server->get('picture_attr')][0];
      }
      else {
        // No picture present.
        return FALSE;
      }

      // @TODO 2914053.
      if (!$this->account || $this->account->isAnonymous() || $this->account->id() == 1) {
        return FALSE;
      }
      $currentUserPicture = $this->account->get('user_picture')->getValue();
      if (empty($currentUserPicture)) {
        return $this->saveUserPicture($this->account->get('user_picture'), $ldapUserPicture);
      }
      else {
        $file = File::load($currentUserPicture[0]['target_id']);
        if ($file && md5(file_get_contents($file->getFileUri())) == md5($ldapUserPicture)) {
          // Same image, do nothing.
          return FALSE;
        }
        else {
          return $this->saveUserPicture($this->account->get('user_picture'), $ldapUserPicture);
        }
      }
    }
  }

  /**
   * Save the user's picture.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field attached to the user.
   * @param string $ldapUserPicture
   *   The picture itself.
   *
   * @return array|bool
   *   Returns file ID wrapped in target or false.
   */
  private function saveUserPicture(FieldItemListInterface $field, $ldapUserPicture) {
    // Create tmp file to get image format and derive extension.
    $fileName = uniqid();
    $unmanagedFile = file_directory_temp() . '/' . $fileName;
    file_put_contents($unmanagedFile, $ldapUserPicture);
    $image_type = exif_imagetype($unmanagedFile);
    $extension = image_type_to_extension($image_type, FALSE);
    unlink($unmanagedFile);

    $fieldSettings = $field->getFieldDefinition()->getItemDefinition()->getSettings();
    $tokenService = \Drupal::token();
    $directory = $tokenService->replace($fieldSettings['file_directory']);
    $fullDirectoryPath = $fieldSettings['uri_scheme'] . '://' . $directory;

    if (!is_dir(\Drupal::service('file_system')->realpath($fullDirectoryPath))) {
      \Drupal::service('file_system')->mkdir($fullDirectoryPath, NULL, TRUE);
    }

    $managed_file = file_save_data($ldapUserPicture, $fullDirectoryPath . '/' . $fileName . '.' . $extension);

    $validators = [
      'file_validate_is_image' => [],
      'file_validate_image_resolution' => [$fieldSettings['max_resolution']],
      'file_validate_size' => [$fieldSettings['max_filesize']],
    ];

    $errors = file_validate($managed_file, $validators);
    if ($managed_file && empty(file_validate($managed_file, $validators))) {
      return ['target_id' => $managed_file->id()];
    }
    else {
      // Todo: Verify file garbage collection.
      foreach ($errors as $error) {
        $this->detailLog
          ->log('File upload error for user image with validation error @error',
            ['@error' => $error]
          );
      }

      return FALSE;
    }
  }

  /**
   * TODO: Remove redundancy in LdapConfiguration.
   */
  private function provisionsLdapEntriesFromDrupalUsers() {
    if ($this->config->get('ldapEntryProvisionServer') &&
      count(array_filter(array_values($this->config->get('ldapEntryProvisionTriggers')))) > 0) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Return context to provision direction.
   *
   * Converts the more general ldap_context string to its associated LDAP user
   * prov direction.
   *
   * @param string|null $ldapContext
   *   The relevant context.
   *
   * @return string
   *   The provisioning direction.
   */
  private function ldapContextToProvDirection($ldapContext = NULL) {

    switch ($ldapContext) {
      case 'ldap_user_prov_to_drupal':
        $result = self::PROVISION_TO_DRUPAL;
        break;

      case 'ldap_user_prov_to_ldap':
      case 'ldap_user_delete_drupal_user':
        $result = self::PROVISION_TO_LDAP;
        break;

      // Provisioning is can happen in both directions in most contexts.
      case 'ldap_user_insert_drupal_user':
      case 'ldap_user_update_drupal_user':
      case 'ldap_authentication_authenticate':
      case 'ldap_user_disable_drupal_user':
        $result = self::PROVISION_TO_ALL;
        break;

      default:
        $result = self::PROVISION_TO_ALL;
        break;
    }
    return $result;
  }

  /**
   * Handle account deletion with LDAP entry provisioning.
   *
   * @param \Drupal\user\UserInterface $account
   *   Drupal account.
   */
  private function deleteProvisionedLdapEntry(UserInterface $account) {
    if ($this->provisionsLdapEntriesFromDrupalUsers()
      && LdapConfiguration::provisionAvailableToLdap(self::PROVISION_LDAP_ENTRY_ON_USER_ON_USER_DELETE)
    ) {
      $ldapProcessor = new LdapUserProcessor();
      $ldapProcessor->deleteProvisionedLdapEntries($account);
    }
  }

  /**
   * Handle account login with LDAP entry provisioning.
   */
  private function loginLdapEntryProvisioning() {
    if ($this->provisionsLdapEntriesFromDrupalUsers()
      && LdapConfiguration::provisionAvailableToLdap(self::PROVISION_LDAP_ENTRY_ON_USER_ON_USER_AUTHENTICATION)) {
      $ldapUserProcessor = new LdapUserProcessor();

      // Provision entry.
      if (SemaphoreStorage::get('provision', $this->account->getAccountName()) == FALSE
      && !$ldapUserProcessor->getProvisionRelatedLdapEntry($this->account)) {
        $provisionResult = $ldapUserProcessor->provisionLdapEntry($this->account);
        if ($provisionResult['status'] == 'success') {
          SemaphoreStorage::set('provision', $this->account->getAccountName());
        }
      }

      // Sync entry if not just provisioned.
      if (SemaphoreStorage::get('provision', $this->account->getAccountName()) == FALSE
        && SemaphoreStorage::get('sync', $this->account->getAccountName()) == FALSE) {
        $result = $ldapUserProcessor->syncToLdapEntry($this->account);
        if ($result) {
          SemaphoreStorage::set('sync', $this->account->getAccountName());
        }
      }
    }
  }

  /**
   * Handle account login with Drupal provisioning.
   */
  private function loginDrupalAccountProvisioning() {
    if (LdapConfiguration::provisionsDrupalAccountsFromLdap()
      && in_array(self::EVENT_SYNC_TO_DRUPAL_USER, array_keys(LdapConfiguration::provisionsDrupalEvents()))) {
      $ldap_user = $this->factory->getUserDataFromServerByAccount($this->account, $this->config->get('drupalAcctProvisionServer'), 'ldap_user_prov_to_drupal');
      if ($ldap_user) {
        $this->server = $this->factory->getServerById($this->config->get('drupalAcctProvisionServer'));
        $this->applyAttributesToAccount($ldap_user, self::PROVISION_TO_DRUPAL, [self::EVENT_SYNC_TO_DRUPAL_USER]);
      }
      $this->saveAccount();
    }
  }

  /**
   * Handle the user update/create event with LDAP entry provisioning.
   */
  private function provisionLdapEntryOnUserUpdateCreateEvent() {
    if (SemaphoreStorage::get('provision', $this->account->getAccountName())
     || SemaphoreStorage::get('sync', $this->account->getAccountName())) {
      return;
    }

    $processor = new LdapUserProcessor();
    // Check if provisioning to LDAP has already occurred this page load.
    if (!$processor->getProvisionRelatedLdapEntry($this->account)) {
      $provisionResult = $processor->provisionLdapEntry($this->account);
      if ($provisionResult['status'] == 'success') {
        SemaphoreStorage::set('provision', $this->account->getAccountName());
      }
    }

    // Sync if not just provisioned and enabled.
    if (SemaphoreStorage::get('provision', $this->account->getAccountName()) == FALSE) {
      // Check if provisioning to LDAP has already occurred this page load.
      if (LdapConfiguration::provisionAvailableToLdap(self::PROVISION_LDAP_ENTRY_ON_USER_ON_USER_UPDATE_CREATE)
        && $processor->getProvisionRelatedLdapEntry($this->account)) {
        $ldapProcessor = new LdapUserProcessor();
        if ($ldapProcessor->syncToLdapEntry($this->account)) {
          SemaphoreStorage::set('sync', $this->account->getAccountName());
        }
      }
    }
  }

  /**
   * Saves the account, separated to make this testable.
   */
  private function saveAccount() {
    $this->account->save();
  }

  /**
   * Apply field values to user account.
   *
   * One should not assume all attributes are present in the LDAP entry.
   *
   * @param array $ldapUser
   *   LDAP entry.
   * @param string $direction
   *   The provisioning direction.
   * @param array $prov_events
   *   The provisioning events.
   */
  private function applyAttributesToAccount(array $ldapUser, $direction = NULL, array $prov_events = NULL) {
    if ($direction == NULL) {
      $direction = self::PROVISION_TO_DRUPAL;
    }
    if (!$prov_events) {
      $prov_events = LdapConfiguration::getAllEvents();
    }

    $this->setLdapBaseFields($ldapUser, $direction, $prov_events);

    if ($direction == self::PROVISION_TO_DRUPAL && in_array(self::EVENT_CREATE_DRUPAL_USER, $prov_events)) {
      // If empty, set initial mail, status active, generate a random password.
      $this->setFieldsOnDrupalUserCreation($ldapUser);
    }

    $this->setUserDefinedMappings($ldapUser, $direction, $prov_events);

    $context = ['ldap_server' => $this->server, 'prov_events' => $prov_events];
    \Drupal::moduleHandler()->alter('ldap_user_edit_user', $this->account, $ldapUser, $context);

    // Set ldap_user_last_checked.
    $this->account->set('ldap_user_last_checked', time());
  }

  /**
   * For a Drupal account, query LDAP, get all user fields and save.
   *
   * @param int $provisioningEvent
   *   The provisioning event.
   * @param array $ldapUser
   *   A user's LDAP entry. Passed to avoid re-querying LDAP in cases where
   *   already present.
   *
   * @return bool
   *   Attempts to sync, reports failure if unsuccessful.
   */
  private function syncToDrupalAccount($provisioningEvent = NULL, array $ldapUser = NULL) {
    if ($provisioningEvent == NULL) {
      $provisioningEvent = self::EVENT_SYNC_TO_DRUPAL_USER;
    }

    if ((!$ldapUser && !method_exists($this->account, 'getAccountName')) || (!$this->account)) {
      \Drupal::logger('ldap_user')
        ->notice('Invalid selection passed to syncToDrupalAccount.');
      return FALSE;
    }

    if (!$ldapUser && $this->config->get('drupalAcctProvisionServer')) {
      $ldapUser = $this->factory->getUserDataFromServerByAccount($this->account, $this->config->get('drupalAcctProvisionServer'), 'ldap_user_prov_to_drupal');
    }

    if (!$ldapUser) {
      return FALSE;
    }

    if ($this->config->get('drupalAcctProvisionServer')) {
      $this->server = Server::load($this->config->get('drupalAcctProvisionServer'));
      $this->applyAttributesToAccount($ldapUser, self::PROVISION_TO_DRUPAL, [$provisioningEvent]);
    }

    $this->saveAccount();
    return TRUE;
  }

  /**
   * Handle LDAP entry provision on user creation.
   *
   * @param \Drupal\user\UserInterface $account
   *   The Drupal user account.
   */
  private function provisionLdapEntryOnUserCreation(UserInterface $account) {
    if ($this->provisionsLdapEntriesFromDrupalUsers()) {
      $processor = new LdapUserProcessor();
      if (LdapConfiguration::provisionAvailableToLdap(self::PROVISION_LDAP_ENTRY_ON_USER_ON_USER_UPDATE_CREATE)) {
        if (!$processor->getProvisionRelatedLdapEntry($account)) {
          $provision_result = $processor->provisionLdapEntry($account);
          if ($provision_result['status'] == 'success') {
            SemaphoreStorage::set('provision', $account->getAccountName());
          }
        }
        else {
          if ($processor->syncToLdapEntry($account)) {
            SemaphoreStorage::set('sync', $account->getAccountName());
          }
        }
      }
    }
  }

  /**
   * Determine if this a user registration process.
   *
   * @param \Drupal\user\UserInterface $account
   *   Drupal user account.
   *
   * @return bool
   *   It is a registration process.
   */
  private function newAccountRequest(UserInterface $account) {
    if (\Drupal::currentUser()->isAnonymous() && $account->isNew()) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Sets the fields for initial users.
   *
   * @param array $ldapUser
   *   Ldap user data.
   */
  private function setFieldsOnDrupalUserCreation(array $ldapUser) {
    $derived_mail = $this->server->userEmailFromLdapEntry($ldapUser['attr']);
    if (!$this->account->getEmail()) {
      $this->account->set('mail', $derived_mail);
    }
    if (!$this->account->getPassword()) {
      $this->account->set('pass', user_password(20));
    }
    if (!$this->account->getInitialEmail()) {
      $this->account->set('init', $derived_mail);
    }
    if (!$this->account->isBlocked()) {
      $this->account->set('status', 1);
    }
  }

  /**
   * Sets the fields required by LDAP.
   *
   * @param array $ldapUser
   *   Ldap user data.
   * @param string $direction
   *   Provision direction.
   * @param array $prov_events
   *   Provisioning event.
   */
  private function setLdapBaseFields(array $ldapUser, $direction, array $prov_events) {
    // Basic $user LDAP fields.
    $mappingHelper = new SyncMappingHelper();

    if ($mappingHelper->isSynced('[property.name]', $prov_events, $direction)) {
      $this->account->set('name', $this->server->userUsernameFromLdapEntry($ldapUser['attr']));
    }

    if ($mappingHelper->isSynced('[property.mail]', $prov_events, $direction)) {
      $derived_mail = $this->server->userEmailFromLdapEntry($ldapUser['attr']);
      if ($derived_mail) {
        $this->account->set('mail', $derived_mail);
      }
    }

    if ($mappingHelper->isSynced('[property.picture]', $prov_events, $direction)) {
      $picture = $this->userPictureFromLdapEntry($ldapUser['attr']);
      if ($picture) {
        $this->account->set('user_picture', $picture);
      }
    }

    if ($mappingHelper->isSynced('[field.ldap_user_puid]', $prov_events, $direction)) {
      $ldap_user_puid = $this->server->userPuidFromLdapEntry($ldapUser['attr']);
      if ($ldap_user_puid) {
        $this->account->set('ldap_user_puid', $ldap_user_puid);
      }
    }
    if ($mappingHelper->isSynced('[field.ldap_user_puid_property]', $prov_events, $direction)) {
      $this->account->set('ldap_user_puid_property', $this->server->get('unique_persistent_attr'));
    }
    if ($mappingHelper->isSynced('[field.ldap_user_puid_sid]', $prov_events, $direction)) {
      $this->account->set('ldap_user_puid_sid', $this->server->id());
    }
    if ($mappingHelper->isSynced('[field.ldap_user_current_dn]', $prov_events, $direction)) {
      $this->account->set('ldap_user_current_dn', $ldapUser['dn']);
    }
  }

  /**
   * Sets the additional, user-defined fields.
   *
   * The greyed out user mappings are not passed to this function.
   *
   * @param array $ldapUser
   *   Ldap user data.
   * @param string $direction
   *   Provision direction.
   * @param array $prov_events
   *   Provisioning event.
   */
  private function setUserDefinedMappings(array $ldapUser, $direction, array $prov_events) {
    $mappingHelper = new SyncMappingHelper();
    // Get any additional mappings.
    $mappings = $mappingHelper->getSyncMappings($direction, $prov_events);

    // Loop over the mappings.
    foreach ($mappings as $key => $fieldDetails) {
      // Make sure this mapping is relevant to the sync context.
      if ($mappingHelper->isSynced($key, $prov_events, $direction)) {
        // If "convert from binary is selected" and no particular method is in
        // token default to binaryConversionToString() function.
        if ($fieldDetails['convert'] && strpos($fieldDetails['ldap_attr'], ';') === FALSE) {
          $fieldDetails['ldap_attr'] = str_replace(']', ';binary]', $fieldDetails['ldap_attr']);
        }
        $value = $this->tokenProcessor->tokenReplace($ldapUser['attr'], $fieldDetails['ldap_attr'], 'ldap_entry');
        // The ordinal $value_instance is not used and could probably be
        // removed.
        list($value_type, $value_name, $value_instance) = $this->parseUserAttributeNames($key);

        if ($value_type == 'field' || $value_type == 'property') {
          $this->account->set($value_name, $value);
        }
      }
    }
  }

  /**
   * Parse user attribute names.
   *
   * @param string $user_attr_key
   *   A string in the form of <attr_type>.<attr_name>[:<instance>] such as
   *   field.lname, property.mail, field.aliases:2.
   *
   * @return array
   *   An array such as array('field','field_user_lname', NULL).
   */
  private function parseUserAttributeNames($user_attr_key) {
    // Make sure no [] are on attribute.
    $user_attr_key = trim($user_attr_key, TokenProcessor::PREFIX . TokenProcessor::SUFFIX);
    $parts = explode('.', $user_attr_key);
    $attr_type = $parts[0];
    $attr_name = (isset($parts[1])) ? $parts[1] : FALSE;
    $attr_ordinal = FALSE;

    if ($attr_name) {
      $attr_name_parts = explode(':', $attr_name);
      if (isset($attr_name_parts[1])) {
        $attr_name = $attr_name_parts[0];
        $attr_ordinal = $attr_name_parts[1];
      }
    }
    return [$attr_type, $attr_name, $attr_ordinal];
  }

}
