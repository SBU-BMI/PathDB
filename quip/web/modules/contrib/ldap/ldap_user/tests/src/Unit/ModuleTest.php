<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_user\Unit;

use Drupal\ldap_servers\LdapUserAttributesInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

require_once __DIR__ . '/../../../ldap_user.module';

/**
 * Test the module functions.
 *
 * @package Drupal\Tests\ldap_user\Unit
 */
class ModuleTest extends UnitTestCase {

  /**
   * The container interface.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container = new ContainerBuilder();

    $string_translation = $this->getStringTranslationStub();
    $this->container->set('string_translation', $string_translation);

    \Drupal::setContainer($this->container);

  }

  /**
   * Test ldap_user_cron().
   *
   * @coversFunction ::ldap_user_cron
   */
  public function testLdapUserCron(): void {
    $settings = $this->createMock('Drupal\Core\Config\ImmutableConfig');
    $settings->expects($this->exactly(2))
      ->method('get')
      ->willReturnMap([
        ['orphanedDrupalAcctBehavior', 'ldap_user_orphan_email'],
        ['userUpdateCronQuery', 'ldap_query_a'],
      ]);

    $config_factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $config_factory->expects($this->exactly(2))
      ->method('get')
      ->with('ldap_user.settings')
      ->willReturn($settings);
    $this->container->set('config.factory', $config_factory);

    $user_processor = $this->createMock('Drupal\ldap_user\Processor\OrphanProcessor');
    $user_processor->expects($this->once())
      ->method('checkOrphans');
    $this->container->set('ldap.orphan_processor', $user_processor);

    $module_handler = $this->createMock('Drupal\Core\Extension\ModuleHandlerInterface');
    $module_handler->expects($this->once())
      ->method('moduleExists')
      ->with('ldap_query')
      ->willReturn(TRUE);
    $this->container->set('module_handler', $module_handler);

    $group_processor = $this->createMock('Drupal\ldap_user\Processor\GroupUserUpdateProcessor');
    $group_processor->expects($this->once())
      ->method('updateDue')
      ->willReturn(TRUE);
    $group_processor->expects($this->once())
      ->method('runQuery')
      ->with('ldap_query_a');
    $this->container->set('ldap.group_user_update_processor', $group_processor);

    ldap_user_cron();
  }

  /**
   * Test hook_mail().
   */
  public function testHookMail(): void {
    $system_site = $this->createMock('Drupal\Core\Config\ImmutableConfig');
    $system_site->expects($this->once())
      ->method('get')
      ->with('name')
      ->willReturn('drupal ldap');
    $config_factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $config_factory->expects($this->once())
      ->method('get')
      ->with('system.site')
      ->willReturn($system_site);
    $this->container->set('config.factory', $config_factory);

    $message = [
      'to' => '',
      'subject' => '',
      'body' => [],
      'headers' => [],
    ];
    $params = [
      'accounts' => [],
    ];

    ldap_user_mail('orphaned_accounts', $message, $params);
  }

  /**
   * Test hook_help().
   *
   * @coversFunction ::ldap_user_help
   */
  public function testHookHelpRouteHelpPage(): void {
    $route_match = $this->createMock('Drupal\Core\Routing\RouteMatchInterface');

    $output = ldap_user_help('help.page.ldap_user', $route_match);
    $this->assertNotEmpty($output);
  }

  /**
   * Test hook_module_implements_alter().
   *
   * @coversFunction ::ldap_user_module_implements_alter
   */
  public function testHookModuleImplementsAlter(): void {

    $implementations = [
      'option_a' => 'option_a',
      'authorization' => 'authorization',
      'option_b' => 'option_b',
    ];

    ldap_user_module_implements_alter($implementations, 'user_login');

    $this->assertCount(3, $implementations);
    $this->assertArrayHasKey('authorization', $implementations);
    $this->assertArrayHasKey('option_a', $implementations);
    $this->assertArrayHasKey('option_b', $implementations);
    $this->assertEquals('authorization', array_pop($implementations));
  }

  /**
   * Test hook_user_login().
   *
   * @coversFunction ::ldap_user_user_login
   */
  public function testHookUserLogin(): void {

    $account = $this->createMock('Drupal\user\Entity\User');

    $processor = $this->createMock('Drupal\ldap_user\Processor\DrupalUserProcessor');
    $processor->expects($this->once())
      ->method('drupalUserLogsIn')
      ->with($account);

    $this->container->set('ldap.drupal_user_processor', $processor);

    ldap_user_user_login($account);
  }

