<?php

namespace Drupal\ldap_user\Processor;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\ldap_servers\ServerFactory;

/**
 * Locates potential orphan user accounts.
 */
class OrphanProcessor {

  private $emailList = [];
  private $ldapQueryOrLimit = 30;
  private $missingServerSemaphore = [];
  private $enabledServers;

  protected $logger;
  protected $configLdapUser;
  protected $mailManager;
  protected $languageManager;
  protected $state;
  protected $entityTypeManager;

  /**
   * Constructor.
   */
  public function __construct(LoggerChannelInterface $logger, ConfigFactory $config, ServerFactory $factory, MailManagerInterface $mail_manager, LanguageManagerInterface $language_manager, StateInterface $state, EntityTypeManagerInterface $entity_type_manager) {
    $this->logger = $logger;
    $this->configFactory = $config;
    $this->configLdapUser = $config->get('ldap_user.settings');
    $this->enabledServers = $factory->getEnabledServers();
    $this->mailManager = $mail_manager;
    $this->languageManager = $language_manager;
    $this->state = $state;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Check for Drupal accounts which no longer have a related LDAP entry.
   */
  public function checkOrphans() {
    $orphan_policy = $this->configLdapUser->get('orphanedDrupalAcctBehavior');
    if (!$orphan_policy || $orphan_policy == 'ldap_user_orphan_do_not_check') {
      return;
    }

    $uids = $this->fetchUidsToCheck();

    if (!empty($uids)) {
      // To avoid query limits, queries are batched by the limit, for example
      // with 175 users and a limit of 30, 6 batches are run.
      $batches = floor(count($uids) / $this->ldapQueryOrLimit) + 1;
      $queriedUsers = [];
      for ($batch = 1; $batch <= $batches; $batch++) {
        $queriedUsers = array_merge($queriedUsers, $this->batchQueryUsers($batch, $uids));
      }

      $this->processOrphanedAccounts($queriedUsers);

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
  private function binaryFilter($value) {
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

    for ($i = 0; $i < strlen($value); $i = $i + 2) {
      $match .= '\\' . substr($value, $i, 2);
    }

    return $match;
  }

  /**
   * Send email.
   */
  public function sendOrphanedAccountsMail() {
    $to = $this->configFactory->get('system.site')->get('mail');
    $siteLanguage = $this->languageManager->getCurrentLanguage()->getId();
    $params = ['accounts' => $this->emailList];
    $result = $this->mailManager->mail('ldap_user', 'orphaned_accounts', $to, $siteLanguage, $params, NULL, TRUE);
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
  private function batchQueryUsers($batch, array $uids) {

    // Creates a list of users in the required format.
    $start = ($batch - 1) * $this->ldapQueryOrLimit;
    $end_plus_1 = min(($batch) * $this->ldapQueryOrLimit, count($uids));
    $batch_uids = array_slice($uids, $start, ($end_plus_1 - $start));

    $accounts = $this->entityTypeManager->getStorage('user')->loadMultiple($batch_uids);

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
  private function fetchUidsToCheck() {
    // We want to query Drupal accounts, which are LDAP associated where a DN
    // is present. The lastUidChecked is used to process only a limited number
    // of batches in the cron run and each user is only checked if the time
    // configured for checking has lapsed.
    $lastUidChecked = $this->state->get('ldap_user_cron_last_uid_checked', 1);

    $query = $this->entityTypeManager->getStorage('user')->getQuery()
      ->exists('ldap_user_current_dn')
      ->exists('ldap_user_puid_property')
      ->exists('ldap_user_puid_sid')
      ->exists('ldap_user_puid')
      ->condition('uid', $lastUidChecked, '>')
      ->condition('status', 1)
      ->sort('uid', 'ASC')
      ->range(0, $this->configLdapUser->get('orphanedCheckQty'));

    $group = $query->orConditionGroup();
    $group->notExists('ldap_user_last_checked');

    switch ($this->configLdapUser->get('orphanedAccountCheckInterval')) {
      case 'always':
        $group->condition('ldap_user_last_checked', time(), '<');
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
  private function processOrphanedAccounts(array $users) {
    $drupalUserProcessor = new DrupalUserProcessor();
    foreach ($users as $user) {
      if (isset($user['uid'])) {
        $account = $this->entityTypeManager->getStorage('user')->load($user['uid']);
        $drupalUserProcessor->drupalUserLogsIn($account);
        if ($user['exists'] == FALSE) {
          switch ($this->configLdapUser->get('orphanedDrupalAcctBehavior')) {
            case 'ldap_user_orphan_email';
              $link = Url::fromRoute('entity.user.edit_form', ['user' => $user['uid']])->setAbsolute();
              $this->emailList[] = $account->getAccountName() . "," . $account->getEmail() . "," . $link->toString();
              break;

            case 'user_cancel_block':
            case 'user_cancel_block_unpublish':
            case 'user_cancel_reassign':
            case 'user_cancel_delete':
              _user_cancel([], $account, $this->configLdapUser->get('orphanedDrupalAcctBehavior'));
              break;
          }
        }
      }
    }
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
  private function ldapQueryEligibleUser($uid, $serverId, $persistentUidProperty, $persistentUid) {

    $user['uid'] = $uid;
    $user['exists'] = FALSE;
    // Query LDAP and update the prepared users with the actual state.
    if (!isset($this->enabledServers[$serverId])) {
      if (!isset($this->missingServerSemaphore[$serverId])) {
        $this->logger->error('Server %id not enabled, but needed to remove orphaned LDAP users', ['%id' => $serverId]);
        $this->missingServerSemaphore[$serverId] = TRUE;
      }
    }
    else {
      if ($this->enabledServers[$serverId]->get('unique_persistent_attr_binary')) {
        $filter = "($persistentUidProperty=" . $this->binaryFilter($persistentUid) . ")";
      }
      else {
        $filter = "($persistentUidProperty=$persistentUid)";
      }

      $ldapEntries = $this->enabledServers[$serverId]->searchAllBaseDns($filter, [$persistentUidProperty]);
      if ($ldapEntries === FALSE) {
        $this->logger->error('LDAP server %id had error while querying to deal with orphaned LDAP user entries. Please check that the LDAP server is configured correctly',
            ['%id' => $serverId]
          );
        return [];
      }
      else {
        unset($ldapEntries['count']);
        if (!empty($ldapEntries)) {
          $user['exists'] = TRUE;
        }
      }
    }
    return $user;
  }

}
