<?php

namespace Drupal\bootstrap\Annotation;

use Drupal\Component\Annotation\Doctrine\AnnotationException;
use Drupal\Component\Annotation\AnnotationBase;

/**
 * Defines a BootstrapConstant annotation object.
 *
 * @Annotation
 *
 * @ingroup utility
 */
class BootstrapConstant extends AnnotationBase {

  /**
   * The stored constant value.
   *
   * @var mixed
   */
  protected $value;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values) {
    $string = (string) $values['value'];

    // Handle classes.
    if (strpos($string, '::') !== FALSE) {
      [$class, $constant] = explode('::', $string);
      try {
        $reflection = new \ReflectionClass($class);
        if ($reflection->hasConstant($constant)) {
          $this->value = $reflection->getConstant($constant);
          return;
        }
      }
      catch (\ReflectionException $e) {
      }
    }

    // Handle procedural constants.
    if (!$this->value && defined($string)) {
      $this->value = constant($string);
      return;
    }

    throw AnnotationException::semanticalErrorConstants($string);
  }

  /**
   * {@inheritdoc}
   */
  public function get() {
    return $this->value;
  }

}
