<?php

declare(strict_types = 1);

namespace Drupal\ldap_servers;

/**
 * Interface for the synchronization mappings ldap_user provides.
 */
class Mapping {

  /**
   * ID.
   *
   * @var string
   */
  private $id;

  /**
   * Label.
   *
   * @var string
   */
  private $label;

  /**
   * Configurable.
   *
   * @var bool
   */
  private $configurable;

  /**
   * Binary.
   *
   * @var bool
   */
  private $binary = FALSE;

  /**
   * Notes.
   *
   * @var string
   */
  private $notes;

  /**
   * Enabled.
   *
   * @var bool
   */
  private $enabled;

  /**
   * Provisioning events.
   *
   * @var array
   */
  private $provisioningEvents;

  /**
   * Configuration module.
   *
   * @var string
   */
  private $configurationModule;

  /**
   * Provisioning module.
   *
   * @var string
   */
  private $provisioningModule;

  /**
   * LDAP attribute.
   *
   * @var string
   */
  private $ldapAttribute = '';

  /**
   * Drupal attribute.
   *
   * @var string
   */
  private $drupalAttribute = '';

  /**
   * User tokens.
   *
   * @var string
   */
  private $userTokens = '';

  /**
   * Mapping constructor.
   *
   * @param string $id
   *   ID.
   * @param string $label
   *   Label.
   * @param bool $configurable
   *   Configurable.
   * @param bool $enabled
   *   Enabled.
   * @param array $provisioning_events
   *   Provisioning events.
   * @param string $configuration_module
   *   Configuration module.
   * @param string $provisioning_module
   *   Provisioning module.
   */
  public function __construct(
    string $id,
    string $label = '',
    bool $configurable = FALSE,
    bool $enabled = FALSE,
    array $provisioning_events = [],
    string $configuration_module = '',
    string $provisioning_module = ''
  ) {
    $this->id = $id;
    $this->label = $label;
    $this->configurable = $configurable;
    $this->enabled = $enabled;
    $this->provisioningEvents = $provisioning_events;
    $this->configurationModule = $configuration_module;
    $this->provisioningModule = $provisioning_module;
  }

  /**
   * Serialized data.
   *
   * @return array
   *   Data.
   */
  public function serialize(): array {
    return [
      'ldap_attr' => $this->getLdapAttribute(),
      'user_attr' => $this->getDrupalAttribute(),
      'convert' => $this->isBinary(),
      'user_tokens' => $this->getUserTokens(),
      'config_module' => $this->getConfigurationModule(),
      'prov_module' => $this->getProvisioningModule(),
      'prov_events' => $this->getProvisioningEvents(),
    ];
  }

  /**
   * Get label.
   *
   * @return null|string
   *   Label.
   */
  public function getLabel(): ?string {
    return $this->label;
  }

  /**
   * Set label.
   *
   * @param string $label
   *   Label.
   */
  public function setLabel(string $label): void {
    $this->label = $label;
  }

  /**
   * Is configurable.
   *
   * @return bool
   *   Configurable.
   */
  public function isConfigurable(): bool {
    return $this->configurable;
  }

  /**
   * Get notes.
   *
   * @return null|string
   *   Notes.
   */
  public function getNotes(): ?string {
    return $this->notes;
  }

  /**
   * Set Notes.
   *
   * @param string $notes
   *   Notes.
   */
  public function setNotes(string $notes): void {
    $this->notes = $notes;
  }

  /**
   * Is enabled.
   *
   * @return bool
   *   Enabled.
   */
  public function isEnabled(): bool {
    return $this->enabled;
  }

  /**
   * Set enabled.
   *
   * @param bool $enabled
   *   Enabled.
   */
  public function setEnabled(bool $enabled): void {
    $this->enabled = $enabled;
  }

  /**
   * Get provisioning events.
   *
   * @return array
   *   Events.
   */
  public function getProvisioningEvents(): array {
    return $this->provisioningEvents;
  }

  /**
   * Provisioning event available?
   *
   * @param string $event
   *   Event.
   *
   * @return bool
   *   Available.
   */
  public function hasProvisioningEvent(string $event): bool {
    return in_array($event, $this->provisioningEvents, TRUE);
  }

  /**
   * Set provisioning events.
   *
   * @param array $events
   *   Provisioning vents.
   */
  public function setProvisioningEvents(array $events): void {
    $this->provisioningEvents = $events;
  }

  /**
   * Get configuration module.
   *
   * @return string
   *   Module.
   */
  public function getConfigurationModule(): string {
    return $this->configurationModule;
  }

  /**
   * Set configuration module.
   *
   * @param string $configurationModule
   *   Module.
   */
  public function setConfigurationModule(string $configurationModule): void {
    $this->configurationModule = $configurationModule;
  }

  /**
   * Get provisioning module.
   *
   * @return string
   *   Module.
   */
  public function getProvisioningModule(): string {
    return $this->provisioningModule;
  }

  /**
   * Set provisioning module.
   *
   * @param string $provisioningModule
   *   Module.
   */
  public function setProvisioningModule(string $provisioningModule): void {
    $this->provisioningModule = $provisioningModule;
  }

  /**
   * Get LDAP attribute.
   *
   * @return string
   *   LDAP attribute.
   */
  public function getLdapAttribute(): string {
    return $this->ldapAttribute;
  }

  /**
   * Set LDAP attribute.
   *
   * @param string $ldapAttribute
   *   LDAP attribute.
   */
  public function setLdapAttribute(string $ldapAttribute): void {
    $this->ldapAttribute = $ldapAttribute;
  }

  /**
   * Get Drupal attribute.
   *
   * @return string
   *   Drupal attribute.
   */
  public function getDrupalAttribute(): string {
    return $this->drupalAttribute;
  }

  /**
   * Set Drupal attribute.
   *
   * @param string $drupalAttribute
   *   Drupal attribute.
   */
  public function setDrupalAttribute(string $drupalAttribute): void {
    $this->drupalAttribute = $drupalAttribute;
  }

  /**
   * Get ID.
   *
   * @return string
   *   ID.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * Get user tokens.
   *
   * @return string
   *   Tokens.
   */
  public function getUserTokens(): string {
    return $this->userTokens;
  }

  /**
   * Set user tokens.
   *
   * @param string $userTokens
   *   Token.
   */
  public function setUserTokens(string $userTokens): void {
    $this->userTokens = $userTokens;
  }

  /**
   * Mapping is binary.
   *
   * @return bool
   *   Binary.
   */
  public function isBinary(): bool {
    return $this->binary;
  }

  /**
   * Mapping set is binary.
   *
   * @todo improve syntax.
   *
   * @param bool $binary
   *   Is binary.
   */
  public function convertBinary(bool $binary): void {
    $this->binary = $binary;
  }

  /**
   * Mapping set is configurable.
   *
   * @todo improve syntax.
   *
   * @param bool $configurable
   *   Is configurable.
   */
  public function setConfigurable(bool $configurable): void {
    $this->configurable = $configurable;
  }

}
