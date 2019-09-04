<?php

namespace Drupal\ldap_user\Helper;

use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers\Helper\ConversionHelper;
use Drupal\ldap_servers\LdapUserAttributesInterface;

/**
 * Helper class to process user field synchronisation mappings.
 */
class SyncMappingHelper implements LdapUserAttributesInterface {


  /**
   * Sync mappings.
   *
   * @var array
   *  Array of field sync mappings provided by all modules.
   *
   * Via hook_ldap_user_attrs_list_alter(). Array has the form of [
   * LdapConfiguration:: | s => [
   *   <server_id> => array(
   *   'sid' => <server_id> (redundant)
   *   'ldap_attr' => e.g. [sn]
   *   'user_attr'  => e.g. [field.field_user_lname]
   *     (when this value is set to 'user_tokens', 'user_tokens' value is used.)
   *   'user_tokens' => e.g. [field.field_user_lname], [field.field_user_fname]
   *   'convert' => 1|0 boolean indicating need to covert from binary
   *   'direction' => LdapUserAttributesInterface::PROVISION_TO_DRUPAL ||
   *     LdapUserAttributesInterface::PROVISION_TO_LDAP (redundant)
   *   'config_module' => 'ldap_user'
   *   'prov_module' => 'ldap_user'
   *   'enabled' => 1|0 boolean
   *   prov_events' => [( see events above )]
   *  ]
   *
   * Array of field syncing directions for each operation. Should include
   * ldapUserSyncMappings. Keyed on direction => property, ldap, or field token
   * such as '[field.field_lname] with brackets in them.
   */
  private $syncMapping;

  private $config;

  /**
   * Constructor.
   */
  public function __construct() {
    $this->config = \Drupal::config('ldap_user.settings');
    $this->loadSyncMappings();
  }

  /**
   * Given configuration of syncing, determine is a given sync should occur.
   *
   * @param string $attr_token
   *   Attribute token such as [property.mail], or
   *   [field.ldap_user_puid_property].
   * @param array $prov_events
   *   Provisioning events such as
   *   LdapUserAttributesInterface::EVENT_CREATE_DRUPAL_USER.
   *   Typically an array with one element.
   * @param int $direction
   *   Either LdapConfiguration::PROVISION_TO_DRUPAL or
   *   LdapConfiguration::PROVISION_TO_LDAP.
   *
   * @return bool
   *   If sync should occur.
   */
  public function isSynced($attr_token, array $prov_events, $direction) {
    $result = (boolean) (
      isset($this->syncMapping[$direction][$attr_token]['prov_events']) &&
      count(array_intersect($prov_events, $this->syncMapping[$direction][$attr_token]['prov_events']))
    );
    return $result;
  }

  /**
   * Util to fetch mappings for a given direction.
   *
   * @param string $direction
   *   Direction to sync in.
   * @param array $prov_events
   *   Events to act upon.
   *
   * @return array|bool
   *   Array of mappings (may be empty array)
   */
  public function getSyncMappings($direction = NULL, array $prov_events = NULL) {
    if (!$prov_events) {
      $prov_events = LdapConfiguration::getAllEvents();
    }
    if ($direction == NULL) {
      $direction = self::PROVISION_TO_ALL;
    }

    $mappings = [];
    if ($direction == self::PROVISION_TO_ALL) {
      $directions = [self::PROVISION_TO_DRUPAL, self::PROVISION_TO_LDAP];
    }
    else {
      $directions = [$direction];
    }
    // TODO: Note that we again query the DB, getSyncMappings() goes around the
    // general implementation and could be its own class.
    foreach ($directions as $direction) {
      if (!empty($this->config->get('ldapUserSyncMappings')[$direction])) {
        foreach ($this->config->get('ldapUserSyncMappings')[$direction] as $mapping) {
          if (!empty($mapping['prov_events'])) {
            $result = count(array_intersect($prov_events, $mapping['prov_events']));
            if ($result) {
              if ($direction == self::PROVISION_TO_DRUPAL && isset($mapping['user_attr'])) {
                $key = $mapping['user_attr'];
              }
              elseif ($direction == self::PROVISION_TO_LDAP && isset($mapping['ldap_attr'])) {
                $key = $mapping['ldap_attr'];
              }
              else {
                continue;
              }
              $mappings[$key] = $mapping;
            }
          }
        }
      }
    }
    return $mappings;
  }

