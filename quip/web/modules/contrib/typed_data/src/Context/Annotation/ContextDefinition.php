<?php

namespace Drupal\typed_data\Context\Annotation;

use Drupal\Core\Annotation\ContextDefinition as CoreContextDefinition;
use Drupal\Core\Annotation\Translation;
use Drupal\typed_data\Context\ContextDefinition as TypedDataContextDefinition;

@trigger_error('\Drupal\typed_data\Context\Annotation\ContextDefinition is deprecated in typed_data:8.x-1.0 and is removed from typed_data:2.0.0. Use \Drupal\Core\Annotation\ContextDefinition instead. See https://www.drupal.org/project/typed_data/issues/3169307', E_USER_DEPRECATED);

/**
 * Extends the core context definition annotation object for Typed Data.
 *
 * Ensures context definitions use
 * \Drupal\typed_data\Context\ContextDefinitionInterface.
 *
 * @Annotation
 *
 * @ingroup plugin_context
 *
 * @deprecated in typed_data:8.x-1.0 and is removed from typed_data:2.0.0. Use \Drupal\Core\Annotation\ContextDefinition instead.
 *
 * @see https://www.drupal.org/project/typed_data/issues/3169307
 */
class ContextDefinition extends CoreContextDefinition {

  /**
   * The ContextDefinitionInterface object.
   *
   * @var \Drupal\typed_data\Context\ContextDefinitionInterface
   */
  protected $definition;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values) {
    // Filter out any @Translation annotation objects.
    foreach ($values as $key => $value) {
      if ($value instanceof Translation) {
        $values[$key] = $value->get();
      }
    }
    $this->definition = TypedDataContextDefinition::createFromArray($values);
  }

  /**
   * Returns the value of an annotation.
   *
   * @return \Drupal\typed_data\Context\ContextDefinitionInterface
   *   Return the Typed Data version of the ContextDefinitionInterface.
   */
  public function get() {
    return $this->definition;
  }

}
