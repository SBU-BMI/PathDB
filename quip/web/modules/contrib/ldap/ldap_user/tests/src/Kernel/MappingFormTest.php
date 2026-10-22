<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_user\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_user\Form\LdapUserMappingToDrupalForm;
use Drupal\ldap_user\Form\LdapUserMappingToLdapForm;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests LDAP user mapping admin forms.
 */
#[Group('ldap')]
#[RunTestsInSeparateProcesses]
class MappingFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'externalauth',
    'ldap_authentication',
    'ldap_query',
    'ldap_servers',
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
    $this->installConfig(['ldap_user']);

    Server::create([
      'id' => 'example',
      'basedn' => ['ou=people,dc=hogwarts,dc=edu'],
      'user_attr' => 'cn',
      'mail_attr' => 'mail',
    ])->save();
    $this->config('ldap_user.settings')
      ->set('drupalAcctProvisionServer', 'example')
      ->set('ldapEntryProvisionServer', 'example')
      ->save();
  }

  /**
   * Drupal mapping form builds and saves a mapping row.
   */
  public function testToDrupalFormBuildAndSubmit(): void {
    $form_object = LdapUserMappingToDrupalForm::create($this->container);
    $form = [];
    $form_state = new FormState();
    $form = $form_object->buildForm($form, $form_state);
    self::assertArrayHasKey('mappings', $form);

    $form_state->setValues([
      'mappings' => [
        [
          'source' => '[sn]',
          'target' => '[field.field_last_name]',
          'convert' => 0,
          'delete' => 0,
          'configured_mapping' => TRUE,
          'create_drupal_user' => 1,
          'sync_to_drupal_user' => 0,
        ],
      ],
    ]);
    $form_object->validateForm($form, $form_state);
    $form_object->submitForm($form, $form_state);
    $mappings = $this->config('ldap_user.settings')->get('ldapUserSyncMappings');
    self::assertNotEmpty($mappings['drupal']);
  }

  /**
   * LDAP mapping form builds.
   */
  public function testToLdapFormBuild(): void {
    $form_object = LdapUserMappingToLdapForm::create($this->container);
    $form = [];
    $form_state = new FormState();
    $form = $form_object->buildForm($form, $form_state);
    self::assertArrayHasKey('mappings', $form);
    self::assertEquals('ldap_user_admin_form', $form_object->getFormId());
  }

}
