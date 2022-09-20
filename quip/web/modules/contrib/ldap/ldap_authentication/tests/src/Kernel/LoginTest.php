<?php

declare(strict_types = 1);

namespace Drupal\Tests\ldap_authentication\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\ldap_authentication\Controller\LoginValidatorLoginForm;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ldap_servers_dummy\FakeBridge;
use Drupal\ldap_servers_dummy\FakeCollection;
use Drupal\ldap_servers\LdapUserAttributesInterface;
use Symfony\Component\Ldap\Entry;

/**
 * Login tests.
 *
 * @group ldap
 */
class LoginTest extends KernelTestBase {

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
   * Validator.
   *
   * @var \Drupal\ldap_authentication\Controller\LoginValidatorLoginForm
   */
  private $validator;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('ldap_server');
    $this->installSchema('externalauth', ['authmap']);
    $this->installSchema('system', 'sequences');

    /** @var \Drupal\Core\Entity\EntityTypeManager $manager */
    $manager = $this->container->get('entity_type.manager');
    /** @var \Drupal\ldap_servers\Entity\Server $server */
    $server = $manager->getStorage('ldap_server')->create([
      'id' => 'test',
      'timeout' => 30,
      'encryption' => 'none',
      'address' => 'example',
      'port' => 963,
      'basedn' => ['ou=people,dc=hogwarts,dc=edu'],
      'user_attr' => 'cn',
      'unique_persistent_attr' => 'uid',
      'status' => TRUE,
      'mail_attr' => 'mail',
    ]);
    $server->save();
    $this->config('ldap_authentication.settings')
      ->set('sids', [$server->id()])
      // @todo This should not be necessary, investigate schema.
      ->set('excludeIfTextInDn', [])
      ->set('allowOnlyIfTextInDn', [])
      ->save();
    $this->config('ldap_user.settings')
      ->set('acctCreation', LdapUserAttributesInterface::ACCOUNT_CREATION_LDAP_BEHAVIOUR)
      ->set('drupalAcctProvisionServer', $server->id())
      ->set('ldapUserSyncMappings', [
        'drupal' => [],
        'ldap' => [],
      ])
      ->set('drupalAcctProvisionTriggers', [
        LdapUserAttributesInterface::PROVISION_DRUPAL_USER_ON_USER_AUTHENTICATION,
        LdapUserAttributesInterface::PROVISION_DRUPAL_USER_ON_USER_ON_MANUAL_CREATION,
        LdapUserAttributesInterface::PROVISION_DRUPAL_USER_ON_USER_UPDATE_CREATE,
      ])
      ->save();
    $this->container->get('config.factory')->reset();
    $bridge = new FakeBridge(
      $this->container->get('logger.channel.ldap_servers'),
      $this->container->get('entity_type.manager')
    );
    $bridge->setServer($server);
    $ldap = $bridge->get();
    $collection = [
      '(cn=hpotter)' => new FakeCollection([
        new Entry(
          'cn=hpotter,ou=people,dc=hogwarts,dc=edu',
          [
            'cn' => ['hpotter'],
            'uid' => ['123'],
            'mail' => ['hpotter@example.com'],
          ]
        ),
      ]),
    ];
    $ldap->setQueryResult($collection);

    $this->container->set('ldap.bridge', $bridge);
    $this->validator = new LoginValidatorLoginForm(
      $this->container->get('config.factory'),
      $this->container->get('ldap.detail_log'),
      $this->container->get('logger.channel.ldap_authentication'),
      $this->container->get('entity_type.manager'),
      $this->container->get('module_handler'),
      $this->container->get('ldap.bridge'),
      $this->container->get('externalauth.authmap'),
      $this->container->get('ldap_authentication.servers'),
      $this->container->get('ldap.user_manager'),
      $this->container->get('messenger'),
      $this->container->get('ldap.drupal_user_processor')
      );
  }

  /**
   * Test general user creation on login (mixed implied).
   *
   * Assumes credentials are correct (binding always successful).
   */
  public function testLoginUserCreation(): void {
    $form_state = new FormState();
    $form_state->setValues(['name' => 'hpotter', 'pass' => 'pass']);
    $state = $this->validator->validateLogin($form_state);
    self::assertCount(0, $state->getErrors());
    /** @var \Drupal\Core\Messenger\MessengerInterface $messenger */
    $messenger = $this->container->get('messenger');
    $messenger_errors = $messenger->messagesByType(MessengerInterface::TYPE_ERROR);
    self::assertEmpty($messenger_errors, json_encode($messenger_errors));
    $messenger_warnings = $messenger->messagesByType(MessengerInterface::TYPE_WARNING);
    self::assertEmpty($messenger_warnings, json_encode($messenger_warnings));
    self::assertGreaterThan(0, $state->get('uid'), $state->get('uid'));
  }

  /**
   * Test the whitelist.
   */
  public function testWhiteListPresent(): void {
    $this->config('ldap_authentication.settings')
      ->set('allowOnlyIfTextInDn', [
        'hpotter',
      ])
      ->set('authenticationMode', 'exclusive')
      ->save();
    $this->container->get('config.factory')->reset();
    $form_state = new FormState();
    $form_state->setValues(['name' => 'hpotter', 'pass' => 'pass']);
    $this->validator->validateLogin($form_state);
    self::assertCount(1, $this->container
      ->get('entity_type.manager')
      ->getStorage('user')
      ->loadMultiple()
    );
  }

  /**
   * Test the whitelist.
   */
  public function testWhiteListMissing(): void {
    $this->config('ldap_authentication.settings')
      ->set('allowOnlyIfTextInDn', [
        'HGRANGER',
      ])
      ->set('authenticationMode', 'exclusive')
      ->save();
    $this->container->get('config.factory')->reset();
    $form_state = new FormState();
    $form_state->setValues(['name' => 'hpotter', 'pass' => 'pass']);
    $this->validator->validateLogin($form_state);
    /** @var \Drupal\Core\Messenger\MessengerInterface $messenger */
    $messenger = $this->container->get('messenger');
    $messenger_errors = $messenger->messagesByType(MessengerInterface::TYPE_ERROR);
    self::assertCount(1, $messenger_errors);
    self::assertCount(0, $this->container
      ->get('entity_type.manager')
      ->getStorage('user')
      ->loadMultiple()
    );
  }

  /**
   * Test the blacklist.
   *
   * DN contains "hogwarts", case-insensitive check is made. Error is only
   * shown in exclusive mode (since Drupal could still allow it).
   */
  public function testBlacklist(): void {
    $this->config('ldap_authentication.settings')
      ->set('excludeIfTextInDn', [
        'Hogwarts',
      ])
      ->set('authenticationMode', 'exclusive')
      ->save();
    $this->container->get('config.factory')->reset();
    $form_state = new FormState();
    $form_state->setValues(['name' => 'hpotter', 'pass' => 'pass']);
    $this->validator->validateLogin($form_state);
    /** @var \Drupal\Core\Messenger\MessengerInterface $messenger */
    $messenger = $this->container->get('messenger');
    $messenger_errors = $messenger->messagesByType(MessengerInterface::TYPE_ERROR);
    self::assertCount(1, $messenger_errors);
    self::assertCount(0, $this->container
      ->get('entity_type.manager')
      ->getStorage('user')
      ->loadMultiple()
    );
  }

}
