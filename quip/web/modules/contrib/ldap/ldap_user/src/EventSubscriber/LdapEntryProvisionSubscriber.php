<?php

declare(strict_types = 1);

namespace Drupal\ldap_user\EventSubscriber;

use Drupal\ldap_servers\LdapTransformationTraits;
use Drupal\ldap_user\Event\LdapNewUserCreatedEvent;
use Drupal\ldap_user\Event\LdapUserLoginEvent;
use Drupal\ldap_user\Event\LdapUserUpdatedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Ldap\Entry;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\ldap_servers\Helper\ConversionHelper;
use Drupal\ldap_servers\Helper\CredentialsStorage;
use Drupal\ldap_servers\LdapUserAttributesInterface;
use Drupal\ldap_servers\LdapUserManager;
use Drupal\ldap_servers\Logger\LdapDetailLog;
use Drupal\ldap_user\Exception\LdapBadParamsException;
use Drupal\ldap_user\FieldProvider;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscribers for creating and updating LDAP entries.
 */
class LdapEntryProvisionSubscriber implements EventSubscriberInterface, LdapUserAttributesInterface {

  use LdapTransformationTraits;

  /**
   * Config.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * Detail log.
   *
   * @var \Drupal\ldap_servers\Logger\LdapDetailLog
   */
  private $detailLog;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * Ldap User Manager.
   *
   * @var \Drupal\ldap_servers\LdapUserManager
   */
  private $ldapUserManager;


  /**
   * Field provider.
   *
   * @var \Drupal\ldap_user\FieldProvider
   */
  private $fieldProvider;

  /**
   * File system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  private $fileSystem;

  /**
   * Server.
   *
   * @var \Drupal\ldap_servers\Entity\Server
   */
  private $ldapServer;

  /**
   * Available tokens.
   *
   * @var array
   *
   * Keys for tokens:
   *   'all' signifies return all token/value pairs available;
   *   otherwise array lists token keys. For example:
   *   property.name ... *not* [property.name].
   */
  private $tokens;

  /**
   * User.
   *
   * @var \Drupal\user\Entity\User|\Drupal\user\UserInterface
   */
  private $account;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   Config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   * @param \Drupal\ldap_servers\Logger\LdapDetailLog $detail_log
   *   Detail log.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler.
   * @param \Drupal\ldap_servers\LdapUserManager $ldap_user_manager
   *   LDAP user manager.
   * @param \Drupal\ldap_user\FieldProvider $field_provider
   *   Field Provider.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   File system.
   */
  public function __construct(
    ConfigFactory $config_factory,
    LoggerInterface $logger,
    LdapDetailLog $detail_log,
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandlerInterface $module_handler,
    LdapUserManager $ldap_user_manager,
    FieldProvider $field_provider,
    FileSystemInterface $file_system) {
    $this->config = $config_factory->get('ldap_user.settings');
    $this->logger = $logger;
    $this->detailLog = $detail_log;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->ldapUserManager = $ldap_user_manager;
    $this->fieldProvider = $field_provider;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[LdapUserLoginEvent::EVENT_NAME] = ['login'];
    $events[LdapNewUserCreatedEvent::EVENT_NAME] = ['userCreated'];
    $events[LdapUserUpdatedEvent::EVENT_NAME] = ['userUpdated'];
    return $events;
  }

  /**
   * Handle account login with LDAP entry provisioning.
   *
   * @param \Drupal\ldap_user\Event\LdapUserLoginEvent $event
   *   Event.
   */
  public function login(LdapUserLoginEvent $event): void {
    $this->account = $event->account;
    $triggers = $this->config->get('ldapEntryProvisionTriggers');
    if (
      $this->provisionLdapEntriesFromDrupalUsers() &&
      \in_array(self::PROVISION_LDAP_ENTRY_ON_USER_ON_USER_AUTHENTICATION, $triggers, TRUE) &&
      $this->account->get('ldap_user_ldap_exclude')->getString() !== '1'
    ) {
      $this->loadServer();
      if ($this->checkExistingLdapEntry()) {
        $this->syncToLdapEntry();
      }
      else {
        // This should only be necessary if the entry was deleted on the
        // directory server.
        $this->provisionLdapEntry();
      }
    }
  }

