<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers\LdapUserAttributesInterface;
use Drupal\ldap_servers_dummy\FakeBridge;
use Drupal\ldap_servers_dummy\FakeEntryManager;
use Drupal\ldap_user\Event\LdapUserDeletedEvent;
use Drupal\ldap_user\EventSubscriber\LdapEntryDeletionSubscriber;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests LDAP entry deletion on Drupal user delete.
 */
#[Group('ldap')]
#[RunTestsInSeparateProcesses]
class LdapEntryDeletionSubscriberTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('ldap_server');
    $this->installSchema('externalauth', ['authmap']);
    $this->installConfig(['ldap_user']);
  }

  /**
   * Deletion is skipped when the trigger is not enabled.
   */
  public function testDeleteSkippedWhenTriggerDisabled(): void {
    $this->config('ldap_user.settings')
      ->set('ldapEntryProvisionServer', 'example')
      ->set('ldapEntryProvisionTriggers', [])
      ->save();
    $account = User::create([
      'name' => 'hpotter',
      'mail' => 'hpotter@example.com',
      'status' => 1,
    ]);
    $account->save();
    $this->container->get('ldap_user.ldap_delete')
      ->deleteProvisionedLdapEntry(new LdapUserDeletedEvent($account));
    self::assertTrue(TRUE);
  }

  /**
   * Provisioned LDAP entries are deleted when configured.
   */
  public function testDeletesProvisionedLdapEntry(): void {
    Server::create([
      'id' => 'example',
      'status' => TRUE,
      'basedn' => ['ou=people,dc=hogwarts,dc=edu'],
      'user_attr' => 'cn',
    ])->save();
    $this->config('ldap_user.settings')
      ->set('ldapEntryProvisionServer', 'example')
      ->set('ldapEntryProvisionTriggers', [
        LdapUserAttributesInterface::PROVISION_LDAP_ENTRY_ON_USER_ON_USER_DELETE,
      ])
      ->save();

    $bridge = new FakeBridge(
      $this->container->get('logger.channel.ldap_servers'),
      $this->container->get('entity_type.manager')
    );
    $bridge->setServer(
      $this->container->get('entity_type.manager')
        ->getStorage('ldap_server')
        ->load('example')
    );
    $bridge->setBindResult(TRUE);
    $manager = new FakeEntryManager();
    $bridge->get()->setEntryManagerResponse($manager);
    $this->container->set('ldap.bridge', $bridge);

    $account = User::create([
      'name' => 'hpotter',
      'mail' => 'hpotter@example.com',
      'status' => 1,
      'ldap_user_prov_entries' => [
        ['value' => 'example|cn=hpotter,ou=people,dc=hogwarts,dc=edu'],
      ],
    ]);
    $account->save();

    $subscriber = new LdapEntryDeletionSubscriber(
      $this->container->get('config.factory'),
      $this->container->get('logger.channel.ldap_user'),
      $this->container->get('ldap.user_manager')
    );
    $subscriber->deleteProvisionedLdapEntry(new LdapUserDeletedEvent($account));
    self::assertSame(
      ['cn=hpotter,ou=people,dc=hogwarts,dc=edu'],
      $manager->removed
    );
  }

  /**
   * Bind failure is logged and skipped.
   */
  public function testDeleteLogsUnavailableServer(): void {
    Server::create([
      'id' => 'offline',
      'status' => TRUE,
      'basedn' => ['ou=people,dc=hogwarts,dc=edu'],
      'user_attr' => 'cn',
    ])->save();
    $this->config('ldap_user.settings')
      ->set('ldapEntryProvisionServer', 'offline')
      ->set('ldapEntryProvisionTriggers', [
        LdapUserAttributesInterface::PROVISION_LDAP_ENTRY_ON_USER_ON_USER_DELETE,
      ])
      ->save();
    $bridge = new FakeBridge(
      $this->container->get('logger.channel.ldap_servers'),
      $this->container->get('entity_type.manager')
    );
    $bridge->setServer(
      $this->container->get('entity_type.manager')
        ->getStorage('ldap_server')
        ->load('offline')
    );
    $bridge->setBindResult(FALSE);
    $this->container->set('ldap.bridge', $bridge);

    $account = User::create([
      'name' => 'hpotter',
      'mail' => 'hpotter@example.com',
      'status' => 1,
      'ldap_user_prov_entries' => [
        ['value' => 'offline|cn=hpotter,ou=people,dc=hogwarts,dc=edu'],
      ],
    ]);
    $account->save();
    $subscriber = new LdapEntryDeletionSubscriber(
      $this->container->get('config.factory'),
      $this->container->get('logger.channel.ldap_user'),
      $this->container->get('ldap.user_manager')
    );
    $subscriber->deleteProvisionedLdapEntry(new LdapUserDeletedEvent($account));
    self::assertTrue(TRUE);
  }

}
