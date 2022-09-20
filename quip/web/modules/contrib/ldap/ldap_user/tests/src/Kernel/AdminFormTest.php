<?php

namespace Drupal\Tests\ldap_user\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_user\Form\LdapUserAdminForm;

/**
 * Admin Form test.
 *
 * @group ldap_user
 */
class AdminFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'externalauth',
    'ldap_authentication',
    'ldap_query',
    'ldap_servers',
    'ldap_user',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('ldap_user');
  }

  /**
   * Test form building.
   */
  public function testEmptyBuild(): void {
    $adminForm = new LdapUserAdminForm(
      $this->container->get('config.factory'),
      $this->container->get('module_handler'),
      $this->container->get('entity_type.manager')
    );

    $form = [];
    $formState = new FormState();
    $form = $adminForm->buildForm($form, $formState);
    self::assertStringContainsString('At least one LDAP server must be configured', $form['intro']['#markup']);
  }

  /**
   * Test form building.
   */
  public function testBuild(): void {
    $server = Server::create([
      'id' => 'example',
      'basedn' => ['ou=people,dc=hogwarts,dc=edu'],
      'user_attr' => 'cn',
      'mail_attr' => 'mail',
    ]);
    $server->save();

    $adminForm = new LdapUserAdminForm(
      $this->container->get('config.factory'),
      $this->container->get('module_handler'),
      $this->container->get('entity_type.manager')
    );

    $form = [];
    $formState = new FormState();
    $form = $adminForm->buildForm($form, $formState);
    self::assertEquals('conflict_reject', $form['manual_drupal_account_editing']['manualAccountConflict']['#default_value']);

    $formState->setValue('manualAccountConflict', 'test');
    $formState->setValue('drupalAcctProvisionTriggers', [
      'value_a' => 0,
      'value_b' => 'value_b',
    ]);
    $formState->setValue('ldapEntryProvisionTriggers', []);
    $adminForm->submitForm($form, $formState);
    self::assertEquals(
      'test',
      $this->config('ldap_user.settings')->get('manualAccountConflict')
    );
    self::assertEquals(
      ['value_b'],
      $this->config('ldap_user.settings')->get('drupalAcctProvisionTriggers')
    );
  }

}
