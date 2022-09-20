<?php

declare(strict_types = 1);

namespace Drupal\ldap_servers;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\externalauth\Authmap;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\LdapException;
use function is_array;

/**
 * LDAP User Manager.
 */
class LdapUserManager extends LdapBaseManager {


  /**
   * Cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Externalauth.
   *
   * @var \Drupal\externalauth\Authmap
   */
  protected $externalAuth;

  /**
   * Constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager.
   * @param \Drupal\ldap_servers\LdapBridgeInterface $ldap_bridge
   *   LDAP bridge.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   Module handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   Cache.
   * @param \Drupal\externalauth\Authmap $external_auth
   *   External auth.
   */
  public function __construct(
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
    LdapBridgeInterface $ldap_bridge,
    ModuleHandler $module_handler,
    CacheBackendInterface $cache,
    Authmap $external_auth) {
    parent::__construct($logger, $entity_type_manager, $ldap_bridge, $module_handler);
    $this->cache = $cache;
    $this->externalAuth = $external_auth;
  }

  /**
   * Create LDAP User entry.
   *
   * Adds AD-specific password handling.
   *
   * @param \Symfony\Component\Ldap\Entry $entry
   *   LDAP Entry.
   *
   * @return bool
   *   Result of action.
   */
  public function createLdapEntry(Entry $entry): bool {
    if (!$this->checkAvailability()) {
      return FALSE;
    }

    // Can be mixed case on direction-to-LDAP.
    if ($entry->hasAttribute('unicodePwd', FALSE) && $this->server->get('type') === 'ad') {
      $converted = $this->convertPasswordForActiveDirectoryUnicodePwd($entry->getAttribute('unicodePwd', FALSE)[0]);
      $entry->setAttribute('unicodePwd', [$converted]);
    }

    try {
      $this->ldap->getEntryManager()->add($entry);
    }
    catch (LdapException $e) {
      $this->logger->error("LDAP server %id exception: %ldap_error", [
        '%id' => $this->server->id(),
        '%ldap_error' => $e->getMessage(),
      ]
          );
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Apply modifications to entry.
   *
   * @param \Symfony\Component\Ldap\Entry $entry
   *   LDAP Entry.
   * @param \Symfony\Component\Ldap\Entry $current
   *   LDAP Entry.
   *
   * @todo / @FIXME: This is not called.
   */
  protected function applyModificationsToEntry(Entry $entry, Entry $current): void {
    if ($entry->hasAttribute('unicodePwd', FALSE) && $this->server->get('type') === 'ad') {
      $converted = $this->convertPasswordForActiveDirectoryUnicodePwd($entry->getAttribute('unicodePwd', FALSE)[0]);
      $entry->setAttribute('unicodePwd', [$converted]);
    }

    parent::applyModificationsToEntry($entry, $current);
  }

  /**
   * Convert password to format required by Active Directory.
   *
   * For the purpose of changing or setting the password. Note that AD needs the
   * field to be called unicodePwd (as opposed to userPassword).
   *
   * @param string|array $password
   *   The password that is being formatted for Active Directory unicodePwd
   *   field.
   *
   * @return string|array
   *   $password surrounded with quotes and in UTF-16LE encoding
   */
  protected function convertPasswordForActiveDirectoryUnicodePwd($password) {
    // This function can be called with $attributes['unicodePwd'] as an array.
    if (!is_array($password)) {
      return mb_convert_encoding(sprintf('"%s"', $password), 'UTF-16LE');
    }

    // Presumably there is no use case for there being more than one password
    // in the $attributes array, hence it will be at index 0, and we return in
    // kind.
    return [mb_convert_encoding(sprintf('"%s"', $password[0]), 'UTF-16LE')];
  }

  /**
   * Fetches the user account based on the persistent UID.
   *
   * @param string $puid
   *   As returned from ldap_read or other LDAP function (can be binary).
   *
   * @return \Drupal\user\UserInterface|null
   *   The updated user or error.
   */
  public function getUserAccountFromPuid(string $puid): ?UserInterface {
    $result = NULL;

    if ($this->checkAvailability()) {
      $storage = $this->entityTypeManager->getStorage('user');
      $query = $storage->getQuery();
      $query->condition('ldap_user_puid_sid', $this->server->id(), '=')
        ->condition('ldap_user_puid', $puid, '=')
        ->condition('ldap_user_puid_property', $this->server->getUniquePersistentAttribute(), '=')
        ->accessCheck(FALSE);
      $queryResult = $query->execute();

      if (count($queryResult) === 1) {
        /** @var \Drupal\user\UserInterface $result */
        $result = $storage->load(array_values($queryResult)[0]);
      }

      if (count($queryResult) > 1) {
        $uids = implode(',', $queryResult);
        $this->logger->error(
          'Multiple users (uids: %uids) with same puid (puid=%puid, sid=%sid, ldap_user_puid_property=%ldap_user_puid_property)',
          [
            '%uids' => $uids,
            '%puid' => $puid,
            '%id' => $this->server->id(),
            '%ldap_user_puid_property' => $this->server->getUniquePersistentAttribute(),
          ]
        );
      }
    }

    return $result;
  }

  /**
   * Fetch user data from server by Identifier.
   *
   * @param string $identifier
   *   User identifier.
   *
   * @return \Symfony\Component\Ldap\Entry|false
   *
   *   This should go into LdapUserProcessor or LdapUserManager, leaning toward
   *   the former.
   */
  public function getUserDataByIdentifier(string $identifier) {
    if (!$this->checkAvailability()) {
      return FALSE;
    }

    // Try to retrieve the user from the cache.
    $cache = $this->cache->get('ldap_servers:user_data:' . $identifier);
    if ($cache && $cache->data) {
      return $cache->data;
    }

    $ldap_entry = $this->queryAllBaseDnLdapForUsername($identifier);
    if ($ldap_entry) {
      $ldap_entry = $this->sanitizeUserDataResponse($ldap_entry, $identifier);
      $cache_expiry = 5 * 60 + time();
      $cache_tags = ['ldap', 'ldap_servers', 'ldap_servers.user_data'];
      $this->cache->set('ldap_servers:user_data:' . $identifier, $ldap_entry, $cache_expiry, $cache_tags);
    }

    return $ldap_entry;
  }

  /**
   * Fetch user data from server by user account.
   *
   * @param \Drupal\user\UserInterface $account
   *   Drupal user account.
   *
   * @return \Symfony\Component\Ldap\Entry|false
   *   Returns entry or FALSE.
   *
   *   @todo This should go into LdapUserProcessor or LdapUserManager,
   *   leaning toward the former.
   */
  public function getUserDataByAccount(UserInterface $account) {
    if (!$this->checkAvailability()) {
      return FALSE;
    }

    $identifier = $this->externalAuth->get($account->id(), 'ldap_user');
    if ($identifier) {
      return $this->getUserDataByIdentifier($identifier);
    }

    return FALSE;
  }

}
