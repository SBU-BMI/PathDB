<?php

namespace Drupal\jwt_test\EventSubscriber;

use Drupal\jwt\Authentication\Event\JwtAuthEvents;
use Drupal\jwt\Authentication\Event\JwtAuthGenerateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Modify claims on a JWT being issued for testing.
 */
class JwtTestAuthIssuerSubscriber implements EventSubscriberInterface {

  /**
   * Modifications to make to the claims.
   *
   * @var array
   */
  public static array $modifications = [];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[JwtAuthEvents::GENERATE][] = ['modifyStandardClaims', 20];
    return $events;
  }

  /**
   * Modify the standard claims set for a JWT.
   *
   * @param \Drupal\jwt\Authentication\Event\JwtAuthGenerateEvent $event
   *   The event.
   */
  public function modifyStandardClaims(JwtAuthGenerateEvent $event) {
    foreach (static::$modifications as $claim => $value) {
      $event->addClaim($claim, $value);
    }
  }

}
