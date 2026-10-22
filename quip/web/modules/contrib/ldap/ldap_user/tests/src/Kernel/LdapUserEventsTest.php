<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ldap_user\Event\LdapNewUserCreatedEvent;
use Drupal\ldap_user\Event\LdapUserDeletedEvent;
use Drupal\ldap_user\Event\LdapUserLoginEvent;
use Drupal\ldap_user\Event\LdapUserUpdatedEvent;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests LDAP user events are dispatched with EVENT_NAME.
 */
#[Group('ldap')]
#[RunTestsInSeparateProcesses]
class LdapUserEventsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'externalauth',
    'ldap_authentication',
    'ldap_query',
    'ldap_servers',
    'ldap_user',
    'system',
    'user',
  ];

  /**
   * Captured events keyed by EVENT_NAME.
   *
   * @var array<string, list<object>>
   */
  private array $capturedEvents = [];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['ldap_user', 'ldap_authentication']);
    $this->installSchema('externalauth', 'authmap');
    if (version_compare(\Drupal::VERSION, '10.2.0', '<')) {
      $this->installSchema('system', 'sequences');
    }

    $dispatcher = $this->container->get('event_dispatcher');
    $event_names = [
      LdapUserLoginEvent::EVENT_NAME,
      LdapNewUserCreatedEvent::EVENT_NAME,
      LdapUserUpdatedEvent::EVENT_NAME,
      LdapUserDeletedEvent::EVENT_NAME,
    ];
    foreach ($event_names as $event_name) {
      $dispatcher->addListener($event_name, function (object $event) use ($event_name): void {
        $this->capturedEvents[$event_name][] = $event;
      });
    }
  }

  /**
   * Login dispatches LdapUserLoginEvent on EVENT_NAME.
   */
  public function testLoginDispatchesNamedEvent(): void {
    $account = $this->createTestUser('login_user');
    $this->capturedEvents = [];

    $this->container->get('ldap.drupal_user_processor')
      ->drupalUserLogsIn($account);

    $this->assertCapturedEvent(
      LdapUserLoginEvent::EVENT_NAME,
      LdapUserLoginEvent::class,
      $account,
    );
  }

  /**
   * User insert dispatches LdapNewUserCreatedEvent on EVENT_NAME.
   */
  public function testInsertDispatchesNamedEvent(): void {
    $account = $this->createTestUser('insert_user');

    $this->assertCapturedEvent(
      LdapNewUserCreatedEvent::EVENT_NAME,
      LdapNewUserCreatedEvent::class,
      $account,
    );
  }

  /**
   * User update dispatches LdapUserUpdatedEvent on EVENT_NAME.
   */
  public function testUpdateDispatchesNamedEvent(): void {
    $account = $this->createTestUser('update_user');
    $this->capturedEvents = [];

    $account->setEmail('updated@example.com');
    $account->save();

    $this->assertCapturedEvent(
      LdapUserUpdatedEvent::EVENT_NAME,
      LdapUserUpdatedEvent::class,
      $account,
    );
  }

  /**
   * User delete dispatches LdapUserDeletedEvent on EVENT_NAME.
   */
  public function testDeleteDispatchesNamedEvent(): void {
    $account = $this->createTestUser('delete_user');
    $uid = $account->id();
    $this->capturedEvents = [];

    $account->delete();

    self::assertArrayHasKey(
      LdapUserDeletedEvent::EVENT_NAME,
      $this->capturedEvents,
    );
    self::assertCount(
      1,
      $this->capturedEvents[LdapUserDeletedEvent::EVENT_NAME],
    );
    $event = $this->capturedEvents[LdapUserDeletedEvent::EVENT_NAME][0];
    self::assertInstanceOf(LdapUserDeletedEvent::class, $event);
    self::assertEquals($uid, $event->account->id());
  }

  /**
   * Create a user for event tests.
   *
   * @param string $name
   *   Account name.
   *
   * @return \Drupal\user\UserInterface
   *   Saved user.
   */
  private function createTestUser(string $name): UserInterface {
    $account = User::create([
      'name' => $name,
      'mail' => $name . '@example.com',
      'status' => 1,
    ]);
    $account->save();
    return $account;
  }

  /**
   * Assert one named event was captured for the account.
   *
   * @param string $event_name
   *   Event name constant.
   * @param class-string $class
   *   Expected event class.
   * @param \Drupal\user\UserInterface $account
   *   Expected account.
   */
  private function assertCapturedEvent(string $event_name, string $class, UserInterface $account): void {
    self::assertArrayHasKey($event_name, $this->capturedEvents);
    self::assertCount(1, $this->capturedEvents[$event_name]);
    $event = $this->capturedEvents[$event_name][0];
    self::assertInstanceOf($class, $event);
    self::assertEquals($account->id(), $event->account->id());
  }

}
