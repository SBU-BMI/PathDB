<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_user\Unit;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

require_once __DIR__ . '/../../../ldap_user.install';

/**
 * Test the install functions.
 *
 * @package Drupal\Tests\ldap_user\Unit
 */
class InstallTest extends UnitTestCase {

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
   * Test ldap_user_module_preinstall().
   *
   * @coversFunction ::ldap_user_module_preinstall
   */
  public function testModulePreinstall(): void {
    require_once __DIR__ . '/../../../ldap_user.module';

    $user_type = $this->createMock('Drupal\Core\Entity\EntityTypeInterface');
    $user_type->expects($this->once())
      ->method('id')
      ->willReturn('user');

    $entity_type_manager = $this->createMock('Drupal\Core\Entity\EntityTypeManagerInterface');
    $entity_type_manager->expects($this->once())
      ->method('getDefinition')
      ->with('user')
      ->willReturn($user_type);

    $this->container->set('entity_type.manager', $entity_type_manager);

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

    $definition_update_manager = $this->createMock('Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface');
    $definition_update_manager->expects($this->exactly(7))
      ->method('installFieldStorageDefinition');
    $this->container->set('entity.definition_update_manager', $definition_update_manager);

    ldap_user_module_preinstall('ldap_user');
  }

  /**
   * Test ldap_user_update_8401().
   *
   * @coversFunction ::ldap_user_update_8401
   */
  public function testHookUpdate8401(): void {

    $module_handler = $this->createMock('Drupal\Core\Extension\ModuleHandlerInterface');
    $module_handler->expects($this->once())
      ->method('moduleExists')
      ->with('ldap_query')
      ->willReturn(FALSE);
    $this->container->set('module_handler', $module_handler);

    $module_installer = $this->createMock('Drupal\Core\Extension\ModuleInstallerInterface');
    $module_installer->expects($this->once())
      ->method('install')
      ->with(['ldap_query']);
    $this->container->set('module_installer', $module_installer);

    ldap_user_update_8401();
  }

  /**
   * Test ldap_user_update_8402().
   *
   * @coversFunction ::ldap_user_update_8402
   */
  public function testHookUpdate8402(): void {

    $storage_definition = $this->createMock('Drupal\Core\Field\FieldStorageDefinitionInterface');

    $definition_update_manager = $this->createMock('Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface');
    $definition_update_manager->expects($this->exactly(7))
      ->method('getFieldStorageDefinition')
      ->willReturnMap([
        ['ldap_user_puid_sid', 'user', $storage_definition],
        ['ldap_user_puid', 'user', $storage_definition],
        ['ldap_user_puid_property', 'user', $storage_definition],
        ['ldap_user_current_dn', 'user', $storage_definition],
        ['ldap_user_prov_entries', 'user', $storage_definition],
        ['ldap_user_last_checked', 'user', $storage_definition],
        ['ldap_user_ldap_exclude', 'user', $storage_definition],
      ]);
    $definition_update_manager->expects($this->exactly(7))
      ->method('updateFieldStorageDefinition')
      ->with($storage_definition);

    $this->container->set('entity.definition_update_manager', $definition_update_manager);

    ldap_user_update_8402();
  }

  /**
   * Test ldap_user_update_8407().
   *
   * @coversFunction ::ldap_user_update_8407
   */
  public function testHookUpdate8407(): void {

    $cache_default = $this->createMock('Drupal\Core\Cache\CacheBackendInterface');
    $cache_default->expects($this->once())
      ->method('deleteAll')
      ->with();
    $this->container->set('cache.default', $cache_default);

    $settings = $this->createMock('Drupal\Core\Config\Config');

    $settings->expects($this->exactly(2))
      ->method('set')
      ->willReturnMap([
        ['orphanedIncludeDisabledUsers', FALSE, NULL],
        ['userUpdateOnly', FALSE, NULL],
      ]);
    $settings->expects($this->once())
      ->method('save');

    $config_factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $config_factory->expects($this->once())
      ->method('getEditable')
      ->with('ldap_user.settings')
      ->willReturn($settings);
    $this->container->set('config.factory', $config_factory);

    ldap_user_update_8407();
  }

  /**
   * Test ldap_user_update_8408().
   *
   * @coversFunction ::ldap_user_update_8408
   */
  public function testHookUpdate8408NoServer(): void {
    $settings = $this->createMock('Drupal\Core\Config\Config');
    $settings->expects($this->exactly(2))
      ->method('get')
      /* cSpell:ignore behaviour */
      ->willReturnMap([
        ['acctCreation', 'ldap_behaviour'],
        ['drupalAcctProvisionServer', 'test_server'],
      ]);
    $settings->expects($this->once())
      ->method('set')
      ->with('acctCreation', 'ldap_behavior')
      ->willReturnSelf();
    $settings->expects($this->once())
      ->method('save');

    $config_factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $config_factory->expects($this->once())
      ->method('getEditable')
      ->with('ldap_user.settings')
      ->willReturn($settings);
    $this->container->set('config.factory', $config_factory);

    $entity_type_manager = $this->createMock('Drupal\Core\Entity\EntityTypeManagerInterface');
    $entity_type_manager->expects($this->once())
      ->method('getStorage')
      ->with('ldap_server')
      ->willReturn($this->createMock('Drupal\Core\Entity\EntityStorageInterface'));
    $this->container->set('entity_type.manager', $entity_type_manager);

    ldap_user_update_8408();
  }

