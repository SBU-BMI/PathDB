<?php

namespace Drupal\taxonomy_unique\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

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
   * The TAXONOMY_UNIQUE_DEFAULT_MESSAGE constant.
   *
   * @var string
   */
  public $notUnique = TAXONOMY_UNIQUE_DEFAULT_MESSAGE;

  /**
   * Overwrites the default error message.
   */
  public function setErrorMessage($message) {
    $this->notUnique = $message;
  }

}
