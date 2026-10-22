<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_servers\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers_dummy\FakeBridge;
use Drupal\ldap_servers_dummy\FakeCollection;
use Drupal\ldap_servers_dummy\FakeEntryManager;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Ldap\Entry;

/**
 * Tests LDAP base and user managers.
 */
#[Group('ldap')]
#[RunTestsInSeparateProcesses]
class LdapManagerTest extends KernelTestBase {

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
    'system',
    'user',
  ];

  /**
   * Fake bridge.
   *
   * @var \Drupal\ldap_servers_dummy\FakeBridge
   */
  protected FakeBridge $bridge;

  /**
   * Server entity.
   *
   * @var \Drupal\ldap_servers\Entity\Server
   */
  protected Server $server;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('ldap_server');
    $this->installEntitySchema('user');
    $this->installSchema('externalauth', 'authmap');
    $this->installConfig(['ldap_user']);

    $this->server = Server::create([
      'id' => 'example',
      'status' => TRUE,
      'basedn' => ['ou=people,dc=hogwarts,dc=edu'],
      'user_attr' => 'cn',
      'mail_attr' => 'mail',
      'unique_persistent_attr' => 'uid',
      'type' => 'ad',
    ]);
    $this->server->save();

    $this->bridge = new FakeBridge(
      $this->container->get('logger.channel.ldap_servers'),
      $this->container->get('entity_type.manager')
    );
    $this->bridge->setServer($this->server);
    $this->bridge->setBindResult(TRUE);
    $this->container->set('ldap.bridge', $this->bridge);
  }

  /**
   * DN existence, search, and CRUD.
   */
  public function testBaseManagerOperations(): void {
    $entry = new Entry('cn=hpotter,ou=people,dc=hogwarts,dc=edu', [
      'cn' => ['hpotter'],
      'objectclass' => ['person'],
    ]);
    $manager = new FakeEntryManager();
    /** @var \Drupal\ldap_servers\LdapUserManager $user_manager */
    $user_manager = $this->container->get('ldap.user_manager');
    $user_manager->setServer($this->server);
    $ldap = $this->bridge->get();
    $ldap->setEntryManagerResponse($manager);
    $ldap->setQueryResult([
      '(objectclass=*)' => new FakeCollection([$entry]),
      '(cn=hpotter)' => new FakeCollection([$entry]),
      '(objectClass=person)' => new FakeCollection([$entry]),
    ]);

    self::assertTrue($user_manager->checkDnExists($entry->getDn()));
    $loaded = $user_manager->checkDnExistsIncludeData($entry->getDn(), ['cn']);
    self::assertInstanceOf(Entry::class, $loaded);

    $found = $user_manager->searchAllBaseDns('(objectClass=person)', ['cn']);
    self::assertCount(1, $found);

    self::assertTrue($user_manager->createLdapEntry($entry));
    self::assertCount(1, $manager->added);
    self::assertTrue($user_manager->modifyLdapEntry($entry));
    self::assertCount(1, $manager->updated);
    self::assertTrue($user_manager->deleteLdapEntry($entry->getDn()));
    self::assertSame([$entry->getDn()], $manager->removed);
  }

  /**
   * User lookup by identifier, account, and PUID.
   */
  public function testUserManagerLookups(): void {
    $ldap_entry = new Entry('cn=hpotter,ou=people,dc=hogwarts,dc=edu', [
      'cn' => ['hpotter'],
      'uid' => ['20001'],
      'mail' => ['hpotter@example.com'],
    ]);
    /** @var \Drupal\ldap_servers\LdapUserManager $user_manager */
    $user_manager = $this->container->get('ldap.user_manager');
    $user_manager->setServer($this->server);
    $this->bridge->get()->setQueryResult([
      '(cn=hpotter)' => new FakeCollection([$ldap_entry]),
    ]);

    $data = $user_manager->getUserDataByIdentifier('hpotter');
    self::assertInstanceOf(Entry::class, $data);
    $cached = $user_manager->getUserDataByIdentifier('hpotter');
    self::assertSame($data->getDn(), $cached->getDn());

    $account = User::create([
      'name' => 'hpotter',
      'mail' => 'hpotter@example.com',
      'status' => 1,
      'ldap_user_puid_sid' => 'example',
      'ldap_user_puid' => '20001',
      'ldap_user_puid_property' => 'uid',
    ]);
    $account->save();
    $this->container->get('externalauth.authmap')
      ->save($account, 'ldap_user', 'hpotter');

    $by_account = $user_manager->getUserDataByAccount($account);
    self::assertInstanceOf(Entry::class, $by_account);
    $by_puid = $user_manager->getUserAccountFromPuid('20001');
    self::assertEquals($account->id(), $by_puid->id());
  }

  /**
   * Identifier mismatch after sanitization returns NULL.
   */
  public function testUserDataIdentifierMismatch(): void {
    $this->server->set('bind_method', 'service_account');
    $this->server->save();
    $ldap_entry = new Entry('cn=hpotter,ou=people,dc=hogwarts,dc=edu', [
      'cn' => ['hpotter'],
    ]);
    /** @var \Drupal\ldap_servers\LdapUserManager $user_manager */
    $user_manager = $this->container->get('ldap.user_manager');
    $user_manager->setServer($this->server);
    $this->bridge->get()->setQueryResult([
      '(cn=not_potter)' => new FakeCollection([$ldap_entry]),
    ]);

    self::assertNull($user_manager->getUserDataByIdentifier('not_potter'));
  }

  /**
   * Unavailable server returns empty results.
   */
  public function testUnavailableServer(): void {
    $this->bridge->setBindResult(FALSE);
    /** @var \Drupal\ldap_servers\LdapUserManager $user_manager */
    $user_manager = $this->container->get('ldap.user_manager');
    $user_manager->setServer($this->server);
    self::assertFalse($user_manager->checkDnExists('cn=x,dc=edu'));
    self::assertFalse($user_manager->getUserDataByIdentifier('hpotter'));
    self::assertSame([], $user_manager->searchAllBaseDns('(cn=*)'));
  }

}
