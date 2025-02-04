<?php

namespace Drupal\http_response_headers\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for HTTP response header entities.
 *
 * @see \Drupal\Core\Entity\Routing\AdminHtmlRouteProvider
 * @see \Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider
 *
 * @ingroup http_response_headers
 */
class ResponseHeaderRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);
    $routes = [
      'enable' => [
        'defaults' => [
          '_title' => 'Enable',
          '_controller' => '\Drupal\http_response_headers\Controller\ResponseHeaderController::enable',
        ],
        'requirements' => [
          '_permission' => 'edit http response headers',
        ],
      ],
      'disable' => [
        'defaults' => [
          '_title' => 'Disable',
          '_controller' => '\Drupal\http_response_headers\Controller\ResponseHeaderController::disable',
        ],
        'requirements' => [
          '_permission' => 'edit http response headers',
        ],
      ],
    ];
    foreach ($routes as $template => $route_info) {
      if ($route_object = $this->getFormRoutes($template, $route_info, $entity_type)) {
        $collection->add('entity.response_header.' . $template, $route_object);
      }
    }
    return $collection;
  }

  /**
   * Build entity specific routes.
   */
  protected function getFormRoutes($template, $route_info, EntityTypeInterface $entity_type) {
    if (!$entity_type->hasLinkTemplate($template)) {
      return NULL;
    }
    $route = new Route($entity_type->getLinkTemplate($template));
    foreach ($route_info['defaults'] as $key => $val) {
      $route->setDefault($key, $val);
    }
    if (!empty($route_info['requirements'])) {
      foreach ($route_info['requirements'] as $key => $val) {
        $route->setRequirement($key, $val);
      }
    }
    else {
      $route->setRequirement('_permission', $entity_type->getAdminPermission());
    }
    if (empty($route_info['skip_options'])) {
      $route->setOption('parameters', [
        'response_header' => [
          'type' => 'entity:response_header',
        ],
      ]);
    }
    return $route;
  }

}
