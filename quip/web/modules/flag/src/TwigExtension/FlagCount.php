<?php

namespace Drupal\flag\TwigExtension;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Template\TwigExtension;
use Drupal\flag\FlagInterface;

/**
 * Provides a Twig extension to get the flag count given a flag and flaggable.
 */
class FlagCount extends TwigExtension {

  /**
   * Generates a list of all Twig functions that this extension defines.
   */
  public function getFunctions() {
    return [
      new \Twig_SimpleFunction('flagcount', [$this, 'count'], ['is_safe' => ['html']]),
    ];
  }

  /**
   * Gets a unique identifier for this Twig extension.
   */
  public function getName() {
    return 'flag.twig.count';
  }

  /**
   * Gets the number of flaggings for the given flag and flaggable.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag entity.
   * @param \Drupal\Core\Entity\EntityInterface $flaggable
   *   The flaggable entity.
   *
   * @return string
   *   The number of times the flaggings for the given parameters.
   */
  public static function count(FlagInterface $flag, EntityInterface $flaggable) {
    $counts = \Drupal::service('flag.count')->getEntityFlagCounts($flaggable);
    return empty($counts) || !isset($counts[$flag->id()]) ? '0' : $counts[$flag->id()];
  }

}