  /**
   * Create or update LDAP entries on user update.
   *
   * @param \Drupal\ldap_user\Event\LdapUserUpdatedEvent $event
   *   Event.
   */
  public function userUpdated(LdapUserUpdatedEvent $event): void {
    $this->account = $event->account;
    if (
      $this->provisionLdapEntriesFromDrupalUsers() &&
      \in_array(self::PROVISION_LDAP_ENTRY_ON_USER_ON_USER_UPDATE_CREATE, $this->config->get('ldapEntryProvisionTriggers'), TRUE) &&
      $this->account->get('ldap_user_ldap_exclude')->getString() !== '1'
    ) {
      $this->loadServer();
      if ($this->checkExistingLdapEntry()) {
        $this->syncToLdapEntry();
      }
      else {
        // This should only be necessary if the entry was deleted on the
        // directory server.
        $this->provisionLdapEntry();
      }
    }
  }

  /**
   * Create or update LDAP entries on user creation.
   *
   * @param \Drupal\ldap_user\Event\LdapNewUserCreatedEvent $event
   *   Event.
   */
  public function userCreated(LdapNewUserCreatedEvent $event): void {
    $this->account = $event->account;
    if (
      $this->provisionLdapEntriesFromDrupalUsers() &&
      \in_array(self::PROVISION_LDAP_ENTRY_ON_USER_ON_USER_UPDATE_CREATE, $this->config->get('ldapEntryProvisionTriggers'), TRUE) &&
      $this->account->get('ldap_user_ldap_exclude')->getString() !== '1'
    ) {
      $this->loadServer();
      if ($this->checkExistingLdapEntry()) {
        $this->syncToLdapEntry();
      }
      else {
        $this->provisionLdapEntry();
      }
    }
  }

  /**
   * Is provisioning of LDAP entries from Drupal users configured.
   *
   * @return bool
   *   Provisioning available.
   */
  private function provisionLdapEntriesFromDrupalUsers(): bool {
    return $this->config->get('ldapEntryProvisionServer') &&
      count(array_filter(array_values($this->config->get('ldapEntryProvisionTriggers')))) > 0;
  }

  /**
   * Populate LDAP entry array for provisioning.
   *
   * @param string $prov_event
   *   Provisioning event.
   *
   * @return \Symfony\Component\Ldap\Entry
   *   Entry to send *to* LDAP.
   */
  private function buildLdapEntry(string $prov_event): Entry {
    $dn = '';
    $attributes = [];

    if (!is_object($this->account) || !is_object($this->ldapServer)) {
      throw new LdapBadParamsException('Missing user or server.');
    }

    $this->fieldProvider->loadAttributes(self::PROVISION_TO_LDAP, $this->ldapServer);

    $mappings = $this->fieldProvider->getAttributesSyncedOnEvent($prov_event);

    foreach ($mappings as $field) {
      // @todo Trimming here shows that we should not be saving the brackets to
      // the database.
      $ldap_attribute_name = trim($field->getLdapAttribute(), '[]');

      $attribute = $field->getDrupalAttribute() === 'user_tokens' ? $field->getUserTokens() : $field->getDrupalAttribute();
      $value = $this->fetchDrupalAttributeValue($attribute, $ldap_attribute_name);

      if ($value) {
        if ($ldap_attribute_name === 'dn') {
          $dn = $value;
        }
        else {
          $attributes[$ldap_attribute_name][] = $value;
        }
      }
    }

    $entry = new Entry($dn, $attributes);

    // Allow other modules to alter $ldap_user.
    $params = [
      'prov_events' => $prov_event,
      'direction' => self::PROVISION_TO_LDAP,
    ];
    $this->moduleHandler
      ->alter('ldap_entry', $entry, $params);

    return $entry;
  }

  /**
   * Fetch a single token.
   *
   * @param string $token
   *   Token key.
   */
  private function fetchDrupalAccountAttribute(string $token): void {
    // Trailing period to allow for empty value.
    [
      $attribute_type,
      $attribute_name,
      $attribute_conversion,
    ] = explode('.', $token . '.');
    $value = NULL;

    if ($attribute_type === 'field' || $attribute_type === 'property') {
      $value = $this->fetchDrupalAccountField($attribute_name);
    }
    elseif ($attribute_type === 'password') {
      $value = $this->fetchDrupalAccountPassword($attribute_name);
      if (empty($value)) {
        // Do not evaluate empty passwords, to avoid overwriting them.
        return;
      }
    }

    if ($attribute_conversion === 'to-md5') {
      $value = md5($value);
    }
    elseif ($attribute_conversion === 'to-lowercase') {
      $value = mb_strtolower($value);
    }

    $this->tokens[sprintf('[%s]', $token)] = $value;
  }

