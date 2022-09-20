<?php

declare(strict_types = 1);

namespace Drupal\authorization\Provider;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\authorization\Annotation\AuthorizationProvider;

/**
 * Manages search Provider plugins.
 *
 * @see \Drupal\authorization\Annotation\AuthorizationProvider
 * @see \Drupal\authorization\Provider\ProviderInterface
 * @see \Drupal\authorization\Provider\ProviderPluginBase
 * @see plugin_api
 */
class ProviderPluginManager extends DefaultPluginManager {

  /**
   * Constructs a ProviderPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_provider
   *   The cache Provider instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_provider,
    ModuleHandlerInterface $module_handler
  ) {
    parent::__construct(
      'Plugin/authorization/Provider',
      $namespaces,
      $module_handler,
      ProviderInterface::class,
      AuthorizationProvider::class
    );
    $this->setCacheBackend($cache_provider, 'authorization_providers');
    $this->alterInfo('authorization_provider_info');
  }

}
