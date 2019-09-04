<?php

namespace Drupal\ldap_servers;

use Drupal\Core\Url;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\ldap_servers\Entity\Server;

/**
 * Provides a listing of Server entities.
 */
class ServerListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   *
   * Building the header and content lines for the server list.
   *
   * Calling the parent::buildHeader() adds a column for the possible actions
   * and inserts the 'edit' and 'delete' links as defined for the entity type.
   */
  public function buildHeader() {
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
  public function buildRow(EntityInterface $entity) {
    $server = Server::load($entity->id());

    $row = [];
    $row['label'] = $this->getLabel($entity);
    $row['bind_method'] = ucfirst($server->getFormattedBind());
    if ($server->get('bind_method') == 'service_account') {
      $row['binddn'] = $server->get('binddn');
    }
    else {
      $row['binddn'] = $this->t('N/A');
    }
    $row['status'] = $server->get('status') ? 'Yes' : 'No';
    $row['address'] = $server->get('address');
    $row['port'] = $server->get('port');
    $row['current_status'] = $this->checkStatus($server);

    $fields = [
      'bind_method',
      'binddn',
      'status',
      'address',
      'port',
    ];

    foreach ($fields as $field) {
      if ($entity->get($field) != $server->get($field)) {
        $row[$field] .= ' ' . $this->t('(overridden)');
      }
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * Format a server status response.
   *
   * @param string $server
   *   Server.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The status string.
   */
  private function checkStatus($server) {
    $connection_result = $server->connect();
    if ($server->get('status')) {
      if ($connection_result == Server::LDAP_SUCCESS) {
        $bind_result = $server->bind();
        if ($bind_result == Server::LDAP_SUCCESS) {
          return t('Server available');
        }
        else {
          return t('Configuration valid, bind failed.');
        }
      }
      else {
        return t('Configuration invalid, cannot connect.');
      }
    }
    else {
      return t('Deactivated');
    }
  }

  /**
   * Get Operations.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity interface.
   *
   * @return array
   *   Available operations in dropdown.
   */
  public function getOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    if (!isset($operations['test'])) {
      $operations['test'] = [
        'title' => $this->t('Test'),
        'weight' => 10,
        'url' => Url::fromRoute('entity.ldap_server.test_form', ['ldap_server' => $entity->id()]),
      ];
    }
    if ($entity->get('status') == 1) {
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