  /**
   * Fetch regular field token.
   *
   * @param string $attribute_name
   *   Field name.
   *
   * @return string
   *   Field data.
   */
  private function fetchDrupalAccountField(string $attribute_name): string {
    $value = '';
    if (is_scalar($this->account->get($attribute_name)->value)) {
      $value = $this->account->get($attribute_name)->value;
    }
    elseif (!empty($this->account->get($attribute_name)->getValue())) {
      $file_reference = $this->account->get($attribute_name)->getValue();
      if (isset($file_reference[0]['target_id'])) {
        /** @var \Drupal\file\Entity\File $file */
        $file = $this->entityTypeManager
          ->getStorage('file')
          ->load($file_reference[0]['target_id']);
        if ($file) {
          $value = file_get_contents($this->fileSystem->realpath($file->getFileUri()));
        }
      }
    }
    return $value;
  }

  /**
   * Fetch the password token.
   *
   * @param string $attribute_name
   *   Field variant.
   *
   * @return string
   *   Password.
   */
  private function fetchDrupalAccountPassword(string $attribute_name): string {
    $value = '';
    switch ($attribute_name) {

      case 'user':
      case 'user-only':
        $value = CredentialsStorage::getPassword();
        break;

      case 'user-random':
        $pwd = CredentialsStorage::getPassword();
        if (version_compare(\Drupal::VERSION, '9.1', '>=')) {
          $generated = \Drupal::service('password_generator')->generate();
        }
        else {
          $generated = user_password();
        }
        $value = $pwd ?: $generated;
        break;

      case 'random':
        if (version_compare(\Drupal::VERSION, '9.1', '>=')) {
          $value = \Drupal::service('password_generator')->generate();
        }
        else {
          $value = user_password();
        }
        break;

    }
    return $value;
  }

  /**
   * Replace a single token.
   *
   * @param string $text
   *   The text such as "[dn]", "[cn]@my.org", "[displayName] [sn]",
   *   "Drupal Provisioned".
   * @param string $type
   *   Type.
   *
   * @return string|null
   *   Attribute value.
   *
   * @see \Drupal\ldap_servers\Processor\TokenProcessor::ldapEntryReplacementsForDrupalAccount()
   */
  private function fetchDrupalAttributeValue(string $text, string $type): ?string {
    // Desired tokens are of form "cn","mail", etc.
    $desired_tokens = ConversionHelper::findTokensNeededForTemplate($text);

    if (empty($desired_tokens)) {
      // If no tokens exist in text, return text itself.
      return $text;
    }

    foreach ($desired_tokens as $desired_token) {
      $this->fetchDrupalAccountAttribute($desired_token);
    }
    // This is inelegant but otherwise we cannot support compound tokens for DN.
    if ($type === 'dn') {
      foreach ($this->tokens as $key => $value) {
        $this->tokens[$key] = $value;
      }
    }

    // @todo Not a great solution.
    // We are adding those lowercase duplicates to make sure we can
    // replace all placeholders independent of their case. Note that as a
    // workaround we are lowercasing those on form saving for now.
    foreach ($this->tokens as $attribute => $value) {
      $this->tokens[mb_strtolower($attribute)] = $value;
    }

    $attributes = array_keys($this->tokens);
    $values = array_values($this->tokens);
    $result = str_replace($attributes, $values, $text);

    // Strip out any un-replaced tokens.
    $result = preg_replace('/^\[.*\]$/', '', $result);

    if ($result === '') {
      $result = NULL;
    }
    return $result;
  }

  /**
   * Load provisioning server from database.
   */
  private function loadServer(): void {
    $this->ldapServer = $this->entityTypeManager
      ->getStorage('ldap_server')
      ->load($this->config->get('ldapEntryProvisionServer'));
    $this->ldapUserManager->setServer($this->ldapServer);
  }

