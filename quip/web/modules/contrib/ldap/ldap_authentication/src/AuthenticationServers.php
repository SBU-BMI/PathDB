<?php

declare(strict_types=1);

namespace Drupal\ldap_authentication;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Authentication serves.
 */
class AuthenticationServers {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructs a new AuthenticationServers object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->config            = $config_factory->get('ldap_authentication.settings');
  }

  /**
   * Authentication servers available.
   *
   * @return bool
   *   Available.
   */
  public function authenticationServersAvailable(): bool {
    if (empty($this->getAvailableAuthenticationServers())) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Get available authentication servers.
   *
   * @return array
   *   Server IDs.
   */
  public function getAvailableAuthenticationServers(): array {
    /** @var array $available_servers */
    $available_servers = $this->entityTypeManager
      ->getStorage('ldap_server')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->execute();

    $result = [];
    foreach ($this->config->get('sids') as $configured_server) {
      if (isset($available_servers[$configured_server])) {
        $result[] = $configured_server;
      }
    }
    return $result;
  }

}
