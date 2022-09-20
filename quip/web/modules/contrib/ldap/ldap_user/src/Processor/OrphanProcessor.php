<?php

declare(strict_types = 1);

namespace Drupal\ldap_user\Processor;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\ldap_servers\LdapUserManager;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * Locates potential orphan user accounts.
 */
class OrphanProcessor {

  /**
   * List of emails.
   *
   * @var array
   */
  private $emailList = [];

  /**
   * Query Limit.
   *
   * @var int
   */
  private $ldapQueryOrLimit = 30;

  /**
   * Enabled servers.
   *
   * @var \Drupal\ldap_servers\ServerInterface[]
   */
  private $enabledServers;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * LDAP User config.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  protected $configLdapUser;

  /**
   * Mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * State.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * LDAP User Manager.
   *
   * @var \Drupal\ldap_servers\LdapUserManager
   */
  protected $ldapUserManager;

  /**
   * Constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   Config.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   Mail manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Language manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   State.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\ldap_servers\LdapUserManager $ldap_user_manager
   *   LDAP user manager.
   */
  public function __construct(
    LoggerInterface $logger,
    ConfigFactory $config,
    MailManagerInterface $mail_manager,
    LanguageManagerInterface $language_manager,
    StateInterface $state,
    EntityTypeManagerInterface $entity_type_manager,
    LdapUserManager $ldap_user_manager
  ) {
    $this->logger = $logger;
    $this->configFactory = $config;
    $this->configLdapUser = $config->get('ldap_user.settings');
    $this->mailManager = $mail_manager;
    $this->languageManager = $language_manager;
    $this->state = $state;
    $this->entityTypeManager = $entity_type_manager;
    $this->ldapUserManager = $ldap_user_manager;

    $storage = $this->entityTypeManager->getStorage('ldap_server');
    $data = $storage->getQuery()->condition('status', 1)->execute();
    $this->enabledServers = $storage->loadMultiple($data);
  }

  /**
   * Check for Drupal accounts which no longer have a related LDAP entry.
   */
  public function checkOrphans(): void {
    $orphan_policy = $this->configLdapUser->get('orphanedDrupalAcctBehavior');
    if (!$orphan_policy || $orphan_policy === 'ldap_user_orphan_do_not_check') {
      return;
    }

    $uids = $this->fetchUidsToCheck();

    if (!empty($uids)) {
      // To avoid query limits, queries are batched by the limit, for example
      // with 175 users and a limit of 30, 6 batches are run.
      $batches = floor(count($uids) / $this->ldapQueryOrLimit) + 1;
      $batchResults = [];
      for ($batch = 1; $batch <= $batches; $batch++) {
        $batchResults[] = $this->batchQueryUsers($batch, $uids);
      }

      $this->processOrphanedAccounts(array_merge(...$batchResults));

      if (count($this->emailList) > 0) {
        $this->sendOrphanedAccountsMail();
      }
    }
    else {
      // This can happen if you update all users periodically and saving them
      // has caused all 'ldap_user_last_checked' values to be newer than your
      // interval.
      $this->logger->notice('No eligible accounts founds for orphan account verification.');
    }
  }