  /**
   * Provision an LDAP entry if none exists.
   *
   * If one exists do nothing, takes Drupal user as argument.
   *
   * @return bool
   *   Provisioning successful.
   */
  private function provisionLdapEntry(): bool {

    if ($this->account->isAnonymous()) {
      $this->logger->notice('Cannot provision LDAP user unless corresponding Drupal account exists.');
      return FALSE;
    }

    if (!$this->config->get('ldapEntryProvisionServer')) {
      $this->logger->error('No provisioning server enabled');
      return FALSE;
    }

    try {
      $entry = $this->buildLdapEntry(self::EVENT_CREATE_LDAP_ENTRY);
    }
    catch (\Exception $e) {
      $this->logger->error('User or server is missing during LDAP provisioning: %message', [
        '%message',
        $e->getMessage(),
      ]);
      return FALSE;
    }

    if (empty($entry->getDn())) {
      $this->detailLog->log('Failed to derive DN.', [], 'ldap_user');
      return FALSE;
    }

    if (empty($entry->getAttributes())) {
      $this->detailLog->log('No attributes defined in mappings.', [], 'ldap_user');
      return FALSE;
    }

    // Stick $proposedLdapEntry in $ldapEntries array for drupal_alter.
    $context = [
      'action' => 'add',
      'corresponding_drupal_data_type' => 'user',
      'account' => $this->account,
    ];
    $this->moduleHandler->alter('ldap_entry_pre_provision', $entry, $this->ldapServer, $context);
    if ($this->ldapUserManager->createLdapEntry($entry)) {
      $callback_params = [$entry, $this->ldapServer, $context];
      $this->moduleHandler->invokeAll('ldap_entry_post_provision', $callback_params);
      $this->updateUserProvisioningReferences($entry);

    }
    else {
      $this->logger->error('LDAP entry for @username cannot be created on @sid. Proposed DN: %dn)',
        [
          '%dn' => $entry->getDn(),
          '@sid' => $this->ldapServer->id(),
          '@username' => $this->account ? $this->account->getAccountName() : 'Missing',
        ]);
      return FALSE;
    }

    $this->detailLog->log(
      'LDAP entry for @username on server @sid created for DN %dn.',
      [
        '%dn' => $entry->getDn(),
        '@sid' => $this->ldapServer->id(),
        '@username' => $this->account ? $this->account->getAccountName() : 'Missing',
      ],
      'ldap_user'
    );

    return TRUE;
  }

  /**
   * Save provisioning entries to database.
   *
   * Need to store <sid>|<dn> in ldap_user_prov_entries field, which may
   *  contain more than one.
   *
   * @param \Symfony\Component\Ldap\Entry $entry
   *   LDAP Entry.
   */
  private function updateUserProvisioningReferences(Entry $entry): void {
    $ldap_user_prov_entry = $this->ldapServer->id() . '|' . $entry->getDn();
    if ($this->account->get('ldap_user_prov_entries') !== NULL) {
      $this->account->set('ldap_user_prov_entries', []);
    }
    $ldap_user_provisioning_entry_exists = FALSE;
    if ($this->account->get('ldap_user_prov_entries')->value) {
      foreach ($this->account->get('ldap_user_prov_entries')->value as $field_value_instance) {
        if ($field_value_instance === $ldap_user_prov_entry) {
          $ldap_user_provisioning_entry_exists = TRUE;
        }
      }
    }
    if (!$ldap_user_provisioning_entry_exists) {
      $prov_entries = $this->account->get('ldap_user_prov_entries')->value;
      $prov_entries[] = [
        'value' => $ldap_user_prov_entry,
        'format' => NULL,
        'save_value' => $ldap_user_prov_entry,
      ];
      $this->account->set('ldap_user_prov_entries', $prov_entries);
      $this->account->save();
    }
  }

  /**
   * Given a Drupal account, sync to related LDAP entry.
   */
  private function syncToLdapEntry(): void {
    if (!$this->config->get('ldapEntryProvisionServer')) {
      $this->logger->error('Provisioning server not available');
      return;
    }

    try {
      $entry = $this->buildLdapEntry(self::EVENT_SYNC_TO_LDAP_ENTRY);
    }
    catch (\Exception $e) {
      $this->logger->error('Unable to prepare LDAP entry: %message', [
        '%message',
        $e->getMessage(),
      ]);
      return;
    }

    if (!empty($entry->getDn())) {
      // Stick $proposedLdapEntry in $ldap_entries array for drupal_alter.
      $context = [
        'action' => 'update',
        'corresponding_drupal_data_type' => 'user',
        'account' => $this->account,
      ];
      $this->moduleHandler->alter('ldap_entry_pre_provision', $entry, $this->ldapServer, $context);
      $this->ldapUserManager->modifyLdapEntry($entry);
      $params = [$entry, $this->ldapServer, $context];
      $this->moduleHandler->invokeAll('ldap_entry_post_provision', $params);
      $tokens = [
        '%dn' => $entry->getDn(),
        '%sid' => $this->ldapServer->id(),
        '%username' => $this->account->getAccountName(),
        '%uid' => (!method_exists($this->account, 'id') || empty($this->account->id())) ? '' : $this->account->id(),
      ];
      $this->logger->info('LDAP entry on server %sid synced dn=%dn for username=%username, uid=%uid', $tokens);
    }
  }

  /**
   * Check existing LDAP entry.
   *
   * @return bool|\Symfony\Component\Ldap\Entry|null
   *   Entry, false or null.
   */
  private function checkExistingLdapEntry() {
    // @todo Inject.
    $authmap = \Drupal::service('externalauth.authmap')
      ->get($this->account->id(), 'ldap_user');
    if ($authmap) {
      return $this->ldapUserManager->queryAllBaseDnLdapForUsername($authmap);
    }
    return FALSE;
  }

}
