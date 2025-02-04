<?php

declare(strict_types=1);

namespace Drupal\ldap_servers;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ldap_servers\Entity\Server;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Server entities.
 */
class ServerListBuilder extends ConfigEntityListBuilder {

  /**
   * The LDAP Bridge.
   *
   * @var \Drupal\ldap_servers\LdapBridgeInterface
   */
  protected $ldapBridge;

  /**
   * The Ldap Server ListBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   Entity type.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   Entity storage.
   * @param \Drupal\ldap_servers\LdapBridgeInterface $ldap_bridge
   *   LDAP Bridge.
   */
  final public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, LdapBridgeInterface $ldap_bridge) {
    $this->entityTypeId = $entity_type->id();
    $this->storage = $storage;
    $this->entityType = $entity_type;
    $this->ldapBridge = $ldap_bridge;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new self(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('ldap.bridge')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Building the header and content lines for the server list.
   *
   * Calling the parent::buildHeader() adds a column for the possible actions
   * and inserts the 'edit' and 'delete' links as defined for the entity type.
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Name');
    $header['bind_method'] = $this->t('Method');
    $header['binddn'] = $this->t('Account');
    $header['status'] = $this->t('Enabled');
    $header['address'] = $this->t('Server address');
    $header['port'] = $this->t('Server port');
    $header['current_status'] = $this->t('Server reachable');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\ldap_servers\ServerInterface $entity_with_overrides */
    $entity_with_overrides = $entity;
    /** @var \Drupal\ldap_servers\ServerInterface $entity */
    $entity = $this->storage->load($entity->id());

    $row = [];
    $row['label'] = $entity->label();
    $row['bind_method'] = ucfirst((string) $entity->getFormattedBind());
    if ($entity->get('bind_method') === 'service_account') {
      $row['binddn'] = $entity->get('binddn');
    }
    else {
      $row['binddn'] = $this->t('N/A');
    }
    $row['status'] = $entity->get('status') ? 'Yes' : 'No';
    $row['address'] = $entity->get('address');
    $row['port'] = $entity->get('port');
    $row['current_status'] = $this->checkStatus($entity);

    $fields = [
      'bind_method',
      'binddn',
      'status',
      'address',
      'port',
    ];

    foreach ($fields as $field) {
      if ($entity->get($field) !== $entity_with_overrides->get($field)) {
        $row[$field] .= ' ' . $this->t('(overridden)');
      }
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * Format a server status response.
   *
   * @param \Drupal\ldap_servers\Entity\Server $server
   *   Server.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The status string.
   */
  private function checkStatus(Server $server): TranslatableMarkup {
    $this->ldapBridge->setServer($server);

    if ($server->get('status')) {
      if ($this->ldapBridge->bind()) {
        $result = $this->t('Server available');
      }
      else {
        $result = $this->t('Binding issues, please see log.');
      }
    }
    else {
      $result = $this->t('Deactivated');
    }

    return $result;
  }

  /**
   * Get Operations.
   *
   * @param \Drupal\Core\Entity\EntityInterface|\Drupal\ldap_servers\ServerInterface $entity
   *   Entity interface.
   *
   * @return array
   *   Available operations in dropdown.
   */
  public function getOperations(EntityInterface $entity): array {
    /** @var \Drupal\ldap_servers\ServerInterface $entity */
    $operations = $this->getDefaultOperations($entity);
    if (!isset($operations['test'])) {
      $operations['test'] = [
        'title' => $this->t('Test'),
        'weight' => 10,
        'url' => Url::fromRoute('entity.ldap_server.test_form', ['ldap_server' => $entity->id()]),
      ];
    }
    if ($entity->get('status')) {
      $operations['disable'] = [
        'title' => $this->t('Disable'),
        'weight' => 15,
        'url' => Url::fromRoute('entity.ldap_server.enable_disable_form', ['ldap_server' => $entity->id()]),
      ];
    }
    else {
      $operations['enable'] = [
        'title' => $this->t('Enable'),
        'weight' => 15,
        'url' => Url::fromRoute('entity.ldap_server.enable_disable_form', ['ldap_server' => $entity->id()]),
      ];
    }
    return $operations;
  }

}
