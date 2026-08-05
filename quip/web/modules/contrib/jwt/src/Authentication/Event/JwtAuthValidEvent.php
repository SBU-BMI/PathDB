<?php

namespace Drupal\jwt\Authentication\Event;

use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * An event triggered after a JWT token is validated.
 */
class JwtAuthValidEvent extends JwtAuthBaseEvent {

  /**
   * Variable holding the account authenticated by the token in the payload.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * Sets the account that will be used for this request.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   An account object.
   */
  public function setAccount(AccountInterface $user) {
    $this->account = $user;
  }

  /**
   * Returns an account to use if the token is validated.
   *
   * @return \Drupal\Core\Session\AccountInterface|null
   *   An account object or NULL if no account was set.
   */
  public function getAccount() {
    return $this->account;
  }

  /**
   * Sets the authenticated user that will be used for this request.
   *
   * @param \Drupal\user\UserInterface $user
   *   A loaded user object.
   */
  public function setUser(UserInterface $user) {
    @trigger_error('Calling ' . __METHOD__ . '() is deprecated in jwt:2.1.0 and is removed from jwt:3.0.0. Use \Drupal\jwt\Authentication\Event\JwtAuthValidEvent::setAccount() instead. See https://www.drupal.org/node/3431432', E_USER_DEPRECATED);
    $this->setAccount($user);
  }

  /**
   * Returns a loaded user to use if the token is validated.
   *
   * @return \Drupal\Core\Session\AccountInterface|null
   *   A loaded user object
   */
  public function getUser() {
    @trigger_error('Calling ' . __METHOD__ . '() is deprecated in jwt:2.1.0 and is removed from jwt:3.0.0. Use \Drupal\jwt\Authentication\Event\JwtAuthValidEvent::getAccount() instead. See https://www.drupal.org/node/3431432', E_USER_DEPRECATED);
    return $this->getAccount();
  }

}
