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
 * A data filter which marks string data as sanitized.
 *
 * @DataFilter(
 *   id = "format_text",
 *   label = @Translation("The format_text filter passes the text through the given filter."),
 * )
 */
#[DataFilter(
  id: "format_text",
  label: new TranslatableMarkup("The format_text filter passes the text through the given filter.")
)]
class FormatTextFilter extends DataFilterBase {

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

  /**
   * {@inheritdoc}
   */
  public function getNumberOfRequiredArguments(): int {
    return 1;
  }

  /**
   * {@inheritdoc}
   */
  public function validateArguments(DataDefinitionInterface $definition, array $arguments): array {
    $errors = parent::validateArguments($definition, $arguments);
    if (isset($arguments[0])) {
      // Ensure the provided value is given for this data.
      $violations = $this->getTypedDataManager()
        ->create($definition, $arguments[0])
        ->validate();
      foreach ($violations as $violation) {
        $errors[] = $violation->getMessage();
      }
    }
    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function filter(DataDefinitionInterface $definition, $value, array $arguments, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    return (string) check_markup($value, $arguments[0]);
  }

}
