<?php

declare(strict_types=1);

namespace Drupal\typed_data;

/**
 * Helper for classes that need the placeholder resolver.
 */
trait PlaceholderResolverTrait {

  /**
   * The placeholder resolver.
   *
   * @var \Drupal\typed_data\PlaceholderResolverInterface|null
   */
  protected $placeholderResolver;

  /**
   * Sets the placeholder resolver.
   *
   * @param \Drupal\typed_data\PlaceholderResolverInterface $placeholder_resolver
   *   The placeholder resolver.
   *
   * @return $this
   */
  public function setPlaceholderResolver(PlaceholderResolverInterface $placeholder_resolver): static {
    $this->placeholderResolver = $placeholder_resolver;
    return $this;
  }

  /**
   * Gets the placeholder resolver.
   *
   * @return \Drupal\typed_data\PlaceholderResolverInterface
   *   The placeholder resolver.
   */
  public function getPlaceholderResolver(): PlaceholderResolverInterface {
    if (!isset($this->placeholderResolver)) {
      $this->placeholderResolver = \Drupal::service('typed_data.placeholder_resolver');
    }
    return $this->placeholderResolver;
  }

}