  /**
   * Test hook_user_insert().
   *
   * @coversFunction ::ldap_user_user_insert
   */
  public function testHookUserInsert(): void {
    $account = $this->createMock('Drupal\user\Entity\User');

    $event_dispatcher = $this->createMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
    $event_dispatcher->expects($this->once())
      ->method('dispatch')
      ->with($this->isInstanceOf('Drupal\ldap_user\Event\LdapNewUserCreatedEvent'), 'ldap_new_drupal_user_created');

    $this->container->set('event_dispatcher', $event_dispatcher);

    ldap_user_user_insert($account);
  }

  /**
   * Test hook_user_presave().
   */
  public function testHookUserPresave(): void {
    $account = $this->createMock('Drupal\user\Entity\User');
    $account->expects($this->once())
      ->method('isNew')
      ->willReturn(FALSE);

    $processor = $this->createMock('Drupal\ldap_user\Processor\DrupalUserProcessor');
    $processor->expects($this->once())
      ->method('drupalUserUpdate')
      ->with($account);
    $this->container->set('ldap.drupal_user_processor', $processor);

    ldap_user_user_presave($account);
  }

  /**
   * Test hook_user_update().
   *
   * @coversFunction ::ldap_user_user_update
   */
  public function testHookUserUpdate(): void {
    $account = $this->createMock('Drupal\user\Entity\User');

    $event_dispatcher = $this->createMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
    $event_dispatcher->expects($this->once())
      ->method('dispatch')
      ->with($this->isInstanceOf('Drupal\ldap_user\Event\LdapUserUpdatedEvent'), 'ldap_drupal_user_update');

    $this->container->set('event_dispatcher', $event_dispatcher);

    ldap_user_user_update($account);
  }

  /**
   * Test hook_user_delete().
   *
   * @coversFunction ::ldap_user_user_delete
   */
  public function testHookUserDelete(): void {
    $account = $this->createMock('Drupal\user\Entity\User');

    $event_dispatcher = $this->createMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
    $event_dispatcher->expects($this->once())
      ->method('dispatch')
      ->with($this->isInstanceOf('Drupal\ldap_user\Event\LdapUserDeletedEvent'), 'ldap_drupal_user_deleted');

    $this->container->set('event_dispatcher', $event_dispatcher);

    ldap_user_user_delete($account);
  }

  /**
   * Test ldap_user_entity_base_field_info().
   */
  public function testLdapUserEntityBaseFieldInfo(): void {

    $field_type = $this->createMock('Drupal\Core\Field\FieldTypePluginManagerInterface');
    $field_type->expects($this->exactly(7))
      ->method('getDefaultStorageSettings')
      ->willReturnMap([
        ['string', []],
        ['timestamp', []],
        ['boolean', []],
      ]);
    $field_type->expects($this->exactly(7))
      ->method('getDefaultFieldSettings')
      ->willReturnMap([
        ['string', []],
        ['timestamp', []],
        ['boolean', []],
      ]);

    $this->container->set('plugin.manager.field.field_type', $field_type);

    $user_type = $this->createMock('Drupal\Core\Entity\EntityTypeInterface');
    $user_type->expects($this->once())
      ->method('id')
      ->willReturn('user');

    $fields = ldap_user_entity_base_field_info($user_type);

    $this->assertIsArray($fields);
    $this->assertCount(7, $fields);
    $this->assertArrayHasKey('ldap_user_puid_sid', $fields);
    $this->assertArrayHasKey('ldap_user_puid', $fields);
    $this->assertArrayHasKey('ldap_user_puid_property', $fields);
    $this->assertArrayHasKey('ldap_user_current_dn', $fields);
    $this->assertArrayHasKey('ldap_user_prov_entries', $fields);
    $this->assertArrayHasKey('ldap_user_last_checked', $fields);
    $this->assertArrayHasKey('ldap_user_ldap_exclude', $fields);
  }

  /**
   * Test hook_form_FORM_ID_alter().
   *
   * @coversFunction ::ldap_user_form_user_login_block_alter
   */
  public function testLdapUserFormUserLoginBlockAlter(): void {
    $form = [
      '#validate' => [
        'callback_1',
        'callback_2',
      ],
    ];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    ldap_user_form_user_login_block_alter($form, $form_state);

    $this->assertCount(3, $form['#validate']);
    $this->assertEquals('ldap_user_grab_password_validate', $form['#validate'][0]);
  }

