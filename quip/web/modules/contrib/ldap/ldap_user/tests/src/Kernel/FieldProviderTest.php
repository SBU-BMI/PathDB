<?php

declare(strict_types = 1);

namespace Drupal\Tests\ldap_user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers\LdapUserAttributesInterface;
use Drupal\ldap_user\FieldProvider;

/**
 * Field Provider tests.
 *
 * @group ldap
 */
class FieldProviderTest extends KernelTestBase implements LdapUserAttributesInterface {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ldap_servers',
    'ldap_user',
    'ldap_query',
    'externalauth',
    'user',
  ];

  /**
   * Server.
   *
   * @var \Drupal\ldap_servers\Entity\Server
   */
  protected $server;

  /**
   * Config input data.
   *
   * @var array
   */
  private $data;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('ldap_server');
    $this->installEntitySchema('user');
    $this->installConfig('ldap_user');
    $this->server = Server::create([
      'id' => 'example',
      'picture_attr' => 'picture_field',
      'user_attr' => 'cn',
      'mail_attr' => 'mail',
      'unique_persistent_attr' => 'guid',
      'drupalAcctProvisionServer' => 'example',
    ]);

    $this->data = [
      'drupal' => [
        'field-test_field' => [
          'ldap_attr' => '[cn]',
          'user_attr' => '[field.test_field]',
          'convert' => FALSE,
          'user_tokens' => '',
          'config_module' => 'ldap_user',
          'prov_module' => 'ldap_user',
          'prov_events' => [
            self::EVENT_CREATE_DRUPAL_USER,
          ],
        ],
        'property-name' => [
          'ldap_attr' => '[cn]',
          'user_attr' => '[property.name]',
          'convert' => TRUE,
          'user_tokens' => '',
          'config_module' => 'ldap_user',
          'prov_module' => 'ldap_user',
          'prov_events' => [
            self::EVENT_CREATE_DRUPAL_USER,
            self::EVENT_SYNC_TO_DRUPAL_USER,
          ],
        ],
      ],
      'ldap' => [
        'userPassword' => [
          'ldap_attr' => '[userPassword]',
          'user_attr' => '[password.user-only]',
          'convert' => FALSE,
          'user_tokens' => '',
          'config_module' => 'ldap_user',
          'prov_module' => 'ldap_user',
          'prov_events' => [
            self::EVENT_CREATE_LDAP_ENTRY,
            self::EVENT_SYNC_TO_LDAP_ENTRY,
          ],
        ],
        'property-not_synced' => [
          'ldap_attr' => '[userPassword]',
          'user_attr' => '',
          'convert' => FALSE,
          'user_tokens' => '',
          'config_module' => 'ldap_user',
          'prov_module' => 'ldap_user',
          'prov_events' => [
            self::EVENT_CREATE_LDAP_ENTRY,
            self::EVENT_SYNC_TO_LDAP_ENTRY,
          ],
        ],
      ],
    ];
  }

  /**
   * Prove that field syncs work and provide the demo data here.
   */
  public function testSyncValidatorIsSynced(): void {
    $container = \Drupal::getContainer();
    $config_factory = $container->get('config.factory');
    $config = $config_factory->getEditable('ldap_user.settings');
    $config
      ->set('ldapUserSyncMappings', $this->data)
      ->set('drupalAcctProvisionTriggers', [
        'drupal_on_login' => 'drupal_on_login',
        'drupal_on_update_create' => 'drupal_on_update_create',
      ])
      ->save();
    $processor = new FieldProvider(
      $config_factory,
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('entity_field.manager')
    );

    $processor->loadAttributes(LdapUserAttributesInterface::PROVISION_TO_DRUPAL, $this->server);
    $data = $processor->getConfigurableAttributesSyncedOnEvent(LdapUserAttributesInterface::EVENT_CREATE_DRUPAL_USER);
    self::assertCount(2, $data);
    self::assertCount(2, $data['[property.name]']->getProvisioningEvents());
    $data = $processor->getConfigurableAttributesSyncedOnEvent(LdapUserAttributesInterface::EVENT_SYNC_TO_LDAP_ENTRY);
    self::assertEmpty($data);

    $data = $processor->getAttributesSyncedOnEvent(LdapUserAttributesInterface::EVENT_CREATE_DRUPAL_USER);
    self::assertEquals('not configurable', $data['[field.ldap_user_current_dn]']->getNotes());
    self::assertTrue($data['[property.picture]']->isEnabled());
    self::assertEquals('ldap_user', $data['[property.picture]']->getProvisioningModule());
    self::assertEquals('[picture_field]', $data['[property.picture]']->getLdapAttribute());
    self::assertEquals('[mail]', $data['[property.mail]']->getLdapAttribute());

    self::assertTrue($processor->attributeIsSyncedOnEvent(
      '[property.name]',
      LdapUserAttributesInterface::EVENT_SYNC_TO_DRUPAL_USER));
    self::assertFalse($processor->attributeIsSyncedOnEvent(
      '[field.test_field]',
      LdapUserAttributesInterface::EVENT_SYNC_TO_DRUPAL_USER));

    self::assertEquals('[guid]', $data['[field.ldap_user_puid]']->getLdapAttribute());

    $this->server->set('mail_template', '[cn]@example.com');
    $processor->loadAttributes(LdapUserAttributesInterface::PROVISION_TO_DRUPAL, $this->server);
    $data = $processor->getAttributesSyncedOnEvent(LdapUserAttributesInterface::EVENT_SYNC_TO_DRUPAL_USER);
    self::assertEquals('[cn]@example.com', $data['[property.mail]']->getLdapAttribute());
  }

}
