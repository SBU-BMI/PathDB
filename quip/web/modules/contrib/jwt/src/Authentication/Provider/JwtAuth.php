<?php

namespace Drupal\jwt\Authentication\Provider;

use Drupal\jwt\Transcoder\JwtTranscoderInterface;
use Drupal\jwt\Transcoder\JwtDecodeException;
use Drupal\jwt\Authentication\Event\JwtAuthGenerateEvent;
use Drupal\jwt\Authentication\Event\JwtAuthValidateEvent;
use Drupal\jwt\Authentication\Event\JwtAuthValidEvent;
use Drupal\jwt\Authentication\Event\JwtAuthEvents;
use Drupal\jwt\JsonWebToken\JsonWebToken;
use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * JWT Authentication Provider.
 */
class JwtAuth implements AuthenticationProviderInterface {

  /**
   * The JWT Transcoder service.
   *
   * @var \Drupal\jwt\Transcoder\JwtTranscoderInterface
   */
  protected $transcoder;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a HTTP basic authentication provider object.
   *
   * @param \Drupal\jwt\Transcoder\JwtTranscoderInterface $transcoder
   *   The jwt transcoder service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher service.
   */
  public function __construct(
    JwtTranscoderInterface $transcoder,
    EventDispatcherInterface $event_dispatcher
  ) {
    $this->transcoder = $transcoder;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    return (bool) self::getJwtFromRequest($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {
    $raw_jwt = self::getJwtFromRequest($request);

    // Decode JWT and validate signature.
    try {
      $jwt = $this->transcoder->decode($raw_jwt);
    }
    catch (JwtDecodeException $e) {
      return NULL;
    }

    $validate = new JwtAuthValidateEvent($jwt);
    // Signature is validated, but allow modules to do additional validation.
    $this->eventDispatcher->dispatch($validate, JwtAuthEvents::VALIDATE);
    if (!$validate->isValid()) {
      return NULL;
    }

    $valid = new JwtAuthValidEvent($jwt);
    $this->eventDispatcher->dispatch($valid, JwtAuthEvents::VALID);
    $user = $valid->getUser();

    if (!$user) {
      return NULL;
    }

    return $user;
  }

  /**
   * Generate a new JWT token calling all event handlers.
   *
   * @return string|bool
   *   The encoded JWT token. False if there is a problem encoding.
   */
  public function generateToken() {
    $event = new JwtAuthGenerateEvent(new JsonWebToken());
    $this->eventDispatcher->dispatch($event, JwtAuthEvents::GENERATE);
    $jwt = $event->getToken();
    return $this->transcoder->encode($jwt);
  }

  /**
   * Gets a raw JsonWebToken from the current request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return string|bool
   *   Raw JWT String if on request, false if not.
   */
  public static function getJwtFromRequest(Request $request) {
    $auth_headers = [];
    $auth = $request->headers->get('Authorization');
    if ($auth) {
      $auth_headers[] = $auth;
    }
    // Check a second header used in combination with basic auth.
    $fallback = $request->headers->get('JWT-Authorization');
    if ($fallback) {
      $auth_headers[] = $fallback;
    }
    foreach ($auth_headers as $value) {
      if (preg_match('/^Bearer (.+)/', $value, $matches)) {
        return $matches[1];
      }
    }
    return FALSE;
  }

}
