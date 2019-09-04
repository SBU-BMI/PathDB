<?php

namespace Drupal\authorization\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Login subscriber.
 */
class LoginSubscriber implements EventSubscriberInterface {

  /**
   * Action on request.
   */
  public function onRequest() {

  }

  /**
   * Action on login.
   */
  public function onLogin() {

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onRequest'];
    return $events;
  }

}
