<?php

namespace Drupal\jwt\Authentication\Event;

use Drupal\jwt\JsonWebToken\JsonWebTokenInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * An event triggered after a JWT token is validated.
 */
class JwtAuthValidEvent extends JwtAuthBaseEvent {

  /**
   * Variable holding the user authenticated by the token in the payload.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user;

  /**
   * {@inheritdoc}
   */
  public function __construct(JsonWebTokenInterface $token) {
    $this->user = User::getAnonymousUser();
    parent::__construct($token);
  }

  /**
   * Sets the authenticated user that will be used for this request.
   *
   * @param \Drupal\user\UserInterface $user
   *   A loaded user object.
   */
  public function setUser(UserInterface $user) {
    $this->user = $user;
  }

  /**
   * Returns a loaded user to use if the token is validated.
   *
   * @return \Drupal\user\UserInterface
   *   A loaded user object
   */
  public function getUser() {
    return $this->user;
  }

}