  /**
   * Test ldap_user_update_8408().
   *
   * @coversFunction ::ldap_user_update_8408
   */
  public function testHookUpdate8408Behavior(): void {
    $settings = $this->createMock('Drupal\Core\Config\Config');
    $settings->expects($this->exactly(2))
      ->method('get')
      ->willReturnMap([
        ['acctCreation', 'ldap_behavior'],
        ['drupalAcctProvisionServer', ''],
      ]);

    $config_factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $config_factory->expects($this->once())
      ->method('getEditable')
      ->with('ldap_user.settings')
      ->willReturn($settings);
    $this->container->set('config.factory', $config_factory);

    ldap_user_update_8408();
  }

  /**
   * Test ldap_user_update_8408().
   *
   * @coversFunction ::ldap_user_update_8408
   */
  public function testHookUpdate8408UpdateUserAttr(): void {
    $settings = $this->createMock('Drupal\Core\Config\Config');
    $settings->expects($this->exactly(5))
      ->method('get')
      ->willReturnMap([
        ['acctCreation', 'ldap_behavior'],
        ['drupalAcctProvisionServer', 'test_server'],
        ['ldapUserSyncMappings.drupal.field-mail', [
          'ldap_attr' => 'mail',
        ],
        ],
        ['ldapUserSyncMappings.drupal', [
          'field-mail' => 'mail',
        ],
        ],
        ['ldapUserSyncMappings.drupal.field-name', [
          'ldap_attr' => 'sAMAccountName',
        ],
        ],
      ]);

    $config_factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $config_factory->expects($this->once())
      ->method('getEditable')
      ->with('ldap_user.settings')
      ->willReturn($settings);
    $this->container->set('config.factory', $config_factory);

    $ldap_server = $this->createMock('Drupal\ldap_servers\Entity\Server');
    $ldap_server->expects($this->once())
      ->method('save');

    $server_storage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
    $server_storage->expects($this->once())
      ->method('load')
      ->with('test_server')
      ->willReturn($ldap_server);

    $entity_type_manager = $this->createMock('Drupal\Core\Entity\EntityTypeManagerInterface');
    $entity_type_manager->expects($this->once())
      ->method('getStorage')
      ->with('ldap_server')
      ->willReturn($server_storage);
    $this->container->set('entity_type.manager', $entity_type_manager);

    ldap_user_update_8408();
  }

  /**
   * Test ldap_user_update_8408().
   *
   * @coversFunction ::ldap_user_update_8408
   */
  public function testHookUpdate8408UpdateAccountNameAttr(): void {
    $settings = $this->createMock('Drupal\Core\Config\Config');
    $settings->expects($this->exactly(5))
      ->method('get')
      ->willReturnMap([
        ['acctCreation', 'ldap_behavior'],
        ['drupalAcctProvisionServer', 'test_server'],
        ['ldapUserSyncMappings.drupal.field-mail', [
          'ldap_attr' => 'mail',
        ],
        ],
        ['ldapUserSyncMappings.drupal', [
          'field-mail' => 'mail',
        ],
        ],
        ['ldapUserSyncMappings.drupal.field-name', [
          'ldap_attr' => 'sAMAccountName',
        ],
        ],
      ]);

    $config_factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $config_factory->expects($this->once())
      ->method('getEditable')
      ->with('ldap_user.settings')
      ->willReturn($settings);
    $this->container->set('config.factory', $config_factory);

    $ldap_server = $this->createMock('Drupal\ldap_servers\Entity\Server');
    $ldap_server->expects($this->once())
      ->method('save');
    $ldap_server->expects($this->exactly(3))
      ->method('get')
      ->willReturnMap([
        ['mail_attr', 'mail'],
        ['user_attr', 'name'],
      ]);

    $server_storage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
    $server_storage->expects($this->once())
      ->method('load')
      ->with('test_server')
      ->willReturn($ldap_server);

    $entity_type_manager = $this->createMock('Drupal\Core\Entity\EntityTypeManagerInterface');
    $entity_type_manager->expects($this->once())
      ->method('getStorage')
      ->with('ldap_server')
      ->willReturn($server_storage);
    $this->container->set('entity_type.manager', $entity_type_manager);

    ldap_user_update_8408();
  }

}
