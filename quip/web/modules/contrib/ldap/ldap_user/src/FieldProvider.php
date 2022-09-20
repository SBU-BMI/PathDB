<?php

declare(strict_types = 1);

namespace Drupal\ldap_user;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers\LdapUserAttributesInterface;
use Drupal\ldap_servers\Mapping;
use Drupal\Core\Link;
use Drupal\Core\Url;
use function in_array;

/**
 * Provides the basic and required fields needed for user mappings.
 */
class FieldProvider implements LdapUserAttributesInterface {

  use StringTranslationTrait;

  /**
   * Config.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Entity Field Manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * Server.
   *
   * @var \Drupal\ldap_servers\ServerInterface
   */
  private $server;

  /**
   * Direction.
   *
   * @var string
   */
  private $direction;

  /**
   * Attributes.
   *
   * @var \Drupal\ldap_servers\Mapping[]
   */
  private $attributes = [];

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   Config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   Module handler.
   * @param \Drupal\Core\Entity\EntityFieldManager $entity_field_manager
   *   Entity field manager.
   */
  public function __construct(
    ConfigFactory $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandler $module_handler,
    EntityFieldManager $entity_field_manager
  ) {
    $this->config = $config_factory->get('ldap_user.settings');
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * LDAP attributes to alter.
   *
   * @param string $direction
   *   Direction.
   * @param \Drupal\ldap_servers\Entity\Server $server
   *   Server.
   *
   * @return array
   *   All attributes.
   */
  public function loadAttributes(string $direction, Server $server): array {
    $this->server = $server;
    $this->direction = $direction;
    if ($this->direction === self::PROVISION_TO_DRUPAL) {
      $this->addDn();

      if ($this->server->getUniquePersistentAttribute()) {
        $this->addPuidFields();
      }

      $triggers = $this->config->get('drupalAcctProvisionTriggers');
      if (!empty($triggers)) {
        $this->addBaseProperties();
      }
    }

    if ($direction === self::PROVISION_TO_LDAP) {
      $this->addToLdapProvisioningFields();
    }

    $this->addUserEntityFields();
    $this->exposeAvailableBaseFields();

    $this->loadUserDefinedMappings();

    return $this->attributes;
  }

  /**
   * Load user-defined mappings from database configuration.
   */
  private function loadUserDefinedMappings(): void {
    $database_mappings = $this->config->get('ldapUserSyncMappings');
    // Leave early if there are no user mappings.
    if (!isset($database_mappings[$this->direction]) || empty($database_mappings[$this->direction])) {
      return;
    }

    foreach ($database_mappings[$this->direction] as $id => $mapping) {
      if (isset($this->attributes[$mapping['user_attr']])) {
        $label = $this->attributes[$mapping['user_attr']]->getLabel();
      }
      $prepared_mapping = new Mapping(
        $id,
        $label ?? $id,
        TRUE,
        TRUE,
        $mapping['prov_events'],
        $mapping['config_module'],
        $mapping['prov_module']
      );
      $prepared_mapping->setDrupalAttribute($mapping['user_attr']);
      $prepared_mapping->setLdapAttribute($mapping['ldap_attr']);
      $prepared_mapping->setUserTokens($mapping['user_tokens']);
      if ($mapping['convert']) {
        $prepared_mapping->convertBinary($mapping['convert']);
      }
      // This is an unideal solution to the mappings being keyed on name.
      // @todo Replace with a plain array in the configuration storage.
      $key = $this->direction === self::PROVISION_TO_DRUPAL ? $mapping['user_attr'] : $mapping['ldap_attr'];
      $this->attributes[$key] = $prepared_mapping;
    }
  }

  /**
   * Attribute is synced on event.
   *
   * @param string $name
   *   Field name.
   * @param string $event
   *   Event.
   *
   * @return bool
   *   Is synced.
   */
  public function attributeIsSyncedOnEvent(string $name, string $event): bool {
    if (
      isset($this->attributes[$name]) &&
      $this->attributes[$name]->isEnabled() &&
      in_array($event, $this->attributes[$name]->getProvisioningEvents(), TRUE)
    ) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get Attributes synced on event.
   *
   * @param string $event
   *   Event.
   *
   * @return \Drupal\ldap_servers\Mapping[]
   *   Mapping.
   */
  public function getAttributesSyncedOnEvent(string $event): array {
    $synced_attributes = [];
    foreach ($this->attributes as $key => $attribute) {
      if ($attribute->isEnabled() &&
        in_array($event, $attribute->getProvisioningEvents(), TRUE)) {
        $synced_attributes[$key] = $attribute;
      }
    }
    return $synced_attributes;
  }

  /**
   * Get configurable attributes synced on event.
   *
   * @param string $event
   *   Event.
   *
   * @return \Drupal\ldap_servers\Mapping[]
   *   Mapping.
   */
  public function getConfigurableAttributesSyncedOnEvent(string $event): array {
    $synced_attributes = [];
    foreach ($this->attributes as $key => $attribute) {
      if ($attribute->isEnabled() &&
        $attribute->isConfigurable() &&
        in_array($event, $attribute->getProvisioningEvents(), TRUE)) {
        $synced_attributes[$key] = $attribute;
      }
    }
    return $synced_attributes;
  }

  /**
   * Add PUID Fields.
   *
   * These 4 user fields identify where in LDAP and which LDAP server they
   * are associated with. They are required for a Drupal account to be
   * "LDAP associated" regardless of if any other fields/properties are
   * provisioned or synced.
   */
  private function addPuidFields(): void {
    $url = Url::fromRoute('entity.ldap_server.collection');
    // A plain $url->toString() call in some places (early in the request)
    // can cause Drupal to throw a 'leaked metadata' exception. To prevent
    // toString() from handling any metadata in the background, we pass TRUE.
    $url_string = $url->toString(TRUE)->getGeneratedUrl();
    $tokens = [
      '%edit_link' => Link::fromTextAndUrl($url_string, $url)->toString(),
      '%sid' => $this->server->id(),
    ];

    $fields = [
      '[field.ldap_user_puid_sid]' => $this->t('Field: sid providing PUID'),
      '[field.ldap_user_puid]' => $this->t('Field: PUID'),
      '[field.ldap_user_puid_property]' => $this->t('Field: PUID Attribute'),
    ];
    foreach ($fields as $key => $name) {
      $this->attributes[$key] = new Mapping(
        $key,
        (string) $name,
        FALSE,
        TRUE,
        [self::EVENT_CREATE_DRUPAL_USER],
        'ldap_user',
        'ldap_servers'
      );
      $this->attributes[$key]->setNotes((string) $this->t('configure at %edit_link', $tokens));
    }

    $this->attributes['[field.ldap_user_puid_sid]']->setLdapAttribute($this->server->id());
    $this->attributes['[field.ldap_user_puid]']->setLdapAttribute($this->addTokens($this->server->getUniquePersistentAttribute()));
    $this->attributes['[field.ldap_user_puid_property]']->setLdapAttribute($this->server->getUniquePersistentAttribute());
  }

  /**
   * Add base properties.
   */
  private function addBaseProperties(): void {
    $fields = [
      '[property.name]' => 'Property: Username',
      '[property.mail]' => 'Property: Email',
    ];

    if ($this->server->getPictureAttribute()) {
      $fields['[property.picture]'] = 'Property: Picture';
    }

    foreach ($fields as $key => $name) {
      $this->attributes[$key] = new Mapping(
        $key,
        $name,
        FALSE,
        TRUE,
        [self::EVENT_CREATE_DRUPAL_USER, self::EVENT_SYNC_TO_DRUPAL_USER],
        'ldap_servers',
        'ldap_user'
      );
    }

    $this->attributes['[property.name]']->setLdapAttribute($this->addTokens($this->server->getAuthenticationNameAttribute()));

    if ($this->server->getMailTemplate()) {
      $this->attributes['[property.mail]']->setLdapAttribute($this->server->getMailTemplate());
    }
    else {
      $this->attributes['[property.mail]']->setLdapAttribute($this->addTokens($this->server->getMailAttribute()));
    }

    if ($this->server->getPictureAttribute()) {
      $this->attributes['[property.picture]']->setLdapAttribute($this->addTokens($this->server->getPictureAttribute()));
    }
  }

  /**
   * Add tokens.
   *
   * @param string $input
   *   Field name.
   *
   * @return string
   *   Tokenized.
   */
  private function addTokens(string $input): string {
    return '[' . $input . ']';
  }

  /**
   * Add DN.
   */
  private function addDn(): void {
    $this->attributes['[field.ldap_user_current_dn]'] = new Mapping(
      '[field.ldap_user_current_dn]',
      (string) $this->t('Field: Most Recent DN'),
      FALSE,
      TRUE,
      [self::EVENT_CREATE_DRUPAL_USER, self::EVENT_SYNC_TO_DRUPAL_USER],
      'ldap_user',
      'ldap_servers'
    );
    $this->attributes['[field.ldap_user_current_dn]']->setLdapAttribute('[dn]');
    $this->attributes['[field.ldap_user_current_dn]']->setNotes('not configurable');
  }

  /**
   * Add to LDAP Provisioning fields.
   */
  private function addToLdapProvisioningFields(): void {
    if (isset($this->attributes['[property.name]'])) {
      $this->attributes['[property.name]']->setConfigurationModule('ldap_user');
      $this->attributes['[property.name]']->setConfigurable(TRUE);
    }

    $fields = [
      '[property.name]' => 'Property: Name',
      '[property.mail]' => 'Property: Email',
      '[property.picture]' => 'Property: Picture',
      '[property.uid]' => 'Property: Drupal User Id (uid)',
      '[password.random]' => 'Password: Random password',
      '[password.user-random]' => 'Password: Plain user password or random',
      '[password.user-only]' => 'Password: Plain user password',
    ];

    foreach ($fields as $key => $name) {
      if (isset($this->attributes[$key])) {
        $this->attributes[$key]->setConfigurationModule('ldap_user');
        $this->attributes[$key]->setConfigurable(TRUE);
      }
      else {
        $this->attributes[$key] = new Mapping(
          $key,
          $name,
          TRUE,
          FALSE,
          [
            self::EVENT_CREATE_LDAP_ENTRY,
            self::EVENT_SYNC_TO_LDAP_ENTRY,
          ],
          'ldap_user',
          'ldap_user'
        );
      }
    }
  }

  /**
   * Additional access needed in direction to Drupal.
   */
  private function exposeAvailableBaseFields(): void {
    $this->server = $this->config->get('drupalAcctProvisionServer');
    $triggers = $this->config->get('drupalAcctProvisionTriggers');
    if ($this->server && !empty($triggers)) {
      /** @var \Drupal\ldap_servers\Mapping availableUserAttributes<> */
      $fields = [
        '[property.mail]',
        '[property.name]',
        '[property.picture]',
        '[field.ldap_user_puid_sid]',
        '[field.ldap_user_puid]',
      ];
      foreach ($fields as $field) {
        if (isset($this->attributes[$field])) {
          $this->attributes[$field]->setConfigurationModule('ldap_user');
        }
      }
    }
  }

  /**
   * Add user entity fields.
   */
  private function addUserEntityFields(): void {
    // Drupal user properties.
    $this->attributes['[property.status]'] = new Mapping(
      '[property.status]',
      'Property: Account Status',
      TRUE,
      FALSE,
      [],
       'ldap_user',
       'ldap_user'
    );

    $this->attributes['[property.timezone]'] = new Mapping(
      '[property.timezone]',
      'Property: User Timezone',
      TRUE,
      FALSE,
      [],
      'ldap_user',
      'ldap_user'
    );

    $this->attributes['[property.signature]'] = new Mapping(
      '[property.signature]',
      'Property: User Signature',
      TRUE,
      FALSE,
      [],
      'ldap_user',
      'ldap_user'
    );

    // Load the remaining active unmanaged Drupal fields.
    // @todo Consider not hard-coding the other properties.
    $user_fields = $this->entityFieldManager
      ->getFieldStorageDefinitions('user');
    foreach ($user_fields as $field_name => $field_instance) {
      $field_id = sprintf('[field.%s]', $field_name);
      if (!isset($this->attributes[$field_id])) {
        $this->attributes[$field_id] = new Mapping(
          $field_id,
          (string) $this->t('Field: @label', ['@label' => $field_instance->getLabel()]),
          TRUE,
          FALSE,
          [],
          'ldap_user',
          'ldap_user'
        );
      }
    }
  }

}
