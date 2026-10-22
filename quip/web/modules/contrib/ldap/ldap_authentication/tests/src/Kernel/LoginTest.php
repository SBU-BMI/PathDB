<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_authentication\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ldap_authentication\Controller\LoginValidatorLoginForm;
use Drupal\ldap_authentication\Controller\LoginValidatorSso;
use Drupal\ldap_servers\LdapUserAttributesInterface;
use Drupal\ldap_servers_dummy\FakeBridge;
use Drupal\ldap_servers_dummy\FakeCollection;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Ldap\Entry;

/**
 * Login tests.
 */
#[Group('ldap')]
#[RunTestsInSeparateProcesses]
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
    $this->installConfig(['ldap_user', 'ldap_authentication']);
    if (version_compare(\Drupal::VERSION, '10.2.0', '<')) {
      $this->installSchema('system', 'sequences');
    }

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
      ->set('acctCreation', LdapUserAttributesInterface::ACCOUNT_CREATION_LDAP_BEHAVIOR)
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
    /** @var \Drupal\ldap_servers_dummy\FakeLdap $ldap */
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
      $this->container->get('ldap.drupal_user_processor'),
      $this->container
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
   * Test the allowlist.
   */
  public function testAllowListPresent(): void {
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
   * Test the allowlist.
   */
  public function testAllowListMissing(): void {
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
   * Test the blocklist.
   *
   * DN contains "hogwarts", case-insensitive check is made. Error is only
   * shown in exclusive mode (since Drupal could still allow it).
   */
  public function testBlocklist(): void {
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

  /**
   * Existing Drupal users without authmap are associated.
   */
  public function testMatchExistingUserWithLdap(): void {
    $account = User::create([
      'name' => 'hpotter',
      'mail' => 'hpotter@example.com',
      'status' => 1,
    ]);
    $account->save();

    $this->config('ldap_user.settings')
      ->set('userConflictResolve', LdapUserAttributesInterface::USER_CONFLICT_ATTEMPT_RESOLVE)
      ->save();
    $this->container->get('config.factory')->reset();

    $form_state = new FormState();
    $form_state->setValues(['name' => 'hpotter', 'pass' => 'pass']);
    $state = $this->validator->validateLogin($form_state);
    self::assertEquals($account->id(), $state->get('uid'));
    self::assertEquals(
      'hpotter',
      $this->container->get('externalauth.authmap')->get((int) $account->id(), 'ldap_user')
    );
    self::assertNotNull($this->validator->getDrupalUser());
  }

  /**
   * Excluded Drupal accounts cannot authenticate via LDAP.
   */
  public function testVerifyUserAllowedSkipsExcluded(): void {
    $account = User::create([
      'name' => 'hpotter',
      'mail' => 'hpotter@example.com',
      'status' => 1,
      'ldap_user_ldap_exclude' => 1,
    ]);
    $account->save();
    $this->container->get('externalauth.authmap')
      ->save($account, 'ldap_user', 'hpotter');

    $form_state = new FormState();
    $form_state->setValues(['name' => 'hpotter', 'pass' => 'pass']);
    $this->validator->validateLogin($form_state);
    self::assertEmpty($form_state->get('uid'));
  }

  /**
   * Email addresses are updated from LDAP when configured.
   */
  public function testFixOutdatedEmailAddress(): void {
    $account = User::create([
      'name' => 'hpotter',
      'mail' => 'stale@example.com',
      'status' => 1,
    ]);
    $account->save();
    $this->container->get('externalauth.authmap')
      ->save($account, 'ldap_user', 'hpotter');
    $this->config('ldap_authentication.settings')
      ->set('emailUpdate', 'update')
      ->save();
    $this->container->get('config.factory')->reset();

    $form_state = new FormState();
    $form_state->setValues(['name' => 'hpotter', 'pass' => 'pass']);
    $this->validator->validateLogin($form_state);
    $account = $this->container->get('entity_type.manager')
      ->getStorage('user')
      ->load($account->id());
    self::assertEquals('hpotter@example.com', $account->getEmail());
  }

  /**
   * Email template fills missing LDAP mail.
   */
  public function testEmailTemplateAlways(): void {
    $this->config('ldap_authentication.settings')
      ->set('emailTemplate', '@username@hogwarts.edu')
      ->set('emailTemplateHandling', 'always')
      ->save();
    $this->container->get('config.factory')->reset();

    $form_state = new FormState();
    $form_state->setValues(['name' => 'hpotter', 'pass' => 'pass']);
    $this->validator->validateLogin($form_state);
    self::assertGreaterThan(0, $form_state->get('uid'));
  }

  /**
   * User-bind method still authenticates with FakeBridge.
   */
  public function testBindToServerAsUser(): void {
    $server = $this->container->get('entity_type.manager')
      ->getStorage('ldap_server')
      ->load('test');
    $server->set('bind_method', 'user');
    $server->set('user_dn_expression', 'cn=%username,%basedn');
    $server->save();
    $this->container->get('config.factory')->reset();

    $form_state = new FormState();
    $form_state->setValues(['name' => 'hpotter', 'pass' => 'pass']);
    $state = $this->validator->validateLogin($form_state);
    self::assertGreaterThan(0, $state->get('uid'));
  }

  /**
   * Mixed mode skips LDAP when Drupal already authenticated.
   */
  public function testUserAlreadyAuthenticatedMixedMode(): void {
    $this->config('ldap_authentication.settings')
      ->set('authenticationMode', 'mixed')
      ->save();
    $this->container->get('config.factory')->reset();

    $form_state = new FormState();
    $form_state->setValues(['name' => 'hpotter', 'pass' => 'pass']);
    $form_state->set('uid', 5);
    $state = $this->validator->validateLogin($form_state);
    self::assertEquals(5, $state->get('uid'));
  }

  /**
   * Logged-in credential check uses authmap authname.
   */
  public function testValidateCredentialsLoggedIn(): void {
    $account = User::create([
      'name' => 'hpotter',
      'mail' => 'hpotter@example.com',
      'status' => 1,
      'pass' => 'pass',
    ]);
    $account->save();
    $this->container->get('externalauth.authmap')
      ->save($account, 'ldap_user', 'hpotter');

    $result = $this->validator->validateCredentialsLoggedIn($account);
    self::assertEquals(
      LoginValidatorLoginForm::AUTHENTICATION_SUCCESS,
      $result
    );
  }

  /**
   * SSO provisions a Drupal user from the authname.
   */
  public function testSsoProcessLoginCreatesUser(): void {
    $sso = new LoginValidatorSso(
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
      $this->container->get('ldap.drupal_user_processor'),
      $this->container
    );
    $sso->setAuthname('hpotter');
    $sso->processLogin();
    self::assertNotNull($sso->getDrupalUser());
    self::assertEquals('hpotter', $sso->getDrupalUser()->getAccountName());
  }

  /**
   * SSO with user bind is rejected.
   */
  public function testSsoRejectsUserBind(): void {
    $server = $this->container->get('entity_type.manager')
      ->getStorage('ldap_server')
      ->load('test');
    $server->set('bind_method', 'user');
    $server->save();
    $this->container->get('config.factory')->reset();

    $sso = new LoginValidatorSso(
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
      $this->container->get('ldap.drupal_user_processor'),
      $this->container
    );
    $sso->setAuthname('hpotter');
    $sso->processLogin();
    self::assertNull($sso->getDrupalUser());
  }

}
