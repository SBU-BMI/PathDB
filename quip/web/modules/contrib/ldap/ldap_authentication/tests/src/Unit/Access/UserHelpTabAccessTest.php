<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_authentication\Unit\Access;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ldap_authentication\Access\UserHelpTabAccess;
use Drupal\Tests\UnitTestCase;

/**
 * User help tab access test.
 *
 * @group ldap_authentication
 * @coversDefaultClass \Drupal\ldap_authentication\Access\UserHelpTabAccess
 */
class UserHelpTabAccessTest extends UnitTestCase {

  /**
   * Config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $config;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * Access.
   *
   * @var \Drupal\ldap_authentication\Access\UserHelpTabAccess
   */
  protected $access;

  /**
   * User.
   *
   * @var \Drupal\Core\Session\AccountInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $user;

  /**
   * External auth.
   *
   * @var \Drupal\externalauth\Authmap|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $externalAuth;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $this->configFactory = $this->createMock(ConfigFactory::class);
    $container->set('config.factory', $this->configFactory);

    $this->config = $this->createMock(ImmutableConfig::class);

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $container->set('entity_type.manager', $this->entityTypeManager);

    $this->user = $this->createMock('Drupal\Core\Session\AccountInterface');
    $container->set('current_user', $this->user);

    $this->externalAuth = $this->createMock('Drupal\externalauth\Authmap');
    $container->set('external_auth.auth_map', $this->externalAuth);

    \Drupal::setContainer($container);

    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('ldap_authentication.settings')
      ->willReturn($this->config);

    $this->access = new UserHelpTabAccess($this->configFactory, $this->user, $this->externalAuth);
  }

  /**
   * Test access.
   *
   * @covers ::access
   * @covers ::accessLdapHelpTab
   */
  public function testAccessTrue(): void {

    $this->config->expects($this->once())
      ->method('get')
      ->with('authenticationMode')
      ->willReturn('mixed');

    $this->user->expects($this->once())
      ->method('id')
      ->willReturn(2);

    $this->externalAuth->expects($this->once())
      ->method('get')
      ->with(2, 'ldap_user')
      ->willReturn('hpotter');

    $result = $this->access->access($this->user);
    $this->assertTrue($result->isAllowed());
  }

  /**
   * Test access.
   *
   * @covers ::access
   * @covers ::accessLdapHelpTab
   */
  public function testAccessFalse(): void {

    $this->config->expects($this->once())
      ->method('get')
      ->with('authenticationMode')
      ->willReturn('exclusive');

    $result = $this->access->access($this->user);
    $this->assertTrue($result->isForbidden());
  }

  /**
   * Test access.
   *
   * @covers ::access
   * @covers ::accessLdapHelpTab
   */
  public function testAccessAnonymous(): void {

    $this->config->expects($this->once())
      ->method('get')
      ->with('authenticationMode')
      ->willReturn('exclusive');

    $this->user->expects($this->once())
      ->method('isAnonymous')
      ->willReturn(TRUE);

    $result = $this->access->access($this->user);
    $this->assertTrue($result->isAllowed());
  }

  /**
   * Test constructor.
   *
   * @covers ::__construct
   */
  public function testConstructor(): void {
    $access = new UserHelpTabAccess($this->configFactory, $this->user, $this->externalAuth);
  }

}
