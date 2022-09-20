<?php

declare(strict_types = 1);

namespace Drupal\Tests\ldap_authentication\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\ldap_servers\Entity\Server;

/**
 * Test the hooks in the module file.
 *
 * @group ldap_authentication
 */
class HookTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'externalauth',
    'ldap_authentication',
    'ldap_query',
    'ldap_servers',
    'ldap_user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('ldap_authentication');
    $this->installEntitySchema('ldap_server');
  }

  /**
   * Test form validation.
   */
  public function testFormValidateShift(): void {
    $form = [
      '#validate' => [
        'a_submission_hook',
        'another_hook',
      ],
    ];
    $formState = new FormState();
    ldap_authentication_form_user_pass_alter($form, $formState);
    self::assertEquals([
      'ldap_authentication_user_pass_validate',
      'a_submission_hook',
      'another_hook',
    ], $form['#validate']);
  }

  /**
   * Test altering the login forms.
   */
  public function testAlterLoginForm(): void {
    $form = [
      '#validate' => [
        'something_else',
        '::validateAuthentication',
        'and_another_thing',
      ],
    ];

    $this->config('ldap_authentication.settings')
      ->set('authenticationMode', 'mixed')
      ->set('sids', ['example' => 'example'])
      ->set('loginUIUsernameTxt', 'overridden name')
      ->set('loginUIPasswordTxt', 'overridden pass')
      ->set('emailTemplateUsageRedirectOnLogin', TRUE)
      ->save();

    $server = Server::create([
      'id' => 'example',
      'basedn' => ['ou=people,dc=hogwarts,dc=edu'],
      'user_attr' => 'cn',
      'mail_attr' => 'mail',
    ]);
    $server->save();

    $formState = new FormState();
    ldap_authentication_form_user_login_form_alter($form, $formState, 'user_login');
    self::assertEquals([
      'something_else',
      'ldap_authentication_core_override_user_login_authenticate_validate',
      'ldap_authentication_user_login_authenticate_validate',
      'and_another_thing',
    ], $form['#validate']);
    self::assertEquals('overridden name', $form['name']['#description']);
    self::assertEquals('overridden pass', $form['pass']['#description']);
    self::assertArrayHasKey(0, $form['#submit']);
  }

}
