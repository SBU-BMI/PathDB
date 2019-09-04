<?php

namespace Drupal\authorization\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\externalauth\Event\ExternalAuthEvents;

/**
 * Registration subscriber.
 */
class RegisterSubscriber implements EventSubscriberInterface {

  /**
   * Action on register.
   */
  public function onRegister() {

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ExternalAuthEvents::REGISTER][] = ['onRegister'];
    return $events;
  }

}
