<?php

namespace Drupal\taxonomy_unique\Plugin\Validation\Constraint;

use Drupal\taxonomy\TermInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Taxonomy unique constraint validator.
 */
class TaxonomyUniqueValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var TermInterface $term */
    $term = $value->getEntity();
    if (\Drupal::config('taxonomy_unique.settings')->get($term->bundle()) && !$this->isUnique($term)) {
      $message = \Drupal::config('taxonomy_unique.settings')->get($term->bundle() . '_message');
      if ($message != '') {
        $constraint->setErrorMessage($message);
      }
      $this->context->addViolation($constraint->notUnique, ['%term' => $term->getName(), '%vocabulary' => $term->bundle()]);
    }
  }

  /**
   * Checks if given term is unique inside its vocabulary.
   *
   * @param TermInterface $term
   *   The term to check.
   *
   * @return bool
   *   Whether the term is unique or not
   */
  private function isUnique(TermInterface $term) {
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => $term->getName(), 'vid' => $term->bundle()]);

    if (empty($terms)) {
      return TRUE;
    }

    if (count($terms) == 1) {
      $found_term = current($terms);
      if ($found_term->id() == $term->id()) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
