<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_user\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\ldap_query\Entity\QueryEntity;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers\LdapUserAttributesInterface;
use Drupal\ldap_servers_dummy\FakeBridge;
use Drupal\ldap_servers_dummy\FakeCollection;
use Drupal\ldap_user\Event\LdapUserLoginEvent;
use Drupal\ldap_user\Processor\GroupUserUpdateProcessor;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Ldap\Entry;

/**
 * Tests cron-based LDAP user updates.
 */
#[Group('ldap')]
#[RunTestsInSeparateProcesses]
class GroupUserUpdateProcessorTest extends EntityKernelTestBase implements LdapUserAttributesInterface {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'externalauth',
    'ldap_authentication',
    'ldap_query',
    'ldap_servers',
    'ldap_servers_dummy',
    'ldap_user',
  ];

  /**
   * Fake LDAP bridge.
   *
   * @var \Drupal\ldap_servers_dummy\FakeBridge
   */
  protected FakeBridge $bridge;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installConfig(['ldap_authentication', 'ldap_user']);
    $this->installEntitySchema('ldap_server');
    $this->installSchema('externalauth', 'authmap');

    Server::create([
      'id' => 'example',
      'status' => TRUE,
      'basedn' => ['ou=people,dc=hogwarts,dc=edu'],
      'user_attr' => 'cn',
      'mail_attr' => 'mail',
    ])->save();

    QueryEntity::create([
      'id' => 'students',
      'label' => 'Students',
      'server_id' => 'example',
      'status' => TRUE,
      'base_dn' => 'ou=people,dc=hogwarts,dc=edu',
      'filter' => '(objectClass=person)',
      'attributes' => 'cn,mail',
      'size_limit' => 0,
      'time_limit' => 0,
      'scope' => 'sub',
      'dereference' => 0,
    ])->save();

    $this->config('ldap_user.settings')
      ->set('drupalAcctProvisionServer', 'example')
      ->set('userUpdateCronQuery', 'students')
      ->set('userUpdateCronInterval', 'always')
      ->set('userUpdateOnly', FALSE)
      ->set('drupalAcctProvisionTriggers', [
        self::PROVISION_DRUPAL_USER_ON_USER_AUTHENTICATION,
        self::PROVISION_DRUPAL_USER_ON_USER_UPDATE_CREATE,
      ])
      ->save();

    $this->bridge = new FakeBridge(
      $this->container->get('logger.channel.ldap_servers'),
      $this->container->get('entity_type.manager')
    );
    $this->bridge->setServer(
      $this->container->get('entity_type.manager')
        ->getStorage('ldap_server')
        ->load('example')
    );
    $this->bridge->setBindResult(TRUE);
    $this->setQueryEntries([
      new Entry('cn=hpotter,ou=people,dc=hogwarts,dc=edu', [
        'cn' => ['hpotter'],
        'uid' => ['123'],
        'mail' => ['hpotter@example.com'],
      ]),
    ]);
    $this->container->set('ldap.bridge', $this->bridge);
  }

  /**
   * Sets FakeLdap results for the students query filter.
   *
   * @param \Symfony\Component\Ldap\Entry[] $entries
   *   LDAP entries.
   */
  private function setQueryEntries(array $entries): void {
    /** @var \Drupal\ldap_servers_dummy\FakeLdap $ldap */
    $ldap = $this->bridge->get();
    $ldap->setQueryResult([
      '(objectClass=person)' => new FakeCollection($entries),
      '(cn=hpotter)' => new FakeCollection($entries),
    ]);
  }

  /**
   * Builds the processor after the fake bridge is in the container.
   */
  private function processor(): GroupUserUpdateProcessor {
    return $this->container->get('ldap.group_user_update_processor');
  }

  /**
   * Cron interval decides whether an update is due.
   */
  public function testUpdateDueIntervals(): void {
    $state = $this->container->get('state');
    $processor = $this->processor();

    $this->config('ldap_user.settings')
      ->set('userUpdateCronInterval', 'always')
      ->save();
    self::assertTrue($processor->updateDue());

    $state->set('ldap_user_cron_last_group_user_update', strtotime('today'));
    $this->config('ldap_user.settings')
      ->set('userUpdateCronInterval', 'daily')
      ->save();
    self::assertFalse($processor->updateDue());

    $state->set('ldap_user_cron_last_group_user_update', strtotime('today -2 day'));
    self::assertTrue($processor->updateDue());

    $state->set('ldap_user_cron_last_group_user_update', strtotime('today'));
    $this->config('ldap_user.settings')
      ->set('userUpdateCronInterval', 'weekly')
      ->save();
    self::assertFalse($processor->updateDue());
    $state->set('ldap_user_cron_last_group_user_update', strtotime('today -8 day'));
    self::assertTrue($processor->updateDue());

    $state->set('ldap_user_cron_last_group_user_update', strtotime('today'));
    $this->config('ldap_user.settings')
      ->set('userUpdateCronInterval', 'monthly')
      ->save();
    self::assertFalse($processor->updateDue());
    $state->set('ldap_user_cron_last_group_user_update', strtotime('today -31 day'));
    self::assertTrue($processor->updateDue());
  }

  /**
   * Missing authentication name attribute aborts the query.
   */
  public function testRunQueryRequiresAuthenticationAttribute(): void {
    $server = $this->container->get('entity_type.manager')
      ->getStorage('ldap_server')
      ->load('example');
    $server->set('user_attr', '')->save();

    $this->processor()->runQuery('students');
    self::assertEmpty(
      $this->container->get('entity_type.manager')
        ->getStorage('user')
        ->loadByProperties(['name' => 'hpotter'])
    );
  }

  /**
   * Entries without the authname attribute are skipped.
   */
  public function testRunQuerySkipsEntriesMissingAuthname(): void {
    $this->setQueryEntries([
      new Entry('cn=unknown,ou=people,dc=hogwarts,dc=edu', [
        'mail' => ['unknown@example.com'],
      ]),
    ]);
    $this->processor()->runQuery('students');
    self::assertCount(
      0,
      $this->container->get('entity_type.manager')
        ->getStorage('user')
        ->loadByProperties(['mail' => 'unknown@example.com'])
    );
  }

  /**
   * Update-only mode skips creating accounts that do not exist yet.
   */
  public function testRunQueryUpdateOnlySkipsCreate(): void {
    $this->config('ldap_user.settings')
      ->set('userUpdateOnly', TRUE)
      ->save();
    $this->processor()->runQuery('students');
    self::assertEmpty(
      $this->container->get('entity_type.manager')
        ->getStorage('user')
        ->loadByProperties(['name' => 'hpotter'])
    );
  }

  /**
   * Duplicate Drupal email skips create without changing the existing user.
   */
  public function testRunQuerySkipsCreateOnDuplicateEmail(): void {
    $existing = User::create([
      'name' => 'local_user',
      'mail' => 'hpotter@example.com',
      'status' => 1,
    ]);
    $existing->save();

    $this->processor()->runQuery('students');
    self::assertEmpty(
      $this->container->get('entity_type.manager')
        ->getStorage('user')
        ->loadByProperties(['name' => 'hpotter'])
    );
    $reloaded = $this->container->get('entity_type.manager')
      ->getStorage('user')
      ->load($existing->id());
    self::assertEquals('local_user', $reloaded->getAccountName());
    self::assertEquals('hpotter@example.com', $reloaded->getEmail());
    self::assertFalse(
      $this->container->get('externalauth.authmap')->getUid('hpotter', 'ldap_user')
    );
  }

  /**
   * Cron query creates a Drupal user from LDAP.
   */
  public function testRunQueryCreatesUser(): void {
    $this->processor()->runQuery('students');
    $users = $this->container->get('entity_type.manager')
      ->getStorage('user')
      ->loadByProperties(['name' => 'hpotter']);
    self::assertCount(1, $users);
    $user = reset($users);
    self::assertEquals('hpotter@example.com', $user->getEmail());
  }

  /**
   * Existing users with an authmap are updated without a login event.
   */
  public function testRunQueryUpdatesExistingUser(): void {
    $account = User::create([
      'name' => 'hpotter',
      'mail' => 'stale@example.com',
      'status' => 1,
    ]);
    $account->save();
    $this->container->get('externalauth.authmap')
      ->save($account, 'ldap_user', 'hpotter');

    $login_events = [];
    $this->container->get('event_dispatcher')->addListener(
      LdapUserLoginEvent::EVENT_NAME,
      function () use (&$login_events): void {
        $login_events[] = TRUE;
      }
    );

    $this->setQueryEntries([
      new Entry('cn=hpotter,ou=people,dc=hogwarts,dc=edu', [
        'cn' => ['hpotter'],
        'uid' => ['123'],
        'mail' => ['hpotter-updated@example.com'],
      ]),
    ]);
    $this->container->get('cache.default')
      ->invalidateTags(['ldap_servers.user_data']);

    $this->processor()->runQuery('students');
    $user = $this->container->get('entity_type.manager')
      ->getStorage('user')
      ->load($account->id());
    self::assertEquals('hpotter-updated@example.com', $user->getEmail());
    self::assertSame([], $login_events);
    self::assertNotEquals(
      1,
      $this->container->get('state')
        ->get('ldap_user_cron_last_group_user_update')
    );
  }

}
