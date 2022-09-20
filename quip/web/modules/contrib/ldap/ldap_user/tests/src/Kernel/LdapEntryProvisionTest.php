<?php

declare(strict_types = 1);

namespace Drupal\Tests\ldap_user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers_dummy\FakeBridge;
use Drupal\ldap_servers\LdapUserAttributesInterface;
use Drupal\ldap_user\Event\LdapUserLoginEvent;
use Drupal\ldap_user\EventSubscriber\LdapEntryProvisionSubscriber;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\Ldap\Adapter\ExtLdap\EntryManager;

/**
 * Token processor tests.
 *
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
    'ldap_authentication',
    'ldap_query',
    'ldap_servers',
    'ldap_servers_dummy',
    'ldap_user',
    'system',
    'user',
  ];

  /**
   * EventSubscriber.
   *
   * @var \Drupal\ldap_user\EventSubscriber\LdapEntryProvisionSubscriber
   */
  private $subscriber;

  /**
   * @var \Drupal\user\Entity\User|false
   */
  private $user;

  /**
   * Test setup.
   */
  public function setUp(): void {
    parent::setUp();

    $this->installSchema('system', 'sequences');
    $this->installEntitySchema('user');
    $this->installSchema('externalauth', 'authmap');

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
        LdapUserAttributesInterface::PROVISION_LDAP_ENTRY_ON_USER_ON_USER_AUTHENTICATION,
      ])
      ->set('ldapEntryProvisionServer', $server->id())
      ->set('ldapUserSyncMappings', [
        LdapUserAttributesInterface::PROVISION_TO_LDAP => [
          'dn' => [
            'ldap_attr' => '[dn]',
            'user_attr' => 'cn=[property.name],ou=people,dc=hogwarts,dc=edu',
            'convert' => FALSE,
            'user_tokens' => '',
            'config_module' => 'ldap_user',
            'prov_module' => 'ldap_user',
            'prov_events' => [
              'create_ldap_entry',
            ],
          ],
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

    $fake_bridge = new FakeBridge(
      $this->container->get('logger.channel.ldap_user'),
      $this->container->get('entity_type.manager')
    );
    $fake_bridge->setServer($server);
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
   * Test building the entry.
   */
  public function testLoginCreate(): void {
    /** @var \Drupal\ldap_servers_dummy\FakeLdap $ldap */
    $ldap = $this->container->get('ldap.bridge')->get();
    $response = $this->getMockBuilder(EntryManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $response
      ->expects(self::once())
      ->method('add')
      ->willReturnCallback(function ($entry): bool {
        self::assertSame($this->user->getEmail(), $entry->getAttribute('mail')[0]);
        // This is not a derived DN.
        self::assertSame(
          'cn=' . $this->user->getAccountName() . ',ou=people,dc=hogwarts,dc=edu',
          $entry->getDn()
        );
        return TRUE;
      });

    $ldap->setEntryManagerResponse($response);
    $this->user = $this->createUser();
    $this->user->save();
    $this->subscriber->login(new LdapUserLoginEvent($this->user));
  }

}