  /**
   * Test hook_form_FORM_ID_alter().
   *
   * @coversFunction ::ldap_user_form_user_pass_reset_alter
   */
  public function testLdapUserFormUserPassResetAlter(): void {
    $form = [
      '#validate' => [
        'callback_1',
        'callback_2',
      ],
    ];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    ldap_user_form_user_pass_reset_alter($form, $form_state);

    $this->assertCount(3, $form['#validate']);
    $this->assertEquals('ldap_user_grab_password_validate', $form['#validate'][0]);
  }

  /**
   * Test hook_form_FORM_ID_alter().
   *
   * @coversFunction ::ldap_user_form_user_login_form_alter
   */
  public function testLdapUserFormUserLoginFormAlter(): void {
    $form = [
      '#validate' => [
        'callback_1',
        'callback_2',
      ],
    ];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    ldap_user_form_user_login_form_alter($form, $form_state);

    $this->assertCount(3, $form['#validate']);
    $this->assertEquals('ldap_user_grab_password_validate', $form['#validate'][0]);
  }

  /**
   * Test hook_form_FORM_ID_alter().
   *
   * @coversFunction ::ldap_user_form_user_form_alter
   */
  public function testLdapUserFormUserFormAlter(): void {
    $form = [
      '#validate' => [
        'callback_1',
        'callback_2',
      ],
    ];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    ldap_user_form_user_form_alter($form, $form_state);

    $this->assertCount(3, $form['#validate']);
    $this->assertEquals('ldap_user_grab_password_validate', $form['#validate'][0]);
  }

  /**
   * Test hook_form_FORM_ID_alter().
   *
   * @coversFunction ::ldap_user_form_password_policy_password_tab_alter
   */
  public function testLdapUserFormPasswordPolicyPasswordTabAlter(): void {
    $form = [
      '#validate' => [
        'callback_1',
        'callback_2',
      ],
    ];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    ldap_user_form_password_policy_password_tab_alter($form, $form_state);

    $this->assertCount(3, $form['#validate']);
    $this->assertEquals('ldap_user_grab_password_validate', $form['#validate'][0]);
  }

  /**
   * Test ldap_user_grab_password_validate().
   *
   * @covers ::ldap_user_grab_password_validate
   */
  public function testLdapUserGrabPasswordValidateLogonForm(): void {
    $form_state = $this->createMock('Drupal\Core\Form\FormState');
    $form_state->expects($this->exactly(3))
      ->method('getValue')
      ->willReturnMap([
        ['current_pass_required_values'],
        ['pass', NULL, 'a password'],
      ]);

    ldap_user_grab_password_validate([], $form_state);
  }

  /**
   * Test ldap_user_grab_password_validate().
   */
  public function testLdapUserGrabPasswordValidateNotLogonForm(): void {
    $form_state = $this->createMock('Drupal\Core\Form\FormState');
    $form_state->expects($this->exactly(4))
      ->method('getValue')
      ->willReturnMap([
        ['current_pass_required_values', NULL, TRUE],
        ['current_pass', NULL, 'a password'],
        ['pass', NULL, NULL],
      ]);

    ldap_user_grab_password_validate([], $form_state);
  }

  /**
   * Tests ldap_user_entity_base_field_info_alter().
   */
  public function testLdapUserEntityBaseFieldInfoAlterNotUser(): void {
    $fields = [];
    $node_type = $this->createMock('Drupal\Core\Entity\EntityTypeInterface');
    $node_type->expects($this->once())
      ->method('id')
      ->willReturn('node');

    ldap_user_entity_base_field_info_alter($fields, $node_type);
  }

  /**
   * Tests ldap_user_entity_base_field_info_alter().
   */
  public function testLdapUserEntityBaseFieldInfoAlterLdapAuthenticationNotInstalled(): void {
    $fields = [];
    $user_type = $this->createMock('Drupal\Core\Entity\EntityTypeInterface');
    $user_type->expects($this->once())
      ->method('id')
      ->willReturn('user');

    $module_handler = $this->createMock('Drupal\Core\Extension\ModuleHandlerInterface');
    $module_handler->expects($this->once())
      ->method('moduleExists')
      ->with('ldap_authentication')
      ->willReturn(FALSE);
    $this->container->set('module_handler', $module_handler);

    ldap_user_entity_base_field_info_alter($fields, $user_type);
  }

