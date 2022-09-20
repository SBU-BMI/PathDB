<?php

declare(strict_types = 1);

namespace Drupal\Tests\ldap_user\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers_dummy\FakeBridge;
use Drupal\ldap_servers_dummy\FakeCollection;
use Drupal\ldap_servers\LdapUserAttributesInterface;
use Drupal\user\Entity\User;
use Symfony\Component\Ldap\Entry;

/**
 * Tests for the DrupalUserProcessor.
 *
 * @group ldap
 */
class DrupalUserProcessorTest extends EntityKernelTestBase implements LdapUserAttributesInterface {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'externalauth',
    'file',
    'image',
    'ldap_authentication',
    'ldap_query',
    'ldap_servers',
    'ldap_servers_dummy',
    'ldap_user',
  ];

  /**
   * Drupal User Processor.
   *
   * @var \Drupal\ldap_user\Processor\DrupalUserProcessor
   */
  private $drupalUserProcessor;

  /**
   * Server.
   *
   * @var \Drupal\ldap_servers\Entity\Server
   */
  private $server;

  /**
   * Setup of kernel tests.
   */
  public function setUp(): void {
    parent::setUp();

    $this->installConfig(['ldap_authentication']);
    $this->installConfig(['ldap_user']);
    $this->installEntitySchema('ldap_server');
    $this->installEntitySchema('file');
    $this->installSchema('file', 'file_usage');
    $this->installSchema('externalauth', 'authmap');

    FieldStorageConfig::create([
      'entity_type' => 'user',
      'field_name' => 'user_picture',
      'type' => 'image',
    ])->save();
    FieldConfig::create([
      'field_name' => 'user_picture',
      'entity_type' => 'user',
      'bundle' => 'user',
    ])->save();

    $this->config('ldap_user.settings')
      ->set('drupalAcctProvisionServer', 'example')
      ->set('drupalAcctProvisionTriggers', [
        self::PROVISION_DRUPAL_USER_ON_USER_AUTHENTICATION,
        self::PROVISION_DRUPAL_USER_ON_USER_UPDATE_CREATE,
      ])
      ->save();

    $this->server = Server::create([
      'id' => 'example',
      'basedn' => ['ou=people,dc=hogwarts,dc=edu'],
      'user_attr' => 'cn',
      'mail_attr' => 'mail',
      'picture_attr' => 'picture_field',
    ]);
    $this->server->save();

    $bridge = new FakeBridge(
      $this->container->get('logger.channel.ldap_servers'),
      $this->container->get('entity_type.manager')
    );
    $bridge->setServer($this->server);
    $collection = [
      '(cn=hpotter)' => new FakeCollection([
        new Entry(
          'cn=hpotter,ou=people,dc=hogwarts,dc=edu',
          [
            'cn' => ['hpotter'],
            'uid' => ['123'],
            'mail' => ['hpotter@example.com'],
            'picture_field' => [
              file_get_contents(__DIR__ . '/../../example.png'),
            ],
          ],
        ),
      ]),
    ];
    $bridge->get()->setQueryResult($collection);
    $bridge->setBindResult(TRUE);
    $this->container->set('ldap.bridge', $bridge);

    $this->drupalUserProcessor = $this->container->get('ldap.drupal_user_processor');
  }

  /**
   * Tests user exclusion for the authentication helper.
   */
  public function testUserExclusion(): void {
    // Skip administrators, if so configured.
    /** @var \Drupal\user\Entity\User $account */
    $account = $this->prophesize(User::class);
    $account->getRoles()->willReturn(['administrator']);
    $account->id()->willReturn(1);
    $exclusion = new GetStringHelper();
    $exclusion->value = '';
    $account->get('ldap_user_ldap_exclude')->willReturn($exclusion);
    $this->entityTypeManager
      ->getStorage('user_role')
      ->create([
        'id' => 'administrator',
        'label' => 'Administrators',
        'is_admin' => TRUE,
      ])
      ->save();
    $admin_roles = $this->entityTypeManager
      ->getStorage('user_role')
      ->getQuery()
      ->condition('is_admin', TRUE)
      ->execute();
    self::assertNotEmpty($admin_roles);
    self::assertTrue($this->drupalUserProcessor->excludeUser($account->reveal()));
    $this->config('ldap_authentication.settings')->set('skipAdministrators', 0)->save();
    self::assertFalse($this->drupalUserProcessor->excludeUser($account->reveal()));

    // Disallow checkbox exclusion (everyone else allowed).
    $account = $this->prophesize(User::class);
    $account->getRoles()->willReturn(['']);
    $account->id()->willReturn(2);
    $exclusion->value = 1;
    $account->get('ldap_user_ldap_exclude')->willReturn($exclusion);
    self::assertTrue($this->drupalUserProcessor->excludeUser($account->reveal()));

    // Everyone else allowed.
    $account = $this->prophesize(User::class);
    $account->getRoles()->willReturn(['']);
    $account->id()->willReturn(2);
    $exclusion->value = 0;
    $account->get('ldap_user_ldap_exclude')->willReturn($exclusion);
    self::assertFalse($this->drupalUserProcessor->excludeUser($account->reveal()));
  }

  /**
   * Test that creating users with createDrupalUserFromLdapEntry() works.
   */
  public function testProvisioning(): void {
    $result = $this->drupalUserProcessor->createDrupalUserFromLdapEntry(['name' => 'invalid']);
    self::assertFalse($result);
    $result = $this->drupalUserProcessor->createDrupalUserFromLdapEntry(['name' => 'hpotter']);
    self::assertTrue($result);
    $user = $this->drupalUserProcessor->getUserAccount();
    self::assertInstanceOf(User::class, $user);
    self::assertEquals('hpotter@example.com', $user->getEmail());

    // Check picture file.
    /** @var \Drupal\file\Entity\File $picture */
    $picture = $user->get('user_picture')->referencedEntities()[0];
    self::assertInstanceOf(File::class, $picture);
    self::assertStringContainsString('.png', $picture->getFilename());

    // Let email be overwritten from LDAP via presave due to
    // PROVISION_DRUPAL_USER_ON_USER_UPDATE_CREATE.
    $user->setEmail('overridden@example.com')->save();
    $user = $this->entityTypeManager->getStorage('user')->load($user->id());
    self::assertEquals('hpotter@example.com', $user->getEmail());

    $this->config('ldap_user.settings')
      ->set('drupalAcctProvisionServer', 'example')
      ->set('drupalAcctProvisionTriggers', [
        self::PROVISION_DRUPAL_USER_ON_USER_AUTHENTICATION,
      ])->save();

    // Value overwritten due to different trigger.
    $user->setEmail('overridden@example.com')->save();
    $user = $this->entityTypeManager->getStorage('user')->load($user->id());
    self::assertEquals('overridden@example.com', $user->getEmail());
  }

  /**
   * Test the 'associate' option when provisioning an account.
   */
  public function testLdapAssociateDrupalAccount(): void {
    $result = $this->drupalUserProcessor->createDrupalUserFromLdapEntry(['name' => 'hpotter']);
    self::assertTrue($result);
    self::assertEquals(TRUE, $this->drupalUserProcessor->ldapAssociateDrupalAccount('hpotter'));
    $this->config('ldap_user.settings')
      ->set('drupalAcctProvisionServer', 'none')
      ->save();
    self::assertEquals(FALSE, $this->drupalUserProcessor->ldapAssociateDrupalAccount('hpotter'));

  }

}
