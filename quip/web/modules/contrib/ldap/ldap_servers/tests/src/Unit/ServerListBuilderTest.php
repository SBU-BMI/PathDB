<?php

declare(strict_types=1);

namespace Drupal\ldap_servers\Tests\Unit;

use Drupal\ldap_servers\ServerListBuilder;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests Server ListBuilder.
 *
 * @group ldap_servers
 */
class ServerListBuilderTest extends UnitTestCase {

  /**
   * The server list builder.
   *
   * @var \Drupal\ldap_servers\ServerListBuilder
   */
  protected $serverListBuilder;

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * The server storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $serverStorage;

  /**
   * The LDAP Bridge.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $ldapBridge;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container = new ContainerBuilder();

    $string_translation = $this->getStringTranslationStub();
    $this->container->set('string_translation', $string_translation);

    $entity_type_manager = $this->prophesize('Drupal\Core\Entity\EntityTypeManagerInterface');
    $this->container->set('entity_type.manager', $entity_type_manager->reveal());

    $this->serverStorage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
    $entity_type_manager->getStorage('ldap_server')
      ->willReturn($this->serverStorage)
      ->shouldBeCalled($this->once());

    $this->ldapBridge = $this->prophesize('Drupal\ldap_servers\LdapBridgeInterface');
    $this->container->set('ldap.bridge', $this->ldapBridge->reveal());

    \Drupal::setContainer($this->container);

    $entity_type = $this->createMock('Drupal\Core\Entity\EntityTypeInterface');
    $entity_type->method('id')
      ->willReturn('ldap_server');
    $this->serverListBuilder = ServerListBuilder::createInstance($this->container, $entity_type);

  }

  /**
   * Tests the buildHeader method.
   */
  public function testBuildHeader(): void {
    $header = $this->serverListBuilder->buildHeader();
    $this->assertIsArray($header);
    $this->assertCount(8, $header);
    $this->assertEquals('Name', (string) $header['label']);
    $this->assertEquals('Operations', (string) $header['operations']);
    $this->assertEquals('Method', (string) $header['bind_method']);
    $this->assertEquals('Account', (string) $header['binddn']);
    $this->assertEquals('Enabled', (string) $header['status']);
    $this->assertEquals('Server address', (string) $header['address']);
    $this->assertEquals('Server port', (string) $header['port']);
    $this->assertEquals('Server reachable', (string) $header['current_status']);
  }

  /**
   * Tests the buildRow method.
   */
  public function testBuildRow(): void {
    $server = $this->createMock('Drupal\ldap_servers\Entity\Server');
    $server->expects($this->exactly(3))
      ->method('id')
      ->willReturn('example');
    $server->expects($this->once(1))
      ->method('label')
      ->willReturn('Example Server');

    $this->serverStorage->expects($this->once())
      ->method('load')
      ->with('example')
      ->willReturn($server);

    $row = $this->serverListBuilder->buildRow($server);
    $this->assertIsArray($row);
  }

  /**
   * Tests the buildRow method. Service account bind.
   */
  public function testBuildRowServiceAccount(): void {
    $server_with_overrides = $this->createMock('Drupal\ldap_servers\Entity\Server');
    $server_with_overrides->expects($this->once())
      ->method('id')
      ->willReturn('example');
    $server = $this->createMock('Drupal\ldap_servers\Entity\Server');
    $server->expects($this->exactly(2))
      ->method('id')
      ->willReturn('example');
    $server->expects($this->once())
      ->method('label')
      ->willReturn('Example Server');
    $translated_markup = $this->createMock('Drupal\Core\StringTranslation\TranslatableMarkup');
    $translated_markup->expects($this->once())
      ->method('__toString')
      ->willReturn('service account bind');
    $server->expects($this->once())
      ->method('getFormattedBind')
      ->willReturn($translated_markup);
    $server->expects($this->exactly(12))
      ->method('get')
      ->willReturn(
        'service_account',
        'cn=admin,dc=hogwarts,dc=edu',
        TRUE,
        'localhost',
        9389,
        TRUE,
        'service_account',
        "ou=people,dc=hogwarts,dc=edu\r\nou=groups,dc=hogwarts,dc=edu",
        FALSE,
        'localhost',
        9389,
        FALSE,
        FALSE
      );

    $this->serverStorage->expects($this->once())
      ->method('load')
      ->with('example')
      ->willReturn($server);

    $this->ldapBridge->bind()
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());