  /**
   * Tests ldap_user_form_register_form_submit2().
   */
  public function testLdapUserFormRegisterFormSubmit2NoContact(): void {

    $processor = $this->createMock('Drupal\ldap_user\Processor\DrupalUserProcessor');
    $processor->expects($this->once())
      ->method('ldapExcludeDrupalAccount')
      ->with('a name');
    $this->container->set('ldap.drupal_user_processor', $processor);

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormState');
    $form_state->expects($this->once())
      ->method('getValues')
      ->willReturn([
        'name' => 'a name',
        'mail' => 'an email',
        'pass' => 'a password',
        'ldap_user_association' => LdapUserAttributesInterface::MANUAL_ACCOUNT_CONFLICT_NO_LDAP_ASSOCIATE,
      ]);
    ldap_user_form_register_form_submit2($form, $form_state);
  }

  /**
   * Tests ldap_user_form_user_register_form_alter().
   */
  public function testLdapUserFormUserRegisterFormAlterNoHasAdministerUsers(): void {
    $account = $this->createMock('Drupal\user\Entity\User');
    $account->expects($this->once())
      ->method('hasPermission')
      ->with('administer users')
      ->willReturn(FALSE);
    $this->container->set('current_user', $account);

    $config = $this->createMock('Drupal\Core\Config\ImmutableConfig');

    $config_factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $config_factory->expects($this->once())
      ->method('get')
      ->with('ldap_user.settings')
      ->willReturn($config);
    $this->container->set('config.factory', $config_factory);

    $form = [
      '#submit' => [
        'callback_1',
        'callback_2',
      ],
    ];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    ldap_user_form_user_register_form_alter($form, $form_state);

    $this->assertCount(3, $form['#submit']);
    $this->assertEquals('ldap_user_grab_password_validate', $form['#submit'][0]);
  }

  /**
   * Tests ldap_user_form_user_register_form_alter().
   */
  public function testLdapUserFormUserRegisterFormAlter(): void {
    $account = $this->createMock('Drupal\user\Entity\User');
    $account->expects($this->once())
      ->method('hasPermission')
      ->with('administer users')
      ->willReturn(TRUE);
    $this->container->set('current_user', $account);

    $config = $this->createMock('Drupal\Core\Config\ImmutableConfig');
    $config->expects($this->exactly(4))
      ->method('get')
      ->willReturnMap([
        ['disableAdminPasswordField', TRUE],
        ['ldapEntryProvisionTriggers', [LdapUserAttributesInterface::PROVISION_DRUPAL_USER_ON_USER_UPDATE_CREATE]],
      ]);

    $config_factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $config_factory->expects($this->once())
      ->method('get')
      ->with('ldap_user.settings')
      ->willReturn($config);
    $this->container->set('config.factory', $config_factory);

    $password_generator = $this->createMock('Drupal\Core\Password\PasswordGeneratorInterface');

    $this->container->set('password_generator', $password_generator);

    $form = [
      'actions' => [
        'submit' => [
          '#type' => 'submit',
        ],

      ],
      '#submit' => [
        'callback_1',
        'callback_2',
      ],
    ];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    ldap_user_form_user_register_form_alter($form, $form_state);

    $this->assertCount(3, $form['#submit']);
    $this->assertEquals('ldap_user_grab_password_validate', $form['#submit'][0]);
  }

  /**
   * Tests ldap_user_form_user_register_form_alter().
   */
  public function testLdapUserFormUserRegisterFormAlter2(): void {
    $account = $this->createMock('Drupal\user\Entity\User');
    $account->expects($this->once())
      ->method('hasPermission')
      ->with('administer users')
      ->willReturn(TRUE);
    $this->container->set('current_user', $account);

    $config = $this->createMock('Drupal\Core\Config\ImmutableConfig');
    $config->expects($this->exactly(4))
      ->method('get')
      ->willReturnMap([
        ['disableAdminPasswordField', TRUE],
        ['ldapEntryProvisionTriggers', []],
        ['manualAccountConflict'],
      ]);

    $config_factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $config_factory->expects($this->once())
      ->method('get')
      ->with('ldap_user.settings')
      ->willReturn($config);
    $this->container->set('config.factory', $config_factory);

    $password_generator = $this->createMock('Drupal\Core\Password\PasswordGeneratorInterface');

    $this->container->set('password_generator', $password_generator);

    $form = [
      'actions' => [
        'submit' => [
          '#type' => 'submit',
        ],

      ],
      '#submit' => [
        'callback_1',
        'callback_2',
      ],
    ];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    ldap_user_form_user_register_form_alter($form, $form_state);

    $this->assertCount(3, $form['#submit']);
    $this->assertEquals('ldap_user_grab_password_validate', $form['#submit'][0]);
  }

