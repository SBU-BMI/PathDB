<?php

declare(strict_types=1);

namespace Drupal\easy_breadcrumb\Cache;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Cache\Context\RequestStackCacheContextBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the Cache Context for Easy Breadcrumb caching.
 *
 * Cache context ID: 'easy_breadcrumb'.
 */
class EasyBreadcrumbCacheContext extends RequestStackCacheContextBase implements CacheContextInterface {

  /**
   * The config factory service.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Cache Context for Easy Breadcrumb.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request stack service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(RequestStack $request_stack, ConfigFactoryInterface $config_factory) {
    parent::__construct($request_stack);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Easy Breadcrumb');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    $request = $this->requestStack->getCurrentRequest();
    $route = $request->attributes->get(RouteObjectInterface::ROUTE_OBJECT);
    $config = $this->configFactory->get(EasyBreadcrumbConstants::MODULE_SETTINGS);
    $applies_admin_routes = $config->get(EasyBreadcrumbConstants::APPLIES_ADMIN_ROUTES);

    if (!isset($applies_admin_routes)) {
      $applies_admin_routes = TRUE;
    }

    if ($route && $route->getOption('_admin_route') && $applies_admin_routes === FALSE) {
      return 'easy_breadcrumb_admin_exclude';
    }

    return 'easy_breadcrumb';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

}
