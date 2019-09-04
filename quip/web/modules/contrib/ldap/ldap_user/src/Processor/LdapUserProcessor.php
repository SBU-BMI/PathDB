<?php

namespace Drupal\ldap_user\Processor;

use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers\Helper\ConversionHelper;
use Drupal\ldap_servers\Processor\TokenProcessor;
use Drupal\ldap_user\Exception\LdapBadParamsException;
use Drupal\ldap_user\Helper\LdapConfiguration;
use Drupal\ldap_servers\LdapUserAttributesInterface;
use Drupal\ldap_user\Helper\SyncMappingHelper;
use Drupal\user\Entity\User;

/**
 * Processor for LDAP provisioning.
 */
class LdapUserProcessor implements LdapUserAttributesInterface {

  /**
   * Configuration settings from ldap_user.
   *
   * @var \Drupal\Core\Config\Config
   */
  private $config;

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
    $this->config = \Drupal::config('ldap_user.settings')->get();
    $this->detailLog = \Drupal::service('ldap.detail_log');
    $this->tokenProcessor = \Drupal::service('ldap.token_processor');
  }

  /**
   * Given a Drupal account, sync to related LDAP entry.
   *
   * @param \Drupal\user\Entity\User $account
   *   Drupal user object.
   * @param array $ldapUser
   *   Current LDAP data of user. See README.developers.txt for structure.
   * @param bool $testQuery
   *   Test query or live query.
   *
   * @return array|bool
   *   Successful sync.
   *
   * @TODO: $ldapUser and $testQuery are not in use.
   * Verify that we need actually need those for a missing test case or remove.
   */
  public function syncToLdapEntry(User $account, array $ldapUser = [], $testQuery = FALSE) {

    // @TODO 2914053.
    if (is_object($account) && $account->id() == 1) {
      // Do not provision or sync user 1.
      return FALSE;
    }

    $result = FALSE;

    if ($this->config['ldapEntryProvisionServer']) {

      $server = Server::load($this->config['ldapEntryProvisionServer']);

      $params = [
        'direction' => self::PROVISION_TO_LDAP,
        'prov_events' => [self::EVENT_SYNC_TO_LDAP_ENTRY],
        'module' => 'ldap_user',
        'function' => 'syncToLdapEntry',
        'include_count' => FALSE,
      ];

      try {
        $proposedLdapEntry = $this->drupalUserToLdapEntry($account, $server, $params, $ldapUser);
      }
      catch (\Exception $e) {
        \Drupal::logger('ldap_user')->error('Unable to prepare LDAP entry: %message', ['%message', $e->getMessage()]);
        return FALSE;
      }

      if (is_array($proposedLdapEntry) && isset($proposedLdapEntry['dn'])) {
        // This array represents attributes to be modified; not comprehensive
        // list of attributes.
        $attributes = [];
        foreach ($proposedLdapEntry as $attributeName => $attributeValues) {
          if ($attributeName != 'dn') {
            if (isset($attributeValues['count'])) {
              unset($attributeValues['count']);
            }
            if (count($attributeValues) == 1) {
              $attributes[$attributeName] = $attributeValues[0];
            }
            else {
              $attributes[$attributeName] = $attributeValues;
            }
          }
        }

        if ($testQuery) {
          $proposedLdapEntry = $attributes;
          $result = [
            'proposed' => $proposedLdapEntry,
            'server' => $server,
          ];
        }
        else {
          // Stick $proposedLdapEntry in $ldap_entries array for drupal_alter.
          $proposedDnLowerCase = mb_strtolower($proposedLdapEntry['dn']);
          $ldap_entries = [$proposedDnLowerCase => $attributes];
          $context = [
            'action' => 'update',
            'corresponding_drupal_data' => [$proposedDnLowerCase => $attributes],
            'corresponding_drupal_data_type' => 'user',
            'account' => $account,
          ];
          \Drupal::moduleHandler()->alter('ldap_entry_pre_provision', $ldap_entries, $server, $context);
          // Remove altered $proposedLdapEntry from $ldap_entries array.
          $attributes = $ldap_entries[$proposedDnLowerCase];
          $result = $server->modifyLdapEntry($proposedLdapEntry['dn'], $attributes);

          if ($result) {
            \Drupal::moduleHandler()
              ->invokeAll('ldap_entry_post_provision', [
                $ldap_entries,
                $server,
                $context,
              ]);
          }

        }

      }
      // Failed to get acceptable proposed LDAP entry.
      else {
        $result = FALSE;
      }
    }

    $tokens = [
      '%dn' => isset($proposedLdapEntry['dn']) ? $proposedLdapEntry['dn'] : 'null',
      '%sid' => $this->config['ldapEntryProvisionServer'],
      '%username' => $account->getAccountName(),
      '%uid' => (!method_exists($account, 'id') || empty($account->id())) ? '' : $account->id(),
      '%action' => $result ? t('synced') : t('not synced'),
    ];

    \Drupal::logger('ldap_user')
      ->info('LDAP entry on server %sid %action dn=%dn for username=%username, uid=%uid', $tokens);

    return $result;

  }

  /**
   * Populate LDAP entry array for provisioning.
   *
   * @param \Drupal\user\Entity\User $account
   *   Drupal account.
   * @param \Drupal\ldap_servers\Entity\Server $ldap_server
   *   LDAP server.
   * @param array $params
   *   Parameters with the following key values:
   *   'ldap_context' =>
   *   'module' => module calling function, e.g. 'ldap_user'
   *   'function' => function calling function, e.g. 'provisionLdapEntry'
   *   'include_count' => should 'count' array key be included
   *   'direction' => self::PROVISION_TO_LDAP || self::PROVISION_TO_DRUPAL.
   * @param array|null $ldapUserEntry
   *   The LDAP user entry.
   *
   * @return array
   *   Array of (ldap entry, $result) in LDAP extension array format.
   *   THIS IS NOT THE ACTUAL LDAP ENTRY.
   *
   * @throws \Drupal\ldap_user\Exception\LdapBadParamsException
   */
  public function drupalUserToLdapEntry(User $account, Server $ldap_server, array $params, $ldapUserEntry = NULL) {
    $provision = (isset($params['function']) && $params['function'] == 'provisionLdapEntry');
    if (!$ldapUserEntry) {
      $ldapUserEntry = [];
    }

    if (!is_object($account) || !is_object($ldap_server)) {
      throw new LdapBadParamsException('Missing user or server.');
    }

    $include_count = (isset($params['include_count']) && $params['include_count']);

    $direction = isset($params['direction']) ? $params['direction'] : self::PROVISION_TO_ALL;
    $prov_events = empty($params['prov_events']) ? LdapConfiguration::getAllEvents() : $params['prov_events'];

    $syncMapper = new SyncMappingHelper();
    $mappings = $syncMapper->getSyncMappings($direction, $prov_events);
    // Loop over the mappings.
    foreach ($mappings as $field_key => $field_detail) {
      list($ldapAttributeName, $ordinal) = $this->extractTokenParts($field_key);
      $ordinal = (!$ordinal) ? 0 : $ordinal;
      if ($ldapUserEntry && isset($ldapUserEntry[$ldapAttributeName]) && is_array($ldapUserEntry[$ldapAttributeName]) && isset($ldapUserEntry[$ldapAttributeName][$ordinal])) {
        // Don't override values passed in.
        continue;
      }

      $synced = $syncMapper->isSynced($field_key, $params['prov_events'], self::PROVISION_TO_LDAP);
      if ($synced) {
        $token = ($field_detail['user_attr'] == 'user_tokens') ? $field_detail['user_tokens'] : $field_detail['user_attr'];
        $value = $this->tokenProcessor->tokenReplace($account, $token, 'user_account');

        // Deal with empty/unresolved password.
        if (substr($token, 0, 10) == '[password.' && (!$value || $value == $token)) {
          if (!$provision) {
            // Don't overwrite password on sync if no value provided.
            continue;
          }
        }

        if ($ldapAttributeName == 'dn' && $value) {
          $ldapUserEntry['dn'] = $value;
        }
        elseif ($value) {
          if (!isset($ldapUserEntry[$ldapAttributeName]) || !is_array($ldapUserEntry[$ldapAttributeName])) {
            $ldapUserEntry[$ldapAttributeName] = [];
          }
          $ldapUserEntry[$ldapAttributeName][$ordinal] = $value;
          if ($include_count) {
            $ldapUserEntry[$ldapAttributeName]['count'] = count($ldapUserEntry[$ldapAttributeName]);
          }
        }
      }
    }

    // Allow other modules to alter $ldap_user.
    \Drupal::moduleHandler()->alter('ldap_entry', $ldapUserEntry, $params);

    return $ldapUserEntry;
  }

  /**
   * Extract parts of token.
   *
   * @param string $token
   *   Token or token expression with singular token in it, eg. [dn],
   *   [dn;binary], [titles:0;binary] [cn]@mycompany.com.
   *
   * @return array
   *   Array triplet containing [<attr_name>, <ordinal>, <conversion>].
   */
  private function extractTokenParts($token) {
    $attributes = [];
    ConversionHelper::extractTokenAttributes($attributes, $token);
    if (is_array($attributes)) {
      $keys = array_keys($attributes);
      $attr_name = $keys[0];
      $attr_data = $attributes[$attr_name];
      $ordinals = array_keys($attr_data['values']);
      $ordinal = $ordinals[0];
      return [$attr_name, $ordinal];
    }
    else {
      return [NULL, NULL];
    }

  }

  /**
   * Provision an LDAP entry if none exists.
   *
   * If one exists do nothing, takes Drupal user as argument.
   *
   * @param \Drupal\user\Entity\User|string $account
   *   Drupal account object with minimum of name property.
   * @param array $ldap_user
   *   LDAP user as pre-populated LDAP entry. Usually not provided.
   *
   * @return array
   *   Format:
   *     array('status' => 'success', 'fail', or 'conflict'),
   *     array('ldap_server' => LDAP server object),
   *     array('proposed' => proposed LDAP entry),
   *     array('existing' => existing LDAP entry),
   *     array('description' = > blah blah)
   */
  public function provisionLdapEntry($account, array $ldap_user = NULL) {

    $result = [
      'status' => NULL,
      'ldap_server' => NULL,
      'proposed' => NULL,
      'existing' => NULL,
      'description' => NULL,
    ];

    if (is_scalar($account)) {
      $account = user_load_by_name($account);
    }

    // @TODO 2914053.
    if (is_object($account) && $account->id() == 1) {
      $result['status'] = 'fail';
      $result['error_description'] = 'can not provision Drupal user 1';
      // Do not provision or sync user 1.
      return $result;
    }

    if ($account == FALSE || $account->isAnonymous()) {
      $result['status'] = 'fail';
      $result['error_description'] = 'can not provision LDAP user unless corresponding Drupal account exists first.';
      return $result;
    }

    if (!$this->config['ldapEntryProvisionServer']) {
      $result['status'] = 'fail';
      $result['error_description'] = 'no provisioning server enabled';
      return $result;
    }
    $factory = \Drupal::service('ldap.servers');
    /** @var \Drupal\ldap_servers\Entity\Server $ldapServer */
    $ldapServer = $factory->getServerById($this->config['ldapEntryProvisionServer']);
    $params = [
      'direction' => self::PROVISION_TO_LDAP,
      'prov_events' => [self::EVENT_CREATE_LDAP_ENTRY],
      'module' => 'ldap_user',
      'function' => 'provisionLdapEntry',
      'include_count' => FALSE,
    ];

    try {
      $proposedLdapEntry = $this->drupalUserToLdapEntry($account, $ldapServer, $params, $ldap_user);
    }
    catch (\Exception $e) {
      \Drupal::logger('ldap_user')->error('User or server is missing during LDAP provisioning: %message', ['%message', $e->getMessage()]);
      return [
        'status' => 'fail',
        'ldap_server' => $ldapServer,
        'created' => NULL,
        'existing' => NULL,
      ];
    }

    if ((is_array($proposedLdapEntry) && isset($proposedLdapEntry['dn']) && $proposedLdapEntry['dn'])) {
      $proposedDn = $proposedLdapEntry['dn'];
    }
    else {
      $proposedDn = NULL;
    }
    $proposedDnLowercase = mb_strtolower($proposedDn);
    $existingLdapEntry = ($proposedDn) ? $ldapServer->checkDnExistsIncludeData($proposedDn, ['objectclass']) : NULL;

    if (!$proposedDn) {
      return [
        'status' => 'fail',
        'description' => t('failed to derive dn and or mappings'),
      ];
    }
    elseif ($existingLdapEntry) {
      $result['status'] = 'conflict';
      $result['description'] = 'can not provision LDAP entry because exists already';
      $result['existing'] = $existingLdapEntry;
      $result['proposed'] = $proposedLdapEntry;
      $result['ldap_server'] = $ldapServer;
    }
    else {
      // Stick $proposedLdapEntry in $ldapEntries array for drupal_alter.
      $ldapEntries = [$proposedDnLowercase => $proposedLdapEntry];
      $context = [
        'action' => 'add',
        'corresponding_drupal_data' => [$proposedDnLowercase => $account],
        'corresponding_drupal_data_type' => 'user',
        'account' => $account,
      ];
      \Drupal::moduleHandler()->alter('ldap_entry_pre_provision', $ldapEntries, $ldapServer, $context);
      // Remove altered $proposedLdapEntry from $ldapEntries array.
      $proposedLdapEntry = $ldapEntries[$proposedDnLowercase];

      $ldapEntryCreated = $ldapServer->createLdapEntry($proposedLdapEntry, $proposedDn);
      $callbackParams = [$ldapEntries, $ldapServer, $context];
      if ($ldapEntryCreated) {
        \Drupal::moduleHandler()
          ->invokeAll('ldap_entry_post_provision', $callbackParams);
        $result = [
          'status' => 'success',
          'description' => 'ldap account created',
          'proposed' => $proposedLdapEntry,
          'created' => $ldapEntryCreated,
          'ldap_server' => $ldapServer,
        ];
        // Need to store <sid>|<dn> in ldap_user_prov_entries field, which may
        // contain more than one.
        $ldap_user_prov_entry = $ldapServer->id() . '|' . $proposedLdapEntry['dn'];
        if (NULL !== $account->get('ldap_user_prov_entries')) {
          $account->set('ldap_user_prov_entries', []);
        }
        $ldapUserProvisioningEntryExists = FALSE;
        if ($account->get('ldap_user_prov_entries')->value) {
          foreach ($account->get('ldap_user_prov_entries')->value as $fieldValueInstance) {
            if ($fieldValueInstance == $ldap_user_prov_entry) {
              $ldapUserProvisioningEntryExists = TRUE;
            }
          }
        }
        if (!$ldapUserProvisioningEntryExists) {
          // @TODO Serialise?
          $prov_entries = $account->get('ldap_user_prov_entries')->value;
          $prov_entries[] = [
            'value' => $ldap_user_prov_entry,
            'format' => NULL,
            'save_value' => $ldap_user_prov_entry,
          ];
          $account->set('ldap_user_prov_entries', $prov_entries);
          $account->save();
        }

      }
      else {
        $result = [
          'status' => 'fail',
          'proposed' => $proposedLdapEntry,
          'created' => $ldapEntryCreated,
          'ldap_server' => $ldapServer,
          'existing' => NULL,
        ];
      }
    }

    $tokens = [
      '%dn' => isset($result['proposed']['dn']) ? $result['proposed']['dn'] : NULL,
      '%sid' => (isset($result['ldap_server']) && $result['ldap_server']) ? $result['ldap_server']->id() : 0,
      '%username' => @$account->getAccountName(),
      '%uid' => @$account->id(),
      '%description' => @$result['description'],
    ];
    if (isset($result['status'])) {
      if ($result['status'] == 'success') {
        $this->detailLog->log(
          'LDAP entry on server %sid created dn=%dn.  %description. username=%username, uid=%uid',
          $tokens, 'ldap_user'
        );
      }
      elseif ($result['status'] == 'conflict') {
        $this->detailLog->log(
          'LDAP entry on server %sid not created because of existing LDAP entry. %description. username=%username, uid=%uid',
          $tokens, 'ldap_user'
        );
      }
      elseif ($result['status'] == 'fail') {
        \Drupal::logger('ldap_user')
          ->error('LDAP entry on server %sid not created because of error. %description. username=%username, uid=%uid, proposed dn=%dn', $tokens);
      }
    }
    return $result;
  }

  /**
   * Delete a provisioned LDAP entry.
   *
   * Given a Drupal account, delete LDAP entry that was provisioned based on it
   * normally this will be 0 or 1 entry, but the ldap_user_prov_entries field
   * attached to the user entity track each LDAP entry provisioned.
   *
   * @param \Drupal\user\Entity\User $account
   *   Drupal user account.
   *
   * @return bool
   *   FALSE indicates failed or action not enabled in LDAP user configuration.
   */
  public function deleteProvisionedLdapEntries(User $account) {
    // Determine server that is associated with user.
    $result = FALSE;
    $entries = $account->get('ldap_user_prov_entries')->getValue();
    foreach ($entries as $entry) {
      $parts = explode('|', $entry['value']);
      if (count($parts) == 2) {
        list($sid, $dn) = $parts;
        $factory = \Drupal::service('ldap.servers');
        $ldap_server = $factory->getServerById($sid);
        if (is_object($ldap_server) && $dn) {
          /** @var \Drupal\ldap_servers\Entity\Server $ldap_server */
          $result = $ldap_server->deleteLdapEntry($dn);
          $tokens = [
            '%sid' => $sid,
            '%dn' => $dn,
            '%username' => $account->getAccountName(),
            '%uid' => $account->id(),
          ];
          if ($result) {
            \Drupal::logger('ldap_user')
              ->info('LDAP entry on server %sid deleted dn=%dn. username=%username, uid=%uid', $tokens);
          }
          else {
            \Drupal::logger('ldap_user')
              ->error('LDAP entry on server %sid not deleted because error. username=%username, uid=%uid', $tokens);
          }
        }
        else {
          $result = FALSE;
        }
      }
    }
    return $result;
  }

  /**
   * Given a Drupal account, find the related LDAP entry.
   *
   * @param \Drupal\user\Entity\User $account
   *   Drupal user account.
   * @param string|null $prov_events
   *   Provisioning event.
   *
   * @return bool|array
   *   False or LDAP entry
   */
  public function getProvisionRelatedLdapEntry(User $account, $prov_events = NULL) {
    if (!$prov_events) {
      $prov_events = LdapConfiguration::getAllEvents();
    }
    $sid = $this->config['ldapEntryProvisionServer'];
    if (!$sid) {
      return FALSE;
    }
    // $user_entity->ldap_user_prov_entries,.
    $factory = \Drupal::service('ldap.servers');
    /** @var \Drupal\ldap_servers\Entity\Server $ldap_server */
    $ldap_server = $factory->getServerById($sid);
    $params = [
      'direction' => self::PROVISION_TO_LDAP,
      'prov_events' => $prov_events,
      'module' => 'ldap_user',
      'function' => 'getProvisionRelatedLdapEntry',
      'include_count' => FALSE,
    ];

    try {
      $proposed_ldap_entry = $this->drupalUserToLdapEntry($account, $ldap_server, $params);
    }
    catch (\Exception $e) {
      \Drupal::logger('ldap_user')->error('Unable to prepare LDAP entry: %message', ['%message', $e->getMessage()]);
      return FALSE;
    }

    if (!(is_array($proposed_ldap_entry) && isset($proposed_ldap_entry['dn']) && $proposed_ldap_entry['dn'])) {
      return FALSE;
    }

    $ldap_entry = $ldap_server->checkDnExistsIncludeData($proposed_ldap_entry['dn'], []);
    return $ldap_entry;

  }

}
