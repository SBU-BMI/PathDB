<?php

namespace Drupal\http_response_headers\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\Markup;

/**
 * Provides a listing of Response Headers.
 */
class ResponseHeaderListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['description'] = $this->t('Description');
    $header['name'] = $this->t('Name');
    $header['value'] = $this->t('Value');
    $header['conditions'] = $this->t('Conditions');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $is_restricted = empty($entity->get('visibility'));
    $row['label'] = $entity->label();
    $row['description'] = Markup::create('<em class="http-response-headers-description">' . $entity->get('description') . '</em>');
    $row['name'] = $entity->get('name');
    $row['value'] = ($value = $entity->get('value')) ? mb_strimwidth($value, 0, 50, '...') : ' - ';
    $row['conditions'] = Markup::create('<span class="views-field"><span class="marker marker--'
      . ($is_restricted ? 'site-wide' : 'restricted')  . '">'
      . ($is_restricted
        ? $this->t('Sitewide')
        : $this->t('Restricted')) . '</span></span>');
    $row['status'] = Markup::create('<span class="views-field">' . ($entity->get('status')
        ? '<span class="marker marker--published">' . $this->t('Active') . '</span>'
        : '<span class="marker">' . $this->t('Disabled') . '</span>') . '</span>');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    if (!empty($build['table']['#rows'])) {
      $build['#attached']['library'][] = 'http_response_headers/http_response_headers.admin';
      $build['table']['#attributes']['class'][] = 'http-response-headers-list';
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    if ($entity->access('edit http response headers')) {
      if ($entity->isPublished()) {
        $operations['disable'] = [
          'title' => $this->t('Disable'),
          'url' => $this->ensureDestination($entity->toUrl('disable')),
        ];
      }
      else {
        $operations['enable'] = [
          'title' => $this->t('Enable'),
          'url' => $this->ensureDestination($entity->toUrl('enable')),
        ];
      }
    }
    return $operations;
  }

}
