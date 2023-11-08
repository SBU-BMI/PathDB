<?php

declare(strict_types = 1);

namespace Drupal\ldap_user\Event;

if (!class_exists('Drupal\Component\EventDispatcher\Event')) {
  class_alias('Symfony\Component\EventDispatcher\Event', 'Drupal\Component\EventDispatcher\Event');
}
use Drupal\Component\EventDispatcher\Event;
use Drupal\user\UserInterface;

/**
 * LDAP User deleted event.
 */
class LdapUserDeletedEvent extends Event {

  /**
   * Event name.
   *
   * @var string
   */
  public const EVENT_NAME = 'ldap_drupal_user_deleted';

  /**
   * Account.
   *
   * @var \Drupal\user\Entity\User
   */
  public $account;

  /**
   * Constructor.
   *
   * @param \Drupal\user\UserInterface $account
   *   Account.
   */
  public function __construct(UserInterface $account) {
    $this->account = $account;
  }

}
