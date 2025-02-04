<?php

namespace Drupal\taxonomy_unique\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Drupal\taxonomy_unique\TaxonomyUniqueConstants;

/**
 * Taxonomy unique constraint.
 *
 * @Constraint(
 *   id = "taxonomy_unique",
 *   label = @Translation("Taxonomy unique", context = "Validation")
 * )
 */
class TaxonomyUnique extends Constraint {

  /**
   * The default error text to inform the term is not unique.
   *
   * @var string
   */
  public $notUnique = TaxonomyUniqueConstants::NOT_UNIQUE_DEFAULT_ERROR_MESSAGE;

  /**
   * Overwrites the default error message.
   */
  public function setErrorMessage($message) {
    $this->notUnique = $message;
  }

}
