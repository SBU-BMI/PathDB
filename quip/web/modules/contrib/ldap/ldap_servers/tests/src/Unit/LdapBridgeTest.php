<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_servers\Unit;

use Drupal\ldap_servers\LdapBridge;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests LdapBridge.
 *
 * @group ldap_servers
 */
class LdapBridgeTest extends UnitTestCase {

  /**
   * The ldap bridge.
   *
   * @var \Drupal\ldap_servers\LdapBridge
   */
  protected $ldapBridge;

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The entity storage.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $entityStorage;

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
    $this->entityStorage = $this->prophesize('Drupal\Core\Entity\EntityStorageInterface');
    $entity_type_manager->getStorage('ldap_server')->willReturn($this->entityStorage->reveal());

    $this->logger = $this->createMock('Psr\Log\LoggerInterface');

    $this->ldapBridge = new LdapBridge(
      $this->logger,
      $entity_type_manager->reveal()
    );
  }

  /**
   * Test the getLdapBridge method.
   */
  public function testSetServerById(): void {
    $server_id = 'example_server';
    $server = $this->prophesize('Drupal\ldap_servers\Entity\Server');
    $server->get('address')
      ->willReturn('example.com')
      ->shouldBeCalled($this->once());
    $server->get('port')
      ->willReturn(389)
      ->shouldBeCalled($this->once());
    $server->get('encryption')
      ->willReturn('none')
      ->shouldBeCalled($this->once());
    $server->get('bind_method')
      ->willReturn('service_account')
      ->shouldBeCalled($this->once());
    $server->get('binddn')
      ->willReturn('cn=admin,dc=example,dc=com')
      ->shouldBeCalled($this->once());
    $server->get('bindpw')
      ->willReturn('password')
      ->shouldBeCalled($this->once());
    $server->getTimeout()
      ->willReturn(30)
      ->shouldBeCalled($this->exactly(2));

    $this->entityStorage
      ->load($server_id)
      ->willReturn($server->reveal())
      ->shouldBeCalled($this->once());

    $this->ldapBridge->setServerById($server_id);
  }

  /**
   * Test the setServer method.
   */
  public function testSetServer(): void {
    $server = $this->prophesize('Drupal\ldap_servers\Entity\Server');
    $server->get('address')
      ->willReturn('example.com')
      ->shouldBeCalled($this->once());
    $server->get('port')
      ->willReturn(389)
      ->shouldBeCalled($this->once());
    $server->get('encryption')
      ->willReturn('none')
      ->shouldBeCalled($this->once());
    $server->get('bind_method')
      ->willReturn('service_account')
      ->shouldBeCalled($this->once());
    $server->get('binddn')
      ->willReturn('cn=admin,dc=example,dc=com')
      ->shouldBeCalled($this->once());
    $server->get('bindpw')
      ->willReturn('password')
      ->shouldBeCalled($this->once());
    $server->getTimeout()
      ->willReturn(30)
      ->shouldBeCalled($this->exactly(2));

    $this->ldapBridge->setServer($server->reveal());
  }

  /**
   * Test the get method.
   */
  public function testGet(): void {
    $server = $this->prophesize('Drupal\ldap_servers\Entity\Server');
    $server->get('address')
      ->willReturn('example.com')
      ->shouldBeCalled($this->once());
    $server->get('port')
      ->willReturn(389)
      ->shouldBeCalled($this->once());
    $server->get('encryption')
      ->willReturn('none')
      ->shouldBeCalled($this->once());
    $server->get('bind_method')
      ->willReturn('service_account')
      ->shouldBeCalled($this->once());
    $server->get('binddn')
      ->willReturn('cn=admin,dc=example,dc=com')
      ->shouldBeCalled($this->once());
    $server->get('bindpw')
      ->willReturn('password')
      ->shouldBeCalled($this->once());
    $server->getTimeout()
      ->willReturn(30)
      ->shouldBeCalled($this->exactly(2));

    $this->ldapBridge->setServer($server->reveal());

    $ldap = $this->ldapBridge->get();
    $this->assertInstanceOf('Symfony\Component\Ldap\Ldap', $ldap);
  }

  /**
   * Test the bind method.
   */
  public function testBind(): void {
    $server = $this->prophesize('Drupal\ldap_servers\Entity\Server');
    $server->get('address')
      ->willReturn('example.com')
      ->shouldBeCalled($this->once());
    $server->get('port')
      ->willReturn(389)
      ->shouldBeCalled($this->once());
    $server->get('encryption')
      ->willReturn('none')
      ->shouldBeCalled($this->once());
    $server->get('bind_method')
      ->willReturn('service_account')
      ->shouldBeCalled($this->once());
    $server->get('binddn')
      ->willReturn('cn=admin,dc=example,dc=com')
      ->shouldBeCalled($this->once());
    $server->get('bindpw')
      ->willReturn('password')
      ->shouldBeCalled($this->once());
    $server->getTimeout()
      ->willReturn(1)
      ->shouldBeCalled($this->exactly(2));

    $this->ldapBridge->setServer($server->reveal());

    $this->ldapBridge->bind();
  }

  /**
   * Test the bind method with anon.
   */
  public function testBindAnon(): void {
    $server = $this->prophesize('Drupal\ldap_servers\Entity\Server');
    $server->get('address')
      ->willReturn('example.com')
      ->shouldBeCalled($this->once());
    $server->get('port')
      ->willReturn(389)
      ->shouldBeCalled($this->once());
    $server->get('encryption')
      ->willReturn('none')
      ->shouldBeCalled($this->once());
    $server->get('bind_method')
      ->willReturn('anon')
      ->shouldBeCalled($this->once());
    $server->get('binddn')
      ->willReturn('cn=admin,dc=example,dc=com')
      ->shouldBeCalled($this->once());
    $server->get('bindpw')
      ->willReturn('password')
      ->shouldBeCalled($this->once());
    $server->getTimeout()
      ->willReturn(1)
      ->shouldBeCalled($this->exactly(2));

    $this->ldapBridge->setServer($server->reveal());

    $this->ldapBridge->bind();
  }

  /**
   * Test the bind method with empty password.
   */
  public function testBindEmptyPassword(): void {
    $server = $this->prophesize('Drupal\ldap_servers\Entity\Server');
    $server->get('address')
      ->willReturn('example.com')
      ->shouldBeCalled($this->once());
    $server->get('port')
      ->willReturn(389)
      ->shouldBeCalled($this->once());
    $server->get('encryption')
      ->willReturn('none')
      ->shouldBeCalled($this->once());
    $server->get('bind_method')
      ->willReturn('service_account')
      ->shouldBeCalled($this->once());
    $server->get('binddn')
      ->willReturn('cn=admin,dc=example,dc=com')
      ->shouldBeCalled($this->once());
    $server->get('bindpw')
      ->willReturn('')
      ->shouldBeCalled($this->once());
    $server->getTimeout()
      ->willReturn(1)
      ->shouldBeCalled($this->exactly(2));

    $this->ldapBridge->setServer($server->reveal());

    $this->ldapBridge->bind();
  }

}