    $this->ldapBridge->setServer($server)
      ->shouldBeCalled($this->once());

    $row = $this->serverListBuilder->buildRow($server_with_overrides);
    $this->assertIsArray($row);
    $this->assertCount(8, $row);
    $this->assertEquals('Example Server', (string) $row['label']);
    $this->assertCount(1, $row['operations']);
    $this->assertEquals('Service account bind (overridden)', (string) $row['bind_method']);
    $this->assertEquals('cn=admin,dc=hogwarts,dc=edu (overridden)', (string) $row['binddn']);
    $this->assertEquals('Yes (overridden)', (string) $row['status']);
    $this->assertEquals('localhost (overridden)', (string) $row['address']);
    $this->assertEquals('9389 (overridden)', (string) $row['port']);
    $this->assertEquals('Server available', (string) $row['current_status']);
  }

  /**
   * Tests the buildRow method. Service account bind failure.
   */
  public function testBuildRowServiceAccountBindFailure(): void {
    $server_with_overrides = $this->createMock('Drupal\ldap_servers\Entity\Server');
    $server_with_overrides->expects($this->once())
      ->method('id')
      ->willReturn('example');
    $server = $this->createMock('Drupal\ldap_servers\Entity\Server');
    $server->expects($this->exactly(2))
      ->method('id')
      ->willReturn('example');
    $server->expects($this->once())
      ->method('label')
      ->willReturn('Example Server');
    $translated_markup = $this->createMock('Drupal\Core\StringTranslation\TranslatableMarkup');
    $translated_markup->expects($this->once())
      ->method('__toString')
      ->willReturn('service account bind');
    $server->expects($this->once())
      ->method('getFormattedBind')
      ->willReturn($translated_markup);
    $server->expects($this->exactly(12))
      ->method('get')
      ->willReturn(
        'service_account',
        'cn=admin,dc=hogwarts,dc=edu',
        TRUE,
        'localhost',
        9389,
        TRUE,
        'service_account',
        "ou=people,dc=hogwarts,dc=edu\r\nou=groups,dc=hogwarts,dc=edu",
        FALSE,
        'localhost',
        9389,
        FALSE,
        FALSE
      );

    $this->serverStorage->expects($this->once())
      ->method('load')
      ->with('example')
      ->willReturn($server);

    $this->ldapBridge->bind()
      ->willReturn(FALSE)
      ->shouldBeCalled($this->once());

    $this->ldapBridge->setServer($server)
      ->shouldBeCalled($this->once());

    $row = $this->serverListBuilder->buildRow($server_with_overrides);
    $this->assertIsArray($row);
    $this->assertCount(8, $row);
    $this->assertEquals('Example Server', (string) $row['label']);
    $this->assertCount(1, $row['operations']);
    $this->assertEquals('Service account bind (overridden)', (string) $row['bind_method']);
    $this->assertEquals('cn=admin,dc=hogwarts,dc=edu (overridden)', (string) $row['binddn']);
    $this->assertEquals('Yes (overridden)', (string) $row['status']);
    $this->assertEquals('localhost (overridden)', (string) $row['address']);
    $this->assertEquals('9389 (overridden)', (string) $row['port']);
    $this->assertEquals('Binding issues, please see log.', (string) $row['current_status']);
  }

  /**
   * Tests getOperations.
   */
  public function testGetOperations() {
    $server = $this->createMock('Drupal\ldap_servers\ServerInterface');
    $server->expects($this->exactly(2))
      ->method('id')
      ->willReturn('example');
    $server->expects($this->once())
      ->method('get')
      ->with('status')
      ->willReturn(TRUE);

    $operations = $this->serverListBuilder->getOperations($server);
    $this->assertIsArray($operations);
    $this->assertCount(2, $operations);
    $this->assertArrayHasKey('test', $operations);
    $this->assertArrayHasKey('disable', $operations);
  }

}
