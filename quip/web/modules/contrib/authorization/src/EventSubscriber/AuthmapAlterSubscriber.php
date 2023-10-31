<?php

declare(strict_types=1);

namespace Drupal\authorization\EventSubscriber;

use Drupal\externalauth\Event\ExternalAuthEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

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