  /**
   * Create a "binary safe" string for use in LDAP filters.
   *
   * @param string $value
   *   Unsfe string.
   *
   * @return string
   *   Safe string.
   */
  private function binaryFilter(string $value): string {
    $match = '';
    if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $value)) {
      // Reconstruct proper "memory" order from (MS?) GUID string.
      $hex_string = str_replace('-', '', $value);
      $value = substr($hex_string, 6, 2) . substr($hex_string, 4, 2) .
        substr($hex_string, 2, 2) . substr($hex_string, 0, 2) .
        substr($hex_string, 10, 2) . substr($hex_string, 8, 2) .
        substr($hex_string, 14, 2) . substr($hex_string, 12, 2) .
        substr($hex_string, 16, 4) . substr($hex_string, 20, 12);
    }

    $length = \strlen($value);
    for ($i = 0; $i < $length; $i += 2) {
      $match .= '\\' . substr($value, $i, 2);
    }

    return $match;
  }

  /**
   * Send email.
   */
  public function sendOrphanedAccountsMail(): void {
    $to = $this->configLdapUser->get('orphanedDrupalAcctReportingInbox');
    if (empty($to)) {
      $to = $this->configFactory->get('system.site')->get('mail');
    }
    $siteLanguage = $this->languageManager->getCurrentLanguage()->getId();
    $params = ['accounts' => $this->emailList];
    $result = $this->mailManager->mail('ldap_user', 'orphaned_accounts', $to, $siteLanguage, $params);
    if (!$result) {
      $this->logger->error('Could not send orphaned LDAP accounts notification.');
    }
  }

  /**
   * Batch query for users.
   *
   * @param int $batch
   *   Batch number.
   * @param array $uids
   *   UIDs to process.
   *
   * @return array
   *   Queried batch of users.
   */
  private function batchQueryUsers(int $batch, array $uids): array {

    // Creates a list of users in the required format.
    $start = ($batch - 1) * $this->ldapQueryOrLimit;
    $end_plus_1 = min(($batch) * $this->ldapQueryOrLimit, count($uids));
    $batch_uids = \array_slice($uids, $start, ($end_plus_1 - $start));

    $accounts = $this->entityTypeManager
      ->getStorage('user')
      ->loadMultiple($batch_uids);

    $users = [];
    foreach ($accounts as $uid => $user) {
      /** @var \Drupal\user\Entity\User $user */
      $users[] = $this->ldapQueryEligibleUser(
        $uid,
        $user->get('ldap_user_puid_sid')->value,
        $user->get('ldap_user_puid_property')->value,
        $user->get('ldap_user_puid')->value
      );
    }

    return $users;
  }

  /**
   * Fetch UIDs to check.
   *
   * @return array
   *   All relevant UID.
   */
  private function fetchUidsToCheck(): array {
    // We want to query Drupal accounts, which are LDAP associated where a DN
    // is present. The lastUidChecked is used to process only a limited number
    // of batches in the cron run and each user is only checked if the time
    // configured for checking has lapsed.
    $lastUidChecked = $this->state->get('ldap_user_cron_last_uid_checked', 1);

    $query = $this->entityTypeManager->getStorage('user')
      ->getQuery()
      ->accessCheck(FALSE)
      ->exists('ldap_user_current_dn')
      ->exists('ldap_user_puid_property')
      ->exists('ldap_user_puid_sid')
      ->exists('ldap_user_puid')
      ->condition('uid', $lastUidChecked, '>')
      ->condition('status', 1)
      ->sort('uid')
      ->range(0, $this->configLdapUser->get('orphanedCheckQty'));

    $group = $query->orConditionGroup();
    $group->notExists('ldap_user_last_checked');

    switch ($this->configLdapUser->get('orphanedAccountCheckInterval')) {
      case 'always':
        $group->condition('ldap_user_last_checked', time(), '<=');
        break;

      case 'daily':
        $group->condition('ldap_user_last_checked', strtotime('today'), '<');
        break;

      case 'weekly':
      default:
        $group->condition('ldap_user_last_checked', strtotime('today - 7 days'), '<');
        break;

      case 'monthly':
        $group->condition('ldap_user_last_checked', strtotime('today - 30 days'), '<');
        break;
    }
    $query->condition($group);
    $uids = $query->execute();

    if (count($uids) < $this->configLdapUser->get('orphanedCheckQty')) {
      $this->state->set('ldap_user_cron_last_uid_checked', 1);
    }
    else {
      $this->state->set('ldap_user_cron_last_uid_checked', max($uids));
    }

    return $uids;
  }

  /**
   * Process one user.
   *
   * @param array $users
   *   User to process.
   */
  private function processOrphanedAccounts(array $users): void {
    foreach ($users as $user) {
      if (isset($user['uid']) && $user['exists'] === FALSE) {
        /** @var \Drupal\user\Entity\User $account */
        $account = $this->entityTypeManager
          ->getStorage('user')
          ->load($user['uid']);
        $method = $this->configLdapUser->get('orphanedDrupalAcctBehavior');
        switch ($method) {
          case 'ldap_user_orphan_email';
            $link = Url::fromRoute('entity.user.edit_form', ['user' => $user['uid']])->setAbsolute();
            $this->emailList[] = $account->getAccountName() . ',' . $account->getEmail() . ',' . $link->toString();
            $account->set('ldap_user_last_checked', time())->save();
            break;

          case 'user_cancel_block':
          case 'user_cancel_block_unpublish':
          case 'user_cancel_reassign':
          case 'user_cancel_delete':
            $this->emailList[] = $account->getAccountName() . ',' . $account->getEmail();
            $this->cancelUser($account, $method);
            break;
        }
      }
    }
  }

  /**
   * Cancel the user.
   *
   * @param \Drupal\user\UserInterface $account
   *   Account.
   * @param string $method
   *   Method.
   */
  private function cancelUser(UserInterface $account, string $method): void {
    // Copied from user_canel().
    // When the 'user_cancel_delete' method is used, user_delete() is called,
    // which invokes hook_ENTITY_TYPE_predelete() and hook_ENTITY_TYPE_delete()
    // for the user entity. Modules should use those hooks to respond to the
    // account deletion.
    $edit = [];
    if ($method !== 'user_cancel_delete') {
      \Drupal::moduleHandler()->invokeAll(
        'user_cancel',
        [$edit, $account, $method]
      );
    }
    _user_cancel($edit, $account, $method);
  }

  /**
   * Check eligible user against directory.
   *
   * @param int $uid
   *   User ID.
   * @param string $serverId
   *   Server ID.
   * @param string $persistentUidProperty
   *   PUID Property.
   * @param string $persistentUid
   *   PUID.
   *
   * @return array
   *   Eligible user data.
   */
  private function ldapQueryEligibleUser(int $uid, string $serverId, string $persistentUidProperty, string $persistentUid): array {

    $user['uid'] = $uid;
    $user['exists'] = FALSE;
    // Query LDAP and update the prepared users with the actual state.
    if (!isset($this->enabledServers[$serverId])) {
      $this->logger->error('Server %id not enabled, but needed to remove orphaned LDAP users', ['%id' => $serverId]);
      throw new \Exception('Server not available, aborting.');
    }

    if ($this->enabledServers[$serverId]->get('unique_persistent_attr_binary')) {
      $persistentUid = $this->binaryFilter($persistentUid);
    }

    $this->ldapUserManager->setServerById($serverId);
    $ldapEntries = $this->ldapUserManager
      ->searchAllBaseDns(
        sprintf('(%s=%s)', $persistentUidProperty, $persistentUid),
        [$persistentUidProperty]
      );
    if (!empty($ldapEntries)) {
      $user['exists'] = TRUE;
    }

    return $user;
  }

}