  /**
   * Tests ldap_user_form_user_register_form_alter().
   */
  public function testLdapUserFormUserRegisterFormAlter3(): void {
    $account = $this->createMock('Drupal\user\Entity\User');
    $account->expects($this->once())
      ->method('hasPermission')
      ->with('administer users')
      ->willReturn(TRUE);
    $this->container->set('current_user', $account);

    $config = $this->createMock('Drupal\Core\Config\ImmutableConfig');
    $config->expects($this->exactly(4))
      ->method('get')
      ->willReturnMap([
        ['disableAdminPasswordField', TRUE],
        ['ldapEntryProvisionTriggers', []],
        ['manualAccountConflict', LdapUserAttributesInterface::MANUAL_ACCOUNT_CONFLICT_SHOW_OPTION_ON_FORM],
      ]);

    $config_factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $config_factory->expects($this->once())
      ->method('get')
      ->with('ldap_user.settings')
      ->willReturn($config);
    $this->container->set('config.factory', $config_factory);

    $password_generator = $this->createMock('Drupal\Core\Password\PasswordGeneratorInterface');

    $this->container->set('password_generator', $password_generator);

    $form = [
      'actions' => [
        'submit' => [
          '#type' => 'submit',
        ],

      ],
      '#submit' => [
        'callback_1',
        'callback_2',
      ],
    ];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    ldap_user_form_user_register_form_alter($form, $form_state);

    $this->assertCount(3, $form['#submit']);
    $this->assertEquals('ldap_user_grab_password_validate', $form['#submit'][0]);
  }

  /**
   * Tests ldap_user_form_register_form_validate().
   */
  public function testLdapUserFormRegisterFormValidateNoAssociate(): void {
    $config = $this->createMock('Drupal\Core\Config\ImmutableConfig');
    $config->expects($this->once())
      ->method('get')
      ->willReturnMap([
        ['drupalAcctProvisionServer'],
        ['ldapEntryProvisionServer'],
      ]);

    $config_factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $config_factory->expects($this->once())
      ->method('get')
      ->with('ldap_user.settings')
      ->willReturn($config);
    $this->container->set('config.factory', $config_factory);

    $user_manager = $this->createMock('Drupal\ldap_servers\LdapUserManager');

    $this->container->set('ldap.user_manager', $user_manager);

    $logger_factory = $this->createMock('Drupal\Core\Logger\LoggerChannelFactoryInterface');
    $logger_factory->expects($this->once())
      ->method('get')
      ->with('ldap_user')
      ->willReturn($this->createMock('Psr\Log\LoggerInterface'));
    $this->container->set('logger.factory', $logger_factory);

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormState');
    $form_state->expects($this->exactly(5))
      ->method('getValue')
      ->willReturnMap([
        ['ldap_user_association', NULL, LdapUserAttributesInterface::MANUAL_ACCOUNT_CONFLICT_NO_LDAP_ASSOCIATE],
        ['ldap_user_create_ldap_acct', NULL, 'an email'],
      ]);
    $form_state->expects($this->once())
      ->method('set')
      ->with('ldap_user_ldap_exclude', 1);

    ldap_user_form_register_form_validate($form, $form_state);
  }

