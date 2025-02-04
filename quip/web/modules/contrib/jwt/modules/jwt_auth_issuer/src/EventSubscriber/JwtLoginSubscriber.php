<?php

namespace Drupal\jwt_auth_issuer\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds JWT token to user.login.http response.
 */
class JwtLoginSubscriber implements EventSubscriberInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private ConfigFactoryInterface $configFactory;

  /**
   * The route builder service.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  private RouteBuilderInterface $routeBuilder;

  /**
   * Constructor with dependency injection.
   */
  public function __construct(ConfigFactoryInterface $configFactory, RouteBuilderInterface $routeBuilder) {
    $this->configFactory = $configFactory;
    $this->routeBuilder = $routeBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public function onAlterRoutes(RouteBuildEvent $event) {
    // Disabled by configuration.
    if (!$this->configFactory->get('jwt_auth_issuer.config')->get('jwt_in_login_response')) {
      return;
    }

    $collection = $event->getRouteCollection();
    $route = $collection->get('user.login.http');
    if ($route) {
      $route->addDefaults(['_controller' => '\Drupal\jwt_auth_issuer\Controller\JwtAuthIssuerLoginController::login']);
      $collection->add('user.login.http', $route);
    }
  }

  /**
   * Reacts to a config save and sets the router to rebuild if required.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The event.
   */
  public function onConfigSave(ConfigCrudEvent $event) {
    $config = $event->getConfig();
    if ($config->getName() == 'jwt_auth_issuer.config' && $event->isChanged('jwt_in_login_response')) {
      $this->routeBuilder->setRebuildNeeded();
    }
  }

  /**
   * The subscribed events.
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    $events[RoutingEvents::ALTER][] = 'onAlterRoutes';
    $events[ConfigEvents::SAVE][] = 'onConfigSave';
    return $events;
  }

}
