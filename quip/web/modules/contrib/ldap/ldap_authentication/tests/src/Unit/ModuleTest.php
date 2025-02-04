<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_authentication\Unit;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

require_once __DIR__ . '/../../../ldap_authentication.module';

/**
 * Test the hook_help function.
 *
 * @package Drupal\Tests\ldap_authentication\Unit
 */
class ModuleTest extends UnitTestCase {

  /**
   * The container interface.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The external auth.
   *
   * @var \Drupal\externalauth\ExternalAuthInterface
   */
  protected $externalAuth;

  /**
   * The authentication servers.
   *
   * @var \Drupal\ldap_authentication\AuthenticationServers
   */
  protected $authenticationServers;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container = new ContainerBuilder();

    $string_translation = $this->getStringTranslationStub();
    $this->container->set('string_translation', $string_translation);

    $this->configFactory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $this->container->set('config.factory', $this->configFactory);

    $this->entityTypeManager = $this->createMock('Drupal\Core\Entity\EntityTypeManagerInterface');
    $this->container->set('entity_type.manager', $this->entityTypeManager);

    $this->userStorage = $this->createMock('Drupal\user\UserStorageInterface');

    $this->externalAuth = $this->createMock('Drupal\externalauth\AuthmapInterface');
    $this->container->set('externalauth.authmap', $this->externalAuth);

    $this->authenticationServers = $this->createMock('Drupal\ldap_authentication\AuthenticationServers');
    $this->container->set('ldap_authentication.servers', $this->authenticationServers);

