<?php

namespace Drupal\ldap_query;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\ldap_servers\Entity\Server;

/**
 * Provides a listing of LDAP Queries entities.
 */
class QueryEntityListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('LDAP Query');
    $header['server_id'] = $this->t('Server');
    $header['status'] = $this->t('Enabled');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $server = Server::load($entity->get('server_id'));
    $row['server_id'] = $server->label();
    $row['status'] = $entity->get('status') ? $this->t('Enabled') : $this->t('Disabled');
    return $row + parent::buildRow($entity);
  }

}
