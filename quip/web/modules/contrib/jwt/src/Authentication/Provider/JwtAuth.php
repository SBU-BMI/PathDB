<?php

namespace Drupal\jwt\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Site\Settings;
use Drupal\jwt\Authentication\Event\JwtAuthEvents;
use Drupal\jwt\Authentication\Event\JwtAuthGenerateEvent;
use Drupal\jwt\Authentication\Event\JwtAuthValidateEvent;
use Drupal\jwt\Authentication\Event\JwtAuthValidEvent;
use Drupal\jwt\JsonWebToken\JsonWebToken;
use Drupal\jwt\Transcoder\JwtDecodeException;
use Drupal\jwt\Transcoder\JwtTranscoderInterface;
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
   * The site settings.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannel;

  /**
   * Constructs a HTTP basic authentication provider object.
   *
   * @param \Drupal\jwt\Transcoder\JwtTranscoderInterface $transcoder
   *   The jwt transcoder service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher service.
   * @param \Drupal\Core\Site\Settings $settings
   *   The site settings.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel factory.
   */
  public function __construct(
    JwtTranscoderInterface $transcoder,
    EventDispatcherInterface $event_dispatcher,
    Settings $settings,
    LoggerChannelInterface $logger
  ) {
    $this->transcoder = $transcoder;
    $this->eventDispatcher = $event_dispatcher;
    $this->settings = $settings;
    $this->loggerChannel = $logger;
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
      return $this->debugLog('JWT decode exception', $e);
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

  /**
   * Log the reason that a JWT could not be used to authenticate.
   *
   * @param string $cause
   *   The cause.
   * @param \Exception|null $e
   *   The caught exception.
   * @param \StdClass|null $payload
   *   The payload (claims) from the JWT.
   *
   * @return null
   *   NULL to be returned instead of a user.
   */
  protected function debugLog($cause, \Exception $e = NULL, \StdClass $payload = NULL) {
    if ($this->settings::get('jwt.debug_log')) {
      $this->loggerChannel
        ->error('Error authenticating with a JWT "%cause". Exception: "%exception" Payload: "%payload"', [
          '%cause' => $cause,
          '%exception' => $e ? get_class($e) . ' ' . $e->getMessage() : 'null',
          '%payload' => $payload ? var_export($payload, TRUE) : 'null',
        ]);
    }
    return NULL;
  }

}