  /**
   * Tests ldap_user_form_register_form_validate().
   */
  public function testLdapUserFormRegisterFormValidate(): void {
    $config = $this->createMock('Drupal\Core\Config\ImmutableConfig');
    $config->expects($this->exactly(3))
      ->method('get')
      ->willReturnMap([
        ['drupalAcctProvisionServer'],
        ['ldapEntryProvisionServer', 'a_server'],
        ['manualAccountConflict', 'conflict_show_option'],
      ]);

    $config_factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $config_factory->expects($this->once())
      ->method('get')
      ->with('ldap_user.settings')
      ->willReturn($config);
    $this->container->set('config.factory', $config_factory);

    $ldap_user = $this->createMock('Symfony\Component\Ldap\Entry');
    $ldap_user->expects($this->once())
      ->method('getDn')
      ->willReturn('a dn');

    $user_manager = $this->createMock('Drupal\ldap_servers\LdapUserManager');
    $user_manager->expects($this->once())
      ->method('getUserDataByIdentifier')
      ->with('hpotter')
      ->willReturn($ldap_user);

    $this->container->set('ldap.user_manager', $user_manager);

    $logger_factory = $this->createMock('Drupal\Core\Logger\LoggerChannelFactoryInterface');
    $this->container->set('logger.factory', $logger_factory);

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormState');
    $form_state->expects($this->exactly(7))
      ->method('getValue')
      ->willReturnMap([
        ['name', NULL, 'hpotter'],
        ['ldap_user_association', NULL, NULL],
        ['ldap_user_create_ldap_acct', NULL, 'an email'],
      ]);
    $form_state->expects($this->once())
      ->method('setValue')
      ->with('ldap_user_association', 'conflict_show_option');

    ldap_user_form_register_form_validate($form, $form_state);
  }

  /**
   * Tests ldap_user_form_register_form_validate().
   */
  public function testLdapUserFormRegisterFormValidateConflictAssociate(): void {
    $config = $this->createMock('Drupal\Core\Config\ImmutableConfig');
    $config->expects($this->once())
      ->method('get')
      ->willReturnMap([
        ['drupalAcctProvisionServer'],
        ['ldapEntryProvisionServer', 'a_server'],
        ['manualAccountConflict', 'conflict_show_option'],
      ]);

    $config_factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $config_factory->expects($this->once())
      ->method('get')
      ->with('ldap_user.settings')
      ->willReturn($config);
    $this->container->set('config.factory', $config_factory);

    $ldap_user = $this->createMock('Symfony\Component\Ldap\Entry');

    $user_manager = $this->createMock('Drupal\ldap_servers\LdapUserManager');

    $this->container->set('ldap.user_manager', $user_manager);

    $logger_factory = $this->createMock('Drupal\Core\Logger\LoggerChannelFactoryInterface');
    $logger_factory->expects($this->once())
      ->method('get')
      ->with('ldap_user')
      ->willReturn($this->createMock('Psr\Log\LoggerInterface'));
    $this->container->set('logger.factory', $logger_factory);

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormState');
    $form_state->expects($this->exactly(6))
      ->method('getValue')
      ->willReturnMap([
        ['name', NULL, 'hpotter'],
        ['ldap_user_association', NULL, LdapUserAttributesInterface::MANUAL_ACCOUNT_CONFLICT_LDAP_ASSOCIATE],
        ['ldap_user_create_ldap_acct', NULL, FALSE],
      ]);

    ldap_user_form_register_form_validate($form, $form_state);
  }

  /**
   * Tests ldap_user_form_register_form_validate().
   */
  public function testLdapUserFormRegisterFormValidateConflictReject(): void {
    $config = $this->createMock('Drupal\Core\Config\ImmutableConfig');
    $config->expects($this->exactly(2))
      ->method('get')
      ->willReturnMap([
        ['drupalAcctProvisionServer', 'a_server'],
        ['ldapEntryProvisionServer', 'a_server'],
        ['manualAccountConflict', 'conflict_show_option'],
      ]);

    $config_factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $config_factory->expects($this->once())
      ->method('get')
      ->with('ldap_user.settings')
      ->willReturn($config);
    $this->container->set('config.factory', $config_factory);

    $ldap_user = $this->createMock('Symfony\Component\Ldap\Entry');
    $ldap_user->expects($this->once())
      ->method('getDn')
      ->willReturn('a dn');

    $user_manager = $this->createMock('Drupal\ldap_servers\LdapUserManager');
    $user_manager->expects($this->once())
      ->method('getUserDataByIdentifier')
      ->with('hpotter')
      ->willReturn($ldap_user);
    $this->container->set('ldap.user_manager', $user_manager);

    $logger_factory = $this->createMock('Drupal\Core\Logger\LoggerChannelFactoryInterface');
    $this->container->set('logger.factory', $logger_factory);

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormState');
    $form_state->expects($this->exactly(8))
      ->method('getValue')
      ->willReturnMap([
        ['name', NULL, 'hpotter'],
        ['ldap_user_association', NULL, LdapUserAttributesInterface::MANUAL_ACCOUNT_CONFLICT_REJECT],
        ['ldap_user_create_ldap_acct', NULL, FALSE],
      ]);

    ldap_user_form_register_form_validate($form, $form_state);
  }

