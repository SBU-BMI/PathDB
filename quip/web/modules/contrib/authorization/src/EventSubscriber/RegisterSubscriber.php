<?php

declare(strict_types=1);

namespace Drupal\authorization\EventSubscriber;

use Drupal\externalauth\Event\ExternalAuthEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Registration subscriber.
 */
class RegisterSubscriber implements EventSubscriberInterface {

  /**
   * Action on register.
   */
  public function onRegister(): void {

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ExternalAuthEvents::REGISTER][] = ['onRegister'];
    return $events;
  }

}