    \Drupal::setContainer($this->container);

  }

  /**
   * Test hook_help().
   *
   * @coversFunction ::ldap_authentication_help
   */
  public function testHookHelpRouteHelpPage(): void {
    $route_match = $this->createMock('Drupal\Core\Routing\RouteMatchInterface');

    $output = ldap_authentication_help('help.page.ldap_authentication', $route_match);
    $this->assertNotEmpty($output);
  }

  /**
   * Test hook_help().
   *
   * @coversFunction ::ldap_authentication_help
   */
  public function testHookHelpSettingsForm(): void {
    $route_match = $this->createMock('Drupal\Core\Routing\RouteMatchInterface');

    $output = ldap_authentication_help('ldap_authentication.admin_form', $route_match);
    $this->assertNotEmpty($output);
  }

  /**
   * Test hook_help().
   *
   * @coversFunction ::ldap_authentication_help
   */
  public function testHookHelpRouteNoHelpProvided(): void {
    $route_match = $this->createMock('Drupal\Core\Routing\RouteMatchInterface');

    $output = ldap_authentication_help('entity.ldap_authentication.nothing', $route_match);
    $this->assertEmpty($output);
  }

  /**
   * Test hook_help().
   *
   * @coversFunction ::ldap_authentication_help
   */
  public function testAlterUserPassForm(): void {
    $form = [
      '#validate' => [
        'some_form_validation_handler',
      ],
    ];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    ldap_authentication_form_user_pass_alter($form, $form_state);

    $this->assertCount(2, $form['#validate']);
    $this->assertEquals('ldap_authentication_user_pass_validate', $form['#validate'][0]);
    $this->assertEquals('some_form_validation_handler', $form['#validate'][1]);
  }

  /**
   * Test hook_help().
   *
   * @coversFunction ::ldap_authentication_help
   */
  public function testCoreOverrideUserLoginNotEmpty(): void {
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->once())
      ->method('get')
      ->with('uid')
      ->willReturn(1);

    ldap_authentication_core_override_user_login_authenticate_validate([], $form_state);
  }

  /**
   * Test hook_help().
   *
   * @coversFunction ::ldap_authentication_help
   */
  public function testCoreOverrideUserLogin(): void {
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->once())
      ->method('get')
      ->with('uid')
      ->willReturn(0);

    $original_form = $this->createMock('Drupal\user\Form\UserLoginForm');
    $original_form->expects($this->once())
      ->method('validateAuthentication')
      ->with([], $form_state);

    $form_state->expects($this->once())
      ->method('getFormObject')
      ->willReturn($original_form);

    ldap_authentication_core_override_user_login_authenticate_validate([], $form_state);
  }

  /**
   * Test hook_help().
   *
   * @coversFunction ::ldap_authentication_help
   */
  public function testUserPassValidatePasswordResetAllowed(): void {
    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $config = $this->createMock('Drupal\Core\Config\ImmutableConfig');
    $config->expects($this->once())
      ->method('get')
      ->with('passwordOption')
      ->willReturn('allow');
    $this->configFactory->expects($this->once())
      ->method('get')
      ->with('ldap_authentication.settings')
      ->willReturn($config);

    ldap_authentication_user_pass_validate($form, $form_state);
  }

  /**
   * Test ldap_authentication_user_pass_validate().
   */
  public function testUserPassValidatePasswordResetNoAccount(): void {
    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->once())
      ->method('getValue')
      ->with('name')
      ->willReturn('hpotter');

    $config = $this->createMock('Drupal\Core\Config\ImmutableConfig');
    $config->expects($this->once())
      ->method('get')
      ->with('passwordOption')
      ->willReturn('notAllow');
    $this->configFactory->expects($this->once())
      ->method('get')
      ->with('ldap_authentication.settings')
      ->willReturn($config);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('user')
      ->willReturn($this->userStorage);

    ldap_authentication_user_pass_validate($form, $form_state);
  }

  /**
   * Test ldap_authentication_user_pass_validate().
   */
  public function testUserPassValidatePasswordResetNoExternalAuthMapping(): void {
    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->once())
      ->method('getValue')
      ->with('name')
      ->willReturn('harry.potter@hogwarts.edu');

    $config = $this->createMock('Drupal\Core\Config\ImmutableConfig');
    $config->expects($this->once())
      ->method('get')
      ->with('passwordOption')
      ->willReturn('notAllow');
    $this->configFactory->expects($this->once())
      ->method('get')
      ->with('ldap_authentication.settings')
      ->willReturn($config);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('user')
      ->willReturn($this->userStorage);
    $user = $this->createMock('Drupal\user\UserInterface');
    $this->userStorage->expects($this->once())
      ->method('loadByProperties')
      ->with(['mail' => 'harry.potter@hogwarts.edu'])
      ->willReturn([$user]);

    ldap_authentication_user_pass_validate($form, $form_state);
  }

  /**
   * Test ldap_authentication_user_pass_validate().
   */
  public function testUserPassValidatePasswordResetNoHelpLink(): void {
    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->once())
      ->method('getValue')
      ->with('name')
      ->willReturn('harry.potter@hogwarts.edu');

    $config = $this->createMock('Drupal\Core\Config\ImmutableConfig');
    $config->expects($this->exactly(2))
      ->method('get')
      ->willReturnMap([
        ['passwordOption', 'notAllow'],
        ['ldapUserHelpLinkUrl', ''],
      ]);
    $this->configFactory->expects($this->once())
      ->method('get')
      ->with('ldap_authentication.settings')
      ->willReturn($config);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('user')
      ->willReturn($this->userStorage);
    $user = $this->createMock('Drupal\user\UserInterface');
    $user->expects($this->once())
      ->method('id')
      ->willReturn(2);

    $this->userStorage->expects($this->once())
      ->method('loadByProperties')
      ->with(['mail' => 'harry.potter@hogwarts.edu'])
      ->willReturn([$user]);

    $this->externalAuth->expects($this->once())
      ->method('get')
      ->with(2, 'ldap_user')
      ->willReturn('hpotter');

    ldap_authentication_user_pass_validate($form, $form_state);
  }

  /**
   * Test ldap_authentication_user_pass_validate().
   */
  public function testUserPassValidatePasswordReset(): void {
    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->once())
      ->method('getValue')
      ->with('name')
      ->willReturn('harry.potter@hogwarts.edu');

    $config = $this->createMock('Drupal\Core\Config\ImmutableConfig');
    $config->expects($this->exactly(4))
      ->method('get')
      ->willReturnMap([
        ['passwordOption', 'notAllow'],
        ['ldapUserHelpLinkUrl', '/ldap-user-help'],
        ['ldapUserHelpLinkText', 'LDAP User help'],
      ]);
    $this->configFactory->expects($this->once())
      ->method('get')
      ->with('ldap_authentication.settings')
      ->willReturn($config);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('user')
      ->willReturn($this->userStorage);
    $user = $this->createMock('Drupal\user\UserInterface');
    $user->expects($this->once())
      ->method('id')
      ->willReturn(2);

    $this->userStorage->expects($this->once())
      ->method('loadByProperties')
      ->with(['mail' => 'harry.potter@hogwarts.edu'])
      ->willReturn([$user]);

    $this->externalAuth->expects($this->once())
      ->method('get')
      ->with(2, 'ldap_user')
      ->willReturn('hpotter');

    ldap_authentication_user_pass_validate($form, $form_state);
  }

  /**
   * Test ldap_authentication_user_pass_validate().
   */
  public function testUserFormAlterNoAccount(): void {

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');

    $user = $this->createMock('Drupal\user\UserInterface');
    $user->expects($this->once())
      ->method('id')
      ->willReturn(2);
    $callback_object = $this->createMock('Drupal\Core\Entity\EntityForm');
    $callback_object->expects($this->once())
      ->method('getEntity')
      ->willReturn($user);

    $form_state->expects($this->once())
      ->method('getBuildInfo')
      ->willReturn(['callback_object' => $callback_object]);

    ldap_authentication_form_user_form_alter($form, $form_state);
  }

  /**
   * Test ldap_authentication_user_pass_validate().
   */
  public function testUserFormAlterEmailRemovedPasswordHide(): void {

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');

    $user = $this->createMock('Drupal\user\UserInterface');
    $user->expects($this->exactly(2))
      ->method('id')
      ->willReturn(2);
    $user->expects($this->once())
      ->method('hasPermission')
      ->with('administer users')
      ->willReturn(FALSE);
    $callback_object = $this->createMock('Drupal\Core\Entity\EntityForm');
    $callback_object->expects($this->once())
      ->method('getEntity')
      ->willReturn($user);

    $form_state->expects($this->once())
      ->method('getBuildInfo')
      ->willReturn(['callback_object' => $callback_object]);

    $this->externalAuth->expects($this->exactly(2))
      ->method('get')
      ->with(2, 'ldap_user')
      ->willReturn('hpotter');

    $config = $this->createMock('Drupal\Core\Config\ImmutableConfig');
    $config->expects($this->exactly(3))
      ->method('get')
      ->willReturnMap([
        ['emailOption', 'remove'],
        ['passwordOption', 'hide'],
      ]);
    $this->configFactory->expects($this->exactly(2))
      ->method('get')
      ->with('ldap_authentication.settings')
      ->willReturn($config);

    ldap_authentication_form_user_form_alter($form, $form_state);
    $this->assertFalse($form['account']['mail']['#access']);
  }

  /**
   * Test ldap_authentication_user_pass_validate().
   */
  public function testUserFormAlterEmailDisabledPasswordDisabled(): void {

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');

    $user = $this->createMock('Drupal\user\UserInterface');
    $user->expects($this->exactly(2))
      ->method('id')
      ->willReturn(2);
    $user->expects($this->once())
      ->method('hasPermission')
      ->with('administer users')
      ->willReturn(FALSE);
    $callback_object = $this->createMock('Drupal\Core\Entity\EntityForm');
    $callback_object->expects($this->once())
      ->method('getEntity')
      ->willReturn($user);

    $form_state->expects($this->once())
      ->method('getBuildInfo')
      ->willReturn(['callback_object' => $callback_object]);

    $this->externalAuth->expects($this->exactly(2))
      ->method('get')
      ->with(2, 'ldap_user')
      ->willReturn('hpotter');

    $config = $this->createMock('Drupal\Core\Config\ImmutableConfig');
    $config->expects($this->exactly(8))
      ->method('get')
      ->willReturnMap([
        ['emailOption', 'disable'],
        ['passwordOption', 'disable'],
        ['ldapUserHelpLinkUrl', 'http://example.com/ldap-user-help'],
        ['ldapUserHelpLinkText', 'LDAP User help'],
      ]);
    $this->configFactory->expects($this->exactly(2))
      ->method('get')
      ->with('ldap_authentication.settings')
      ->willReturn($config);

    ldap_authentication_form_user_form_alter($form, $form_state);
    $this->assertTrue($form['account']['current_pass']['#disabled']);
    $this->assertTrue($form['account']['pass']['#disabled']);
  }

  /**
   * Test ldap_authentication_user_pass_validate().
   */
  public function testUserFormAlterEmailRemovePasswordDisabled(): void {

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');

    $user = $this->createMock('Drupal\user\UserInterface');
    $user->expects($this->exactly(2))
      ->method('id')
      ->willReturn(2);
    $user->expects($this->once())
      ->method('hasPermission')
      ->with('administer users')
      ->willReturn(FALSE);
    $callback_object = $this->createMock('Drupal\Core\Entity\EntityForm');
    $callback_object->expects($this->once())
      ->method('getEntity')
      ->willReturn($user);

    $form_state->expects($this->once())
      ->method('getBuildInfo')
      ->willReturn(['callback_object' => $callback_object]);

    $this->externalAuth->expects($this->exactly(2))
      ->method('get')
      ->with(2, 'ldap_user')
      ->willReturn('hpotter');

    $config = $this->createMock('Drupal\Core\Config\ImmutableConfig');
    $config->expects($this->exactly(6))
      ->method('get')
      ->willReturnMap([
        ['emailOption', 'disable'],
        ['passwordOption', 'disable'],
        ['ldapUserHelpLinkUrl', ''],
      ]);
    $this->configFactory->expects($this->exactly(2))
      ->method('get')
      ->with('ldap_authentication.settings')
      ->willReturn($config);

    ldap_authentication_form_user_form_alter($form, $form_state);
    $this->assertCount(1, $form);
    $this->assertCount(3, $form['account']);
    $this->assertEquals('The password cannot be changed using this website.', (string) $form['account']['current_pass']['#description']);
  }

  /**
   * Test ldap_authentication_show_password_field().
   */
  public function testLdapAuthenticationShowPasswordFieldCurrentUser(): void {
    $user = $this->createMock('Drupal\user\UserInterface');
    $user->expects($this->once())
      ->method('hasPermission')
      ->with('administer users')
      ->willReturn(TRUE);
    $this->container->set('current_user', $user);

    $result = ldap_authentication_show_password_field();
    $this->assertTrue($result);
  }

  /**
   * Test ldap_authentication_show_password_field().
   */
  public function testLdapAuthenticationShowPasswordFieldNoExternal(): void {
    $user = $this->createMock('Drupal\user\UserInterface');
    $user->expects($this->once())
      ->method('hasPermission')
      ->with('administer users')
      ->willReturn(FALSE);
    $this->container->set('current_user', $user);

    $result = ldap_authentication_show_password_field();
    $this->assertTrue($result);
  }

  /**
   * Tests ldap_authentication_form_user_login_block_alter().
   */
  public function testUserLoginBlockAlter(): void {
    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');

    ldap_authentication_form_user_login_block_alter($form, $form_state);
  }

  /**
   * Tests ldap_authentication_form_user_login_form_alter().
   */
  public function testUserLoginFormAlter(): void {
    $form = [
      '#validate' => ['::validateAuthentication'],
    ];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');

    $this->authenticationServers->expects($this->once())
      ->method('authenticationServersAvailable')
      ->willReturn(TRUE);

    $config = $this->createMock('Drupal\Core\Config\ImmutableConfig');
    $config->expects($this->exactly(6))
      ->method('get')
      ->willReturnMap([
        ['authenticationMode', 'exclusive'],
        ['loginUIUsernameTxt', 'Some custom text'],
        ['loginUIPasswordTxt', 'Different custom text'],
        ['emailTemplateUsageRedirectOnLogin', TRUE],
      ]);
    $this->configFactory->expects($this->once())
      ->method('get')
      ->with('ldap_authentication.settings')
      ->willReturn($config);

    ldap_authentication_form_user_login_form_alter($form, $form_state, 'form_id');
  }

  /**
   * Tests ldap_authentication_user_login_authenticate_validate().
   */
  public function testUserLoginAuthenticateValidate(): void {
    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->exactly(2))
      ->method('getValue')
      ->willReturnMap([
        ['pass', NULL, 'password'],
        ['name', NULL, 'hpotter'],
      ]);

    $login_validator = $this->createMock('Drupal\ldap_authentication\Controller\LoginValidatorLoginForm');
    $this->container->set('ldap_authentication.login_validator', $login_validator);

    ldap_authentication_user_login_authenticate_validate($form, $form_state);
  }

}