  /**
   * Tests ldap_user_form_register_form_validate().
   */
  public function testLdapUserFormRegisterFormValidateConflictRejectNoServer(): void {
    $config = $this->createMock('Drupal\Core\Config\ImmutableConfig');
    $config->expects($this->once())
      ->method('get')
      ->willReturnMap([
        ['drupalAcctProvisionServer', FALSE],
        ['ldapEntryProvisionServer', 'a_server'],
        ['manualAccountConflict', 'conflict_show_option'],
      ]);

    $config_factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $config_factory->expects($this->once())
      ->method('get')
      ->with('ldap_user.settings')
      ->willReturn($config);
    $this->container->set('config.factory', $config_factory);

    $user_manager = $this->createMock('Drupal\ldap_servers\LdapUserManager');
    $this->container->set('ldap.user_manager', $user_manager);

    $logger_factory = $this->createMock('Drupal\Core\Logger\LoggerChannelFactoryInterface');
    $logger_factory->expects($this->once())
      ->method('get')
      ->with('ldap_user')
      ->willReturn($this->createMock('Psr\Log\LoggerInterface'));
    $this->container->set('logger.factory', $logger_factory);

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormState');
    $form_state->expects($this->exactly(6))
      ->method('getValue')
      ->willReturnMap([
        ['name', NULL, 'hpotter'],
        ['ldap_user_association', NULL, LdapUserAttributesInterface::MANUAL_ACCOUNT_CONFLICT_REJECT],
        ['ldap_user_create_ldap_acct', NULL, FALSE],
      ]);

    ldap_user_form_register_form_validate($form, $form_state);
  }

  /**
   * Tests ldap_user_form_register_form_submit2().
   */
  public function testLdapUserFormRegisterFormSubmit2Conflict(): void {
    $messenger = $this->createMock('Drupal\Core\Messenger\MessengerInterface');
    $messenger->expects($this->once())
      ->method('addWarning');
    $this->container->set('messenger', $messenger);

    $processor = $this->createMock('Drupal\ldap_user\Processor\DrupalUserProcessor');
    $processor->expects($this->once())
      ->method('ldapAssociateDrupalAccount')
      ->with('a name');
    $this->container->set('ldap.drupal_user_processor', $processor);

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormState');
    $form_state->expects($this->once())
      ->method('getValues')
      ->willReturn([
        'name' => 'a name',
        'mail' => 'an email',
        'pass' => 'a password',
        'ldap_user_association' => LdapUserAttributesInterface::MANUAL_ACCOUNT_CONFLICT_LDAP_ASSOCIATE,
      ]);
    ldap_user_form_register_form_submit2($form, $form_state);
  }

  /**
   * Tests ldap_user_entity_base_field_info_alter().
   */
  public function testLdapUserEntityBaseFieldInfoAlter(): void {
    $pass_field = $this->createMock('Drupal\Core\Field\BaseFieldDefinition');
    $pass_field->expects($this->once())
      ->method('getConstraints')
      ->willReturn([
        'LdapProtectedUserField' => [],
        'ProtectedUserField' => [],
      ]);

    $mail_field = $this->createMock('Drupal\Core\Field\BaseFieldDefinition');
    $mail_field->expects($this->once())
      ->method('getConstraints')
      ->willReturn([
        'LdapProtectedUserField' => [],
        'ProtectedUserField' => [],
      ]);

    $fields = [
      'pass' => $pass_field,
      'mail' => $mail_field,
    ];
    $user_type = $this->createMock('Drupal\Core\Entity\EntityTypeInterface');
    $user_type->expects($this->once())
      ->method('id')
      ->willReturn('user');

    $module_handler = $this->createMock('Drupal\Core\Extension\ModuleHandlerInterface');
    $module_handler->expects($this->once())
      ->method('moduleExists')
      ->with('ldap_authentication')
      ->willReturn(TRUE);
    $this->container->set('module_handler', $module_handler);

    ldap_user_entity_base_field_info_alter($fields, $user_type);
  }

}
