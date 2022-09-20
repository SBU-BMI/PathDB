<?php

declare(strict_types = 1);

namespace Drupal\authorization;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Authorization profile entities.
 */
class AuthorizationProfileListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Profile');
    $header['provider'] = $this->t('Provider');
    $header['consumer'] = $this->t('Consumer');
    $header['enabled'] = $this->t('Enabled');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row['label'] = $entity->label();
    // @todo Abstract get[Provider|Consumer]Options() from the form into Entity
    // or as a trait so we can display the label of them here instead of the
    // machine name.
    $row['provider'] = $entity->get('provider');
    $row['consumer'] = $entity->get('consumer');
    $row['enabled'] = $entity->get('status') ? 'Yes' : 'No';
    return $row + parent::buildRow($entity);
  }

}
