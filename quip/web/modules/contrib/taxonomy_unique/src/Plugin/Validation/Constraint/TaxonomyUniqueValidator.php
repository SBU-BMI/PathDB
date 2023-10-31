<?php

namespace Drupal\taxonomy_unique\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Taxonomy unique constraint validator.
 */
class TaxonomyUniqueValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The taxonomy unique manager.
   *
   * @var \Drupal\taxonomy_unique\TaxonomyUniqueManager
   */
  protected $taxonomyUniqueManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $taxonomy_unique_validator = new static();
    $taxonomy_unique_validator->taxonomyUniqueManager = $container->get('taxonomy_unique.manager');
    $taxonomy_unique_validator->entityTypeManager = $container->get('entity_type.manager');
    return $taxonomy_unique_validator;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var \Drupal\taxonomy\TermInterface $term */
    $term = $value->getEntity();
    if (\Drupal::config('taxonomy_unique.settings')->get($term->bundle()) && !$this->taxonomyUniqueManager->isUnique($term)) {
      $message = \Drupal::config('taxonomy_unique.settings')->get($term->bundle() . '_message');
      if ($message != '') {
        $constraint->setErrorMessage($message);
      }
      $vocabulary = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->load($term->bundle());
      $this->context->addViolation($constraint->notUnique, ['%term' => $term->getName(), '%vocabulary' => $vocabulary->label()]);
    }
  }

}
