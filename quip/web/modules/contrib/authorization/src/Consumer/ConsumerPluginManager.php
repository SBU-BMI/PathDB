<?php

declare(strict_types = 1);

namespace Drupal\authorization\Consumer;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages search Consumer plugins.
 *
 * @see \Drupal\authorization\Annotation\AuthorizationConsumer
 * @see \Drupal\authorization\Consumer\ConsumerInterface
 * @see \Drupal\authorization\Consumer\ConsumerPluginBase
 * @see plugin_api
 */
class ConsumerPluginManager extends DefaultPluginManager {

  /**
   * Constructs a ConsumerPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_consumer
   *   The cache Consumer instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_consumer,
    ModuleHandlerInterface $module_handler
  ) {
    parent::__construct(
      'Plugin/authorization/Consumer',
      $namespaces,
      $module_handler,
      'Drupal\authorization\Consumer\ConsumerInterface',
      'Drupal\authorization\Annotation\AuthorizationConsumer'
    );
    $this->setCacheBackend($cache_consumer, 'authorization_consumers');
    $this->alterInfo('authorization_consumer_info');
  }

}
