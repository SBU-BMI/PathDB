<?php

declare(strict_types = 1);

namespace Drupal\ldap_servers_dummy;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers\LdapBridgeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Ldap\LdapInterface;

/**
 * Fake LdapBridge to instantiate a fake server for testing.
 */
class FakeBridge implements LdapBridgeInterface {

  /**
   * LDAP.
   *
   * @var \Drupal\ldap_servers_dummy\FakeLdap
   */
  protected $ldap;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Entity Storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityManager;

  /**
   * Bind result.
   *
   * @var bool
   */
  protected $bindResult = TRUE;

  /**
   * Constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   */
  public function __construct(LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager) {
    $this->logger = $logger;
    $this->entityManager = $entity_type_manager->getStorage('ldap_server');
  }

  /**
   * {@inheritdoc}
   */
  public function setServerById(string $sid): void {
    $server = $this->entityManager->load($sid);
    /** @var \Drupal\ldap_servers\Entity\Server $server */
    if ($server) {
      $this->setServer($server);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setServer(Server $server): void {
    if (!$this->ldap) {
      $this->ldap = new FakeLdap();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function bind(): bool {
    $this->ldap->bind();
    return $this->bindResult;
  }

  /**
   * Set bind result.
   *
   * @param bool $bindResult
   *   Result.
   */
  public function setBindResult(bool $bindResult): void {
    $this->bindResult = $bindResult;
  }

  /**
   * {@inheritdoc}
   */
  public function get(): LdapInterface {
    return $this->ldap;
  }

}
