<?php

declare(strict_types = 1);

namespace Drupal\ldap_user\Processor;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\State\StateInterface;
use Drupal\externalauth\Authmap;
use Drupal\ldap_query\Controller\QueryController;
use Drupal\ldap_servers\Logger\LdapDetailLog;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Ldap\Entry;

/**
 * Provides functionality to generically update existing users.
 */
class GroupUserUpdateProcessor {

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Detail log.
   *
   * @var \Drupal\ldap_servers\Logger\LdapDetailLog
   */
  protected $detailLog;

  /**
   * Config.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * State.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Externalauth.
   *
   * @var \Drupal\externalauth\Authmap
   */
  protected $externalAuth;

  /**
   * Query controller.
   *
   * @var \Drupal\ldap_query\Controller\QueryController
   */
  protected $queryController;

  /**
   * Drupal user processor.
   *
   * @var \Drupal\ldap_user\Processor\DrupalUserProcessor
   */
  protected $drupalUserProcessor;

  /**
   * LDAP Server.
   *
   * @var \Drupal\ldap_servers\Entity\Server|null
   */
  protected $ldapServer;

  /**
   * User storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $userStorage;

  /**
   * Constructor for update process.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   * @param \Drupal\ldap_servers\Logger\LdapDetailLog $detail_log
   *   Detail log.
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   Config factory.
   * @param \Drupal\Core\State\StateInterface $state
   *   State.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   Module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\externalauth\Authmap $external_auth
   *   Externalauth.
   * @param \Drupal\ldap_query\Controller\QueryController $query_controller
   *   Query controller.
   * @param \Drupal\ldap_user\Processor\DrupalUserProcessor $drupal_user_processor
   *   Drupal user processor.
   */
  public function __construct(
    LoggerInterface $logger,
    LdapDetailLog $detail_log,
    ConfigFactory $config,
    StateInterface $state,
    ModuleHandler $module_handler,
    EntityTypeManagerInterface $entity_type_manager,
    Authmap $external_auth,
    QueryController $query_controller,
    DrupalUserProcessor $drupal_user_processor) {
    $this->logger = $logger;
    $this->detailLog = $detail_log;
    $this->config = $config->get('ldap_user.settings');
    $this->drupalUserProcessor = $drupal_user_processor;
    $this->state = $state;
    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->externalAuth = $external_auth;
    $this->queryController = $query_controller;

    $this->ldapServer = $this->entityTypeManager
      ->getStorage('ldap_server')
      ->load($this->config->get('drupalAcctProvisionServer'));
    $this->userStorage = $this->entityTypeManager
      ->getStorage('user');
  }

  /**
   * Check if the query is valid.
   *
   * @return bool
   *   Query valid.
   */
  protected function constraintsValid(): bool {
    if (!$this->queryController) {
      $this->logger->error('Configured query for update mechanism cannot be loaded.');
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Check whether updates are due.
   *
   * @return bool
   *   Whether to update.
   */
  public function updateDue(): bool {
    $lastRun = $this->state->get('ldap_user_cron_last_group_user_update', 1);
    $result = FALSE;
    switch ($this->config->get('userUpdateCronInterval')) {
      case 'always':
        $result = TRUE;
        break;

      case 'daily':
        $result = strtotime('today -1 day') - $lastRun >= 0;
        break;

      case 'weekly':
        $result = strtotime('today -7 day') - $lastRun >= 0;
        break;

      case 'monthly':
        $result = strtotime('today -30 day') - $lastRun >= 0;
        break;
    }
    return $result;
  }

  /**
   * Update authorizations.
   *
   * @param \Drupal\user\UserInterface $user
   *   Drupal user to update.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function updateAuthorizations(UserInterface $user): void {
    if ($this->moduleHandler->moduleExists('ldap_authorization')) {
      // We are not injecting this service properly to avoid forcing this
      // dependency on authorization.
      /** @var \Drupal\authorization\AuthorizationController $authorization_manager */
      // phpcs:ignore
      $authorization_manager = \Drupal::service('authorization.manager');
      $authorization_manager->setUser($user);
      $authorization_manager->setAllProfiles();
    }
    else {
      // We are saving here for sites without ldap_authorization since saving is
      // embedded in setAllProfiles().
      // @todo Provide method for decoupling saving users and use it instead.
      $user->save();
    }
  }

  /**
   * Runs the updating mechanism.
   *
   * @param string $id
   *   LDAP QueryEntity ID.
   */
  public function runQuery(string $id): void {

    $this->queryController->load($id);
    if (!$this->constraintsValid()) {
      return;
    }

    $this->queryController->execute();
    $entries = $this->queryController->getRawResults();
    $attribute = $this->ldapServer->getAuthenticationNameAttribute();

    if (empty($attribute)) {
      $this->logger->error('No authentication name attribute set for periodic update.');
      return;
    }

    $this->logger->notice(
      'Processing @count accounts for periodic update.',
        ['@count' => count($entries)]
      );

    foreach ($entries as $entry) {
      $this->processAccount($entry, $attribute);
    }
    $this->state->set('ldap_user_cron_last_group_user_update', strtotime('today'));
  }

  /**
   * Create or update an entry in Drupal.
   *
   * @param \Symfony\Component\Ldap\Entry $entry
   *   LDAP entry.
   * @param string $attribute
   *   Authname attribute.
   */
  protected function processAccount(Entry $entry, string $attribute): void {
    if (!$entry->hasAttribute($attribute, FALSE)) {
      // Missing authname attribute.
      $this->detailLog->log(
        'DN @dn missing authentication name.',
        ['@dn' => $entry->getDn()],
        'ldap_user'
      );
      return;
    }
    $username = $entry->getAttribute($attribute, FALSE)[0];

    // Make sure nothing from the previous request can interact on login.
    $this->drupalUserProcessor->reset();

    $uid = $this->externalAuth->getUid($username, 'ldap_user');
    if (!$uid) {
      $result = $this->drupalUserProcessor->createDrupalUserFromLdapEntry(
        [
          'name' => $username,
          'status' => TRUE,
        ]
      );
      if ($result) {
        $this->detailLog->log(
          'Periodic update: @name created',
          ['@name' => $username],
          'ldap_user'
        );
        $uid = $this->externalAuth->getUid($username, 'ldap_user');
      }
      else {
        $this->logger->error(
          'Periodic update: Error creating user @name',
          ['@name' => $username]
        );
        return;
      }
    }

    // User exists and is mapped in authmap.
    /** @var \Drupal\user\Entity\User $drupal_account */
    $drupal_account = $this->userStorage->load($uid);
    $this->drupalUserProcessor->drupalUserLogsIn($drupal_account);
    // Reload since data has changed.
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->userStorage->load($drupal_account->id());
    $this->updateAuthorizations($user);
    $this->detailLog->log(
      'Periodic update: @name updated',
      ['@name' => $username],
      'ldap_user'
    );
  }

}