  /**
   * Returns all available mappings.
   *
   * @TODO: Try to remove this, parsing of arrays as in LdapUserAdminForm is
   * not ideal.
   *
   * @return array
   *   All sync mappings.
   */
  public function getAllSyncMappings() {
    return $this->syncMapping;
  }

  /**
   * Setter function to ease testing.
   *
   * @param array $mappings
   *   Set all mappings.
   */
  private function setAllSyncMappings(array $mappings) {
    $this->syncMapping = $mappings;
  }

  /**
   * Fetches the sync mappings from cache or loads them from configuration.
   */
  public function loadSyncMappings() {
    $syncMappingsCache = \Drupal::cache()->get('ldap_user_sync_mapping');
    if ($syncMappingsCache) {
      $this->syncMapping = $syncMappingsCache->data;
    }
    else {
      $this->processSyncMappings();
      \Drupal::cache()->set('ldap_user_sync_mapping', $this->syncMapping);
    }
  }

  /**
   * Derive synchronization mappings from configuration.
   *
   * This function would be private if not for easier access for tests.
   *
   * return array
   */
  private function processSyncMappings() {
    $available_user_attributes = [];
    foreach ([
      self::PROVISION_TO_DRUPAL,
      self::PROVISION_TO_LDAP,
    ] as $direction) {
      if ($direction == self::PROVISION_TO_DRUPAL) {
        $sid = $this->config->get('drupalAcctProvisionServer');
      }
      else {
        $sid = $this->config->get('ldapEntryProvisionServer');
      }
      $available_user_attributes[$direction] = [];
      $ldap_server = FALSE;
      if ($sid) {
        try {
          $ldap_server = Server::load($sid);
        }
        catch (\Exception $e) {
          \Drupal::logger('ldap_user')->error('Missing server');
        }
      }

      $params = [
        'ldap_server' => $ldap_server,
        'direction' => $direction,
      ];

      // This function does not add any attributes by itself but allows modules
      // such as ldap_user to inject them through this hook.
      \Drupal::moduleHandler()->alter(
        'ldap_user_attrs_list',
        $available_user_attributes[$direction],
        $params
      );
    }
    $this->setAllSyncMappings($available_user_attributes);
  }

  /**
   * Util to fetch attributes required for this user conf, not other modules.
   *
   * @param int $direction
   *   LDAP_USER_PROV_DIRECTION_* constants.
   * @param string $ldap_context
   *   LDAP context.
   *
   * @return array
   *   Required attributes.
   */
  public function getLdapUserRequiredAttributes($direction = NULL, $ldap_context = NULL) {
    if ($direction == NULL) {
      $direction = self::PROVISION_TO_ALL;
    }
    $required_attributes = [];
    if ($this->config->get('drupalAcctProvisionServer')) {
      $prov_events = $this->ldapContextToProvEvents($ldap_context);
      $attributes_map = $this->getSyncMappings($direction, $prov_events);
      $required_attributes = [];
      foreach ($attributes_map as $detail) {
        if (count(array_intersect($prov_events, $detail['prov_events']))) {
          // Add the attribute to our array.
          if ($detail['ldap_attr']) {
            ConversionHelper::extractTokenAttributes($required_attributes, $detail['ldap_attr']);
          }
        }
      }
    }
    return $required_attributes;
  }

  /**
   * Convert context for ldap_user event.
   *
   * Converts the more general ldap_context string to its associated LDAP user
   * event.
   *
   * @param string|null $ldapContext
   *   Context.
   *
   * @return array
   *   List of events.
   */
  public static function ldapContextToProvEvents($ldapContext = NULL) {

    switch ($ldapContext) {
      case 'ldap_user_prov_to_drupal':
        $result = [
          self::EVENT_SYNC_TO_DRUPAL_USER,
          self::EVENT_CREATE_DRUPAL_USER,
          self::EVENT_LDAP_ASSOCIATE_DRUPAL_USER,
        ];
        break;

      case 'ldap_user_prov_to_ldap':
        $result = [
          self::EVENT_SYNC_TO_LDAP_ENTRY,
          self::EVENT_CREATE_LDAP_ENTRY,
        ];
        break;

      default:
        $result = LdapConfiguration::getAllEvents();
        break;
    }
    return $result;
  }

}
