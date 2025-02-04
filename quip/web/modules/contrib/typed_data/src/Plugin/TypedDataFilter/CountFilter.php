<?php

declare(strict_types=1);

namespace Drupal\typed_data\Plugin\TypedDataFilter;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\Type\StringInterface;
use Drupal\typed_data\Attribute\DataFilter;
use Drupal\typed_data\DataFilterBase;

/**
 * A data filter which counts the number of characters in a string.
 *
 * @DataFilter(
 *   id = "count",
 *   label = @Translation("The count filter counts the number of characters in the input string."),
 * )
 */
#[DataFilter(
  id: "count",
  label: new TranslatableMarkup("The count filter counts the number of characters in the input string.")
)]
class CountFilter extends DataFilterBase {

  /**
   * {@inheritdoc}
   */
  public function canFilter(DataDefinitionInterface $definition): bool {
    return is_subclass_of($definition->getClass(), StringInterface::class);
  }

  /**
   * {@inheritdoc}
   */
  public function filtersTo(DataDefinitionInterface $definition, array $arguments): DataDefinitionInterface {
    return DataDefinition::create('integer');
  }

  /**
   * {@inheritdoc}
   */
  public function filter(DataDefinitionInterface $definition, $value, array $arguments, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    return mb_strlen($value);
  }

}
