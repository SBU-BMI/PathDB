<?php

declare(strict_types = 1);

namespace Drupal\authorization\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Authorization consumer item annotation object.
 *
 * @see \Drupal\authorization\Consumer\ConsumerPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class AuthorizationConsumer extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

}
