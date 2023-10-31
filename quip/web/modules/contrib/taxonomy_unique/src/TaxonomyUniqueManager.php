<?php

namespace Drupal\taxonomy_unique;

use Drupal\taxonomy\TermInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * The taxonomy unique manager.
 */
class TaxonomyUniqueManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * TaxonomyUniqueManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Checks if given term is unique inside its vocabulary.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The term to check.
   *
   * @return bool
   *   Whether the term is unique or not
   */
  public function isUnique(TermInterface $term) {
    $term_ids = $this
      ->entityTypeManager
      ->getStorage('taxonomy_term')
      ->getQuery()
      ->accessCheck()
      ->condition('vid', $term->bundle())
      ->condition('name', $term->getName())
      ->condition('langcode', $term->language()->getId())
      ->addTag('taxonomy_unique')
      ->addMetaData('term', $term)
      ->execute();

    if (empty($term_ids)) {
      return TRUE;
    }

    if (count($term_ids) == 1) {
      $found_term_id = current($term_ids);
      if ($found_term_id == $term->id()) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
