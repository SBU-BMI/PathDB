<?php

declare(strict_types = 1);

namespace Drupal\Tests\ldap_user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers\FakeBridge;
use Drupal\ldap_servers\LdapUserAttributesInterface;
use Drupal\ldap_user\EventSubscriber\LdapEntryProvisionSubscriber;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * @coversDefaultClass \Drupal\ldap_servers\Processor\TokenProcessor
 * @group ldap
 */
class LdapEntryProvisionTest extends KernelTestBase {

  use UserCreationTrait {
    checkPermissions as drupalCheckPermissions;
    createAdminRole as drupalCreateAdminRole;
    createRole as drupalCreateRole;
    createUser as drupalCreateUser;
    grantPermissions as drupalGrantPermissions;
    setCurrentUser as drupalSetCurrentUser;
    setUpCurrentUser as drupalSetUpCurrentUser;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'externalauth',
    'ldap_servers',
    'ldap_user',
    'ldap_query',
    'ldap_authentication',
    'user',
    'system',
  ];

  /**
   * EventSubscriber.
   *
   * @var \Drupal\ldap_user\EventSubscriber\LdapEntryProvisionSubscriber
   */
  private $subscriber;

  /**
   * Test setup.
   */
  public function setUp(): void {
    parent::setUp();

    $this->installSchema('system', 'sequences');
    $this->installEntitySchema('user');

    $server = Server::create([
      'id' => 'test',
      'timeout' => 30,
      'encryption' => 'none',
      'address' => 'example',
      'port' => 963,
      'basedn' => [],
    ]);
    $server->save();
    $this->config('ldap_user.settings')
      ->set('ldapEntryProvisionTriggers', [
        LdapEntryProvisionSubscriber::EVENT_CREATE_LDAP_ENTRY,
        LdapEntryProvisionSubscriber::EVENT_SYNC_TO_LDAP_ENTRY,
      ])
      ->set('ldapEntryProvisionServer', $server->id())
      ->set('ldapUserSyncMappings', [
        LdapUserAttributesInterface::PROVISION_TO_LDAP => [
          'mail' => [
            'ldap_attr' => '[mail]',
            'user_attr' => '[property.mail]',
            'convert' => FALSE,
            'user_tokens' => '',
            'config_module' => 'ldap_user',
            'prov_module' => 'ldap_user',
            'prov_events' => [
              'create_ldap_entry',
            ],
          ],
        ],
      ])
      ->save();

    // @todo Replace bridge with FakeBridge.
    $fake_bridge = new FakeBridge(
      $this->container->get('logger.channel.ldap_user'),
      $this->container->get('entity_type.manager')
    );
    $this->container->set('ldap.bridge', $fake_bridge);

    $this->subscriber = new LdapEntryProvisionSubscriber(
      $this->container->get('config.factory'),
      $this->container->get('logger.channel.ldap_user'),
      $this->container->get('ldap.detail_log'),
      $this->container->get('entity_type.manager'),
      $this->container->get('module_handler'),
      $this->container->get('ldap.user_manager'),
      $this->container->get('ldap_user.field_provider'),
      $this->container->get('file_system')
    );
  }

  /**
   * Helper function to test private methods.
   *
   * @param string $name
   *   Method name.
   * @param array $arguments
   *   Method arguments.
   *
   * @return mixed
   *   Method result.
   */
  protected function invokeNonPublic(string $name, array $arguments) {
    $reflection = new \ReflectionClass(get_class($this->subscriber));
    $method = $reflection->getMethod($name);
    $method->setAccessible(TRUE);
    return $method->invokeArgs($this->subscriber, $arguments);
  }

  /**
   * Test building the entry.
   */
  public function testEntry(): void {
    $user = $this->createUser();
    $this->subscriber->setUser($user);
    self::assertEquals('test', $this->config('ldap_user.settings')
      ->get('ldapEntryProvisionServer'));
    $this->invokeNonPublic('loadServer', []);
    /** @var \Symfony\Component\Ldap\Entry $entry */
    $entry = $this->invokeNonPublic('buildLdapEntry', [LdapEntryProvisionSubscriber::EVENT_CREATE_LDAP_ENTRY]);
    $tokens = $this->subscriber->getTokens();
    self::assertEquals($user->getEmail(), $tokens['[property.mail]']);
    self::assertEquals($user->getEmail(), $entry->getAttribute('mail', FALSE)[0]);
    self::markTestIncomplete('TODO: We still need to fix & test case sensitive here like we did for the token processor.');
    // We can simplify since the record *to* LDAP does not care about case.
    // We only need to make sure that the Drupal token isn't an issue
    // (or let the user know how to fix it).
  }

}
