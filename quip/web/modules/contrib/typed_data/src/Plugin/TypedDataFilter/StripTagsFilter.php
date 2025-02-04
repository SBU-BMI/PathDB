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
 * A data filter call "strip_tags()".
 *
 * @DataFilter(
 *   id = "striptags",
 *   label = @Translation("The striptags filter strips SGML/XML tags and replace adjacent whitespace by one space."),
 * )
 */
#[DataFilter(
  id: "striptags",
  label: new TranslatableMarkup("The striptags filter strips SGML/XML tags and replace adjacent whitespace by one space.")
)]
class StripTagsFilter extends DataFilterBase {

  /**
   * {@inheritdoc}
   */
  public function filter(DataDefinitionInterface $definition, $value, array $arguments, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    return strip_tags($value);
  }

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
    return DataDefinition::create('string');
  }

}
