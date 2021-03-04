<?php

/**
 * @file
 * Contains \Drupal\http_response_headers\EventSubscriber\AddHTTPHeaders.
 */

namespace Drupal\http_response_headers\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides AddHTTPHeaders.
 */
class AddHTTPHeaders implements EventSubscriberInterface {

  /**
   * The entity storage manager for response_header entities.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityManager;

  /**
   * Constructs a new Google Tag response subscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->entityManager = \Drupal::entityTypeManager()->getStorage('response_header');
  }

  /**
   * Sets extra HTTP headers.
   */
  public function onRespond(FilterResponseEvent $event) {
    if (!$event->isMasterRequest()) {
      return;
    }
    $response = $event->getResponse();

    $headers = $this->entityManager->loadMultiple();
    if (!empty($headers)) {
      foreach ($headers as $key => $header) {
        if (!empty($header->get('name'))) {
          // @TODO Add context rules to header groups to allow
          // certain groups to only be applied in certain contexts.
          if (!empty($header->get('value'))) {
            // Must remove the existing header if settings a new value.
            if ($response->headers->has($header->get('name'))) {
              $response->headers->remove($header->get('name'));
            }
            $response->headers->set($header->get('name'), $header->get('value'));
          }
          else {
            $response->headers->remove($header->get('name'));
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['onRespond', -100];
    return $events;
  }

}
