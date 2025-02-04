<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_authentication\Unit;

use Drupal\ldap_authentication\AuthenticationServers;
use Drupal\Tests\UnitTestCase;

/**
 * Authentication servers test.
 *
 * @group ldap_authentication
 * @coversDefaultClass \Drupal\ldap_authentication\AuthenticationServers
 */
class AuthenticationServersTest extends UnitTestCase {

  /**
   * Server storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $serverStorage;

  /**
   * Config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $config;

  /**
   * Service.
   *
   * @var \Drupal\ldap_authentication\AuthenticationServers
   */
  protected $service;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->serverStorage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
    $entity_type_manager = $this->createMock('Drupal\Core\Entity\EntityTypeManagerInterface');
    $entity_type_manager->expects($this->once())
      ->method('getStorage')
      ->with('ldap_server')
      ->willReturn($this->serverStorage);

    $this->config = $this->createMock('Drupal\Core\Config\ImmutableConfig');
    $config_factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $config_factory->expects($this->once())
      ->method('get')
      ->with('ldap_authentication.settings')
      ->willReturn($this->config);

    $this->service = new AuthenticationServers($entity_type_manager, $config_factory);
  }

  /**
   * Test authentication servers available.
   */
  public function testAuthenticationServersAvailableFalse(): void {
    $entity_query = $this->createMock('Drupal\Core\Entity\Query\QueryInterface');
    $entity_query->expects($this->once())
      ->method('accessCheck')
      ->with(FALSE)
      ->willReturnSelf();
    $entity_query->expects($this->once())
      ->method('condition')
      ->with('status', 1)
      ->willReturnSelf();
    $entity_query->expects($this->once())
      ->method('execute')
      ->willReturn([]);
    $this->serverStorage->expects($this->once())
      ->method('getQuery')
      ->willReturn($entity_query);

    $this->config->expects($this->once())
      ->method('get')
      ->with('sids')
      ->willReturn(['server1', 'server2']);

    $result = $this->service->authenticationServersAvailable();

    $this->assertFalse($result);
  }

  /**
   * Test authentication servers available.
   */
  public function testAuthenticationServersAvailableTrue(): void {
    $entity_query = $this->createMock('Drupal\Core\Entity\Query\QueryInterface');
    $entity_query->expects($this->once())
      ->method('accessCheck')
      ->with(FALSE)
      ->willReturnSelf();
    $entity_query->expects($this->once())
      ->method('condition')
      ->with('status', 1)
      ->willReturnSelf();
    $entity_query->expects($this->once())
      ->method('execute')
      ->willReturn(['server1' => 'server1']);
    $this->serverStorage->expects($this->once())
      ->method('getQuery')
      ->willReturn($entity_query);

    $this->config->expects($this->once())
      ->method('get')
      ->with('sids')
      ->willReturn(['server1', 'server2']);

    $result = $this->service->authenticationServersAvailable();

    $this->assertTrue($result);
  }

}
