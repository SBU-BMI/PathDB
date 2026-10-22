<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_authentication\Unit;

use Drupal\ldap_authentication\AuthenticationServers;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Authentication servers test.
 */
#[Group('ldap_authentication')]
#[CoversClass(AuthenticationServers::class)]
class AuthenticationServersTest extends UnitTestCase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

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

    $server_storage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
    $this->entityTypeManager = $this->createMock('Drupal\Core\Entity\EntityTypeManagerInterface');
    $this->entityTypeManager->method('getStorage')
      ->with('ldap_server')
      ->willReturn($server_storage);

    $this->config = $this->createMock('Drupal\Core\Config\ImmutableConfig');
    $config_factory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $config_factory->expects($this->once())
      ->method('get')
      ->with('ldap_authentication.settings')
      ->willReturn($this->config);

    $this->service = new AuthenticationServers($this->entityTypeManager, $config_factory);
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
    $this->entityTypeManager->getStorage('ldap_server')
      ->expects($this->once())
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
    $this->entityTypeManager->getStorage('ldap_server')
      ->expects($this->once())
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
