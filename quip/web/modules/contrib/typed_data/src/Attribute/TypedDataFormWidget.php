<?php

declare(strict_types=1);

namespace Drupal\typed_data\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * PHP Attribute class for typed data form widget plugins.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class TypedDataFormWidget extends Plugin {

  /**
   * Constructs a typed data form widget attribute object.
   *
   * @param string $id
   *   The plugin ID. The machine-name of the widget.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the widget.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   A short description of the widget.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly TranslatableMarkup $description,
    public readonly ?string $deriver = NULL,
  ) {}

}
