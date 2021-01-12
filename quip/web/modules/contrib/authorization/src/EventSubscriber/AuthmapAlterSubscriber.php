<?php

declare(strict_types = 1);

namespace Drupal\authorization\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\externalauth\Event\ExternalAuthEvents;

/**
 * Authmap event subscriber.
 */
class AuthmapAlterSubscriber implements EventSubscriberInterface {

  /**
   * Action to take on authorization.
   */
  public function onAuthmapAlter(): void {

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ExternalAuthEvents::AUTHMAP_ALTER][] = ['onAuthmapAlter'];
    return $events;
  }

}
