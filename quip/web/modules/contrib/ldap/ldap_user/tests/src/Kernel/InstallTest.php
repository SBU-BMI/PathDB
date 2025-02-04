<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_user\Kernel;

use Drupal\KernelTests\KernelTestBase;

require_once __DIR__ . '/../../../ldap_user.install';

/**
 * Tests the installation of the module.
 *
 * @group ldap_user
 */
class InstallTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'externalauth',
    'ldap_servers',
    'ldap_user',
  ];

  /**
   * Server.
   *
   * @var \Drupal\ldap_servers\ServerInterface
   */
  protected $server;

  /**
   * Settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $settings;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    // $this->installSchema('ldap_user', ['ldap_user_mapping']);
    $this->installConfig(['ldap_user']);
    $server = \Drupal::service('entity_type.manager')->getStorage('ldap_server')->create([
      'id' => 'test_ldap_server',
      'label' => 'Test',
      'status' => 1,
      'address' => 'ldap://ldap.forum.com',
      'port' => 389,
      'encryption' => 'None',
      'root_dn' => 'dc=example,dc=com',
      'bind_method' => 'service_account',
      'service_account_username' => 'cn=read-only-admin,dc=example,dc=com',
      'service_account_password' => 'password',
      'basedn' => ['dc=example,dc=com'],
      'user_attr' => '',
      'mail_attr' => '',
      'user_filter' => '(&(objectClass=inetOrgPerson)(uid=*))',
      'group_attr' => 'cn',
      'group_filter' => '(&(objectClass=groupOfNames)(cn=*))',
      'group_members_attr' => 'member',
      'group_user_attr' => 'uid',
      'group_user_filter' => '(&(objectClass=inetOrgPerson)(uid=*))',
    ]);
    $server->save();
    $this->server = $server;
    $this->settings = \Drupal::configFactory()->getEditable('ldap_user.settings');
    $this->settings->set('drupalAcctProvisionServer', 'test_ldap_server');
    $this->settings->save();
  }

  /**
   * Test ldap_user_update_8408().
   */
  public function testLdapUserUpdate8408Behavior(): void {
    $mapping = $this->settings->get('ldapUserSyncMappings.drupal');
    $mapping['field-name'] = [
      'ldap_attr' => '[samaccountname]',
      'user_attr' => '[field.name]',
      'convert' => FALSE,
      'user_tokens' => '',
      'config_module' => 'ldap_user',
      'prov_module' => 'ldap_user',
      'prov_events' => [
        'create_drupal_user',
        'sync_to_drupal_user',
      ],
    ];

    $mapping['field-mail'] = [
      'ldap_attr' => '[mail]',
      'user_attr' => '[field.mail]',
      'convert' => FALSE,
      'user_tokens' => '',
      'config_module' => 'ldap_user',
      'prov_module' => 'ldap_user',
      'prov_events' => [
        'create_drupal_user',
        'sync_to_drupal_user',
      ],
    ];

    $this->settings->set('ldapUserSyncMappings.drupal', $mapping);
    /* cSpell:ignore behaviour */
    $this->settings->set('acctCreation', 'ldap_behaviour');
    $this->settings->save();

    ldap_user_update_8408();

    $server = \Drupal::service('entity_type.manager')
      ->getStorage('ldap_server')
      ->load('test_ldap_server');

    $this->assertEquals('ldap_behavior', $this->settings->get('acctCreation'));
    $this->assertEquals('samaccountname', $server->get('user_attr'));
    $this->assertEquals('mail', $server->get('mail_attr'));
  }

  /**
   * Test ldap_user_update_8408().
   */
  public function testLdapUserUpdate8408AccountName(): void {
    $mapping = $this->settings->get('ldapUserSyncMappings.drupal');
    $mapping['field-name'] = [
      'ldap_attr' => '[samaccountname]',
      'user_attr' => '[field.name]',
      'convert' => FALSE,
      'user_tokens' => '',
      'config_module' => 'ldap_user',
      'prov_module' => 'ldap_user',
      'prov_events' => [
        'create_drupal_user',
        'sync_to_drupal_user',
      ],
    ];

    $mapping['field-mail'] = [
      'ldap_attr' => '[mail]',
      'user_attr' => '[field.mail]',
      'convert' => FALSE,
      'user_tokens' => '',
      'config_module' => 'ldap_user',
      'prov_module' => 'ldap_user',
      'prov_events' => [
        'create_drupal_user',
        'sync_to_drupal_user',
      ],
    ];

    $this->settings->set('ldapUserSyncMappings.drupal', $mapping);
    $this->settings->save();

    $this->server->set('user_attr', 'cn');
    $this->server->save();

    ldap_user_update_8408();

    $server = \Drupal::service('entity_type.manager')
      ->getStorage('ldap_server')
      ->load('test_ldap_server');

    $this->assertEquals('samaccountname', $server->get('account_name_attr'));
    $this->assertEquals('cn', $server->get('user_attr'));
    $this->assertEquals('mail', $server->get('mail_attr'));
  }

  /**
   * Test ldap_user_update_8408().
   */
  public function testLdapUserUpdate8408RemovesExcludedFields(): void {
    $mapping = $this->settings->get('ldapUserSyncMappings.drupal');
    $mapping['field-name'] = [
      'ldap_attr' => '[samaccountname]',
      'user_attr' => '[field.name]',
      'convert' => FALSE,
      'user_tokens' => '',
      'config_module' => 'ldap_user',
      'prov_module' => 'ldap_user',
      'prov_events' => [
        'create_drupal_user',
        'sync_to_drupal_user',
      ],
    ];
    $mapping['field-mail'] = [
      'ldap_attr' => '[mail]',
      'user_attr' => '[field.mail]',
      'convert' => FALSE,
      'user_tokens' => '',
      'config_module' => 'ldap_user',
      'prov_module' => 'ldap_user',
      'prov_events' => [
        'create_drupal_user',
        'sync_to_drupal_user',
      ],
    ];
    $mapping['field-pass'] = [
      'ldap_attr' => '[pass]',
      'user_attr' => '[field.pass]',
      'convert' => FALSE,
      'user_tokens' => '',
      'config_module' => 'ldap_user',
      'prov_module' => 'ldap_user',
      'prov_events' => [
        'create_drupal_user',
        'sync_to_drupal_user',
      ],
    ];
    $mapping['field-roles'] = [
      'ldap_attr' => '[roles]',
      'user_attr' => '[field.roles]',
      'convert' => FALSE,
      'user_tokens' => '',
      'config_module' => 'ldap_user',
      'prov_module' => 'ldap_user',
      'prov_events' => [
        'create_drupal_user',
        'sync_to_drupal_user',
      ],
    ];
    $mapping['field-status'] = [
      'ldap_attr' => '[status]',
      'user_attr' => '[field.status]',
      'convert' => FALSE,
      'user_tokens' => '',
      'config_module' => 'ldap_user',
      'prov_module' => 'ldap_user',
      'prov_events' => [
        'create_drupal_user',
        'sync_to_drupal_user',
      ],
    ];
    $mapping['field-langcode'] = [
      'ldap_attr' => '[langcode]',
      'user_attr' => '[field.langcode]',
      'convert' => FALSE,
      'user_tokens' => '',
      'config_module' => 'ldap_user',
      'prov_module' => 'ldap_user',
      'prov_events' => [
        'create_drupal_user',
        'sync_to_drupal_user',
      ],
    ];

    $this->settings->set('ldapUserSyncMappings.drupal', $mapping);
    $this->settings->save();

    ldap_user_update_8408();

    $mapping = $this->settings->get('ldapUserSyncMappings.drupal');

    $this->assertArrayHasKey('field-langcode', $mapping);
    $this->assertArrayNotHasKey('field-name', $mapping);
    $this->assertArrayNotHasKey('field-mail', $mapping);
    $this->assertArrayNotHasKey('field-pass', $mapping);
    $this->assertArrayNotHasKey('field-status', $mapping);
  }

}
