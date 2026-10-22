<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_user\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers\LdapUserAttributesInterface;
use Drupal\ldap_servers_dummy\FakeBridge;
use Drupal\ldap_servers_dummy\FakeCollection;
use Drupal\ldap_user\Form\LdapUserTestForm;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Ldap\Entry;

/**
 * Tests the LDAP user debug form.
 */
#[Group('ldap')]
#[RunTestsInSeparateProcesses]
class LdapUserTestFormTest extends EntityKernelTestBase implements LdapUserAttributesInterface {

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
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installConfig(['ldap_user']);
    $this->installEntitySchema('ldap_server');
    $this->installSchema('externalauth', 'authmap');

    Server::create([
      'id' => 'example',
      'status' => TRUE,
      'basedn' => ['ou=people,dc=hogwarts,dc=edu'],
      'user_attr' => 'cn',
      'mail_attr' => 'mail',
    ])->save();
    $this->config('ldap_user.settings')
      ->set('drupalAcctProvisionServer', 'example')
      ->set('ldapEntryProvisionServer', '')
      ->set('drupalAcctProvisionTriggers', [
        self::PROVISION_DRUPAL_USER_ON_USER_AUTHENTICATION,
      ])
      ->set('ldapEntryProvisionTriggers', [])
      ->save();

    $bridge = new FakeBridge(
      $this->container->get('logger.channel.ldap_servers'),
      $this->container->get('entity_type.manager')
    );
    $bridge->setServer(
      $this->container->get('entity_type.manager')
        ->getStorage('ldap_server')
        ->load('example')
    );
    $bridge->setBindResult(TRUE);
    /** @var \Drupal\ldap_servers_dummy\FakeLdap $ldap */
    $ldap = $bridge->get();
    $ldap->setQueryResult([
      '(cn=hpotter)' => new FakeCollection([
        new Entry('cn=hpotter,ou=people,dc=hogwarts,dc=edu', [
          'cn' => ['hpotter'],
          'mail' => ['hpotter@example.com'],
        ]),
      ]),
    ]);
    $this->container->set('ldap.bridge', $bridge);
  }

  /**
   * Form builds radios and submit stores debug data.
   */
  public function testBuildAndSubmit(): void {
    $form_object = LdapUserTestForm::create($this->container);
    $form_state = new FormState();
    $form = $form_object->buildForm([], $form_state);
    self::assertEquals('ldap_user_test_form', $form_object->getFormId());
    self::assertArrayHasKey('testing_drupal_username', $form);
    self::assertArrayHasKey('action', $form);

    $form_state->setValue('testing_drupal_username', 'hpotter');
    $form_state->setValue(
      'action',
      self::PROVISION_DRUPAL_USER_ON_USER_AUTHENTICATION
    );
    $form_object->submitForm($form, $form_state);
    self::assertTrue($form_state->has('ldap_user_test_data'));
    $data = $form_state->get('ldap_user_test_data');
    self::assertEquals('hpotter', $data['username']);
  }

}
