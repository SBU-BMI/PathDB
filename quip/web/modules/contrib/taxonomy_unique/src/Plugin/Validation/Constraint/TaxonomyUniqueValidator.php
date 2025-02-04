<?php

namespace Drupal\taxonomy_unique\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Drupal\taxonomy_unique\TaxonomyUniqueConstants;

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
    /** @var \Drupal\taxonomy\VocabularyInterface $vocabulary */
    $vocabulary = $this
      ->entityTypeManager
      ->getStorage('taxonomy_vocabulary')
      ->load($term->bundle());

    if ($vocabulary->getThirdPartySetting('taxonomy_unique', 'enabled') && !$this->taxonomyUniqueManager->isUnique($term)) {
      $message = $vocabulary->getThirdPartySetting('taxonomy_unique', 'message', TaxonomyUniqueConstants::NOT_UNIQUE_DEFAULT_ERROR_MESSAGE);
      if ($message != '') {
        $constraint->setErrorMessage($message);
      }
      $this->context->addViolation($constraint->notUnique, ['%term' => $term->getName(), '%vocabulary' => $vocabulary->label()]);
    }
  }

}
