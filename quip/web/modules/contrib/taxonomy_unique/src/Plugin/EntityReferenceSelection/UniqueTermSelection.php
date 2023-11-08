<?php

namespace Drupal\taxonomy_unique\Plugin\EntityReferenceSelection;

use Drupal\taxonomy\Plugin\EntityReferenceSelection\TermSelection;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Prevents duplicate terms from being auto-created.
 *
 * @EntityReferenceSelection(
 *   id = "taxonomy_unique",
 *   label = @Translation("Unique taxonomy term selection"),
 *   entity_types = {"taxonomy_term"},
 *   group = "taxonomy_unique",
 *   weight = 2
 * )
 */
class UniqueTermSelection extends TermSelection {

  /**
   * The taxonomy unique manager.
   *
   * @var \Drupal\taxonomy_unique\TaxonomyUniqueManager
   */
  protected $taxonomyUniqueManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $unique_term_selection = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $unique_term_selection->taxonomyUniqueManager = $container->get('taxonomy_unique.manager');
    return $unique_term_selection;
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableNewEntities(array $entities) {
    return array_filter(parent::validateReferenceableNewEntities($entities), function (TermInterface $term) {
      return $this->taxonomyUniqueManager->isUnique($term);
    });
  }

}
