<?php

declare(strict_types = 1);

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
  public function onRequest(): void {

  }

  /**
   * Action on login.
   */
  public function onLogin(): void {

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onRequest'];
    return $events;
  }

}
