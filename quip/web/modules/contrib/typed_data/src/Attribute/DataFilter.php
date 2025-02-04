<?php

declare(strict_types=1);

namespace Drupal\typed_data\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * PHP Attribute class for typed data filter plugins.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class DataFilter extends Plugin {

  /**
   * Constructs a typed data filter attribute object.
   *
   * @param string $id
   *   The plugin ID. The machine-name of the data filter.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the data filter.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) A short description of the data filter.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?string $deriver = NULL,
  ) {}

}
