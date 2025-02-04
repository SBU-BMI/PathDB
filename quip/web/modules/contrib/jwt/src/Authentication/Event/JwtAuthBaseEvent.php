<?php

namespace Drupal\jwt\Authentication\Event;

use Drupal\jwt\JsonWebToken\JsonWebTokenInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * JWT Auth Base Event is extended by other JWT event classes.
 */
class JwtAuthBaseEvent extends Event {

  /**
   * The JsonWebToken.
   *
   * @var \Drupal\jwt\JsonWebToken\JsonWebTokenInterface
   */
  protected JsonWebTokenInterface $jwt;

  /**
   * Constructs a JwtAuthEvent with a JsonWebToken.
   *
   * @param \Drupal\jwt\JsonWebToken\JsonWebTokenInterface $token
   *   A decoded JWT.
   */
  public function __construct(JsonWebTokenInterface $token) {
    $this->jwt = $token;
  }

  /**
   * Returns the JWT.
   *
   * @return \Drupal\jwt\JsonWebToken\JsonWebTokenInterface
   *   Returns the token.
   */
  public function getToken(): JsonWebTokenInterface {
    return $this->jwt;
  }

}
