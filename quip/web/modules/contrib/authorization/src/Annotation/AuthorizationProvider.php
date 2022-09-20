<?php

declare(strict_types = 1);

namespace Drupal\authorization\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Authorization provider item annotation object.
 *
 * @see \Drupal\authorization\Provider\ProviderPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class AuthorizationProvider extends Plugin {

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
