<?php

namespace Drupal\file_replace\Plugin\views\field;

use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\field\EntityLink;

/**
 * Field handler to present a link to replace a file.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("file_replace_link")
 */
class FileLinkReplace extends EntityLink {

  /**
   * {@inheritdoc}
   */
  protected function getEntityLinkTemplate() {
    return 'replace-form';
  }

  /**
   * {@inheritdoc}
   */
  protected function renderLink(ResultRow $row) {
    $this->options['alter']['query'] = $this->getDestinationArray();
    return parent::renderLink($row);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultLabel() {
    return $this->t('replace');
  }

}
