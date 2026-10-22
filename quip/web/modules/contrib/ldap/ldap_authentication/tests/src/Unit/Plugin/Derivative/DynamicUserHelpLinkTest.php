<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_authentication\Unit\Plugin\Derivative;

use Drupal\ldap_authentication\Plugin\Derivative\DynamicUserHelpLink;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Dynamic user help link test.
 */
#[Group('ldap_authentication')]
#[CoversClass(DynamicUserHelpLink::class)]
class DynamicUserHelpLinkTest extends UnitTestCase {

  /**
   * Derivative.
   *
   * @var \Drupal\ldap_authentication\Plugin\Derivative\DynamicUserHelpLink
   */
  protected $derivative;

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $this->configFactory = $this->createMock('Drupal\Core\Config\ConfigFactory');
    $container->set('config.factory', $this->configFactory);

    $this->config = $this->createMock('Drupal\Core\Config\ImmutableConfig');

    \Drupal::setContainer($container);
  }

  /**
   * Test getDerivativeDefinitions.
   */
  public function testGetDerivativeDefinitions(): void {
    $this->configFactory->expects($this->once())
      ->method('get')
      ->with('ldap_authentication.settings')
      ->willReturn($this->config);

    $this->derivative = DynamicUserHelpLink::create(\Drupal::getContainer(), 'dynamic_user_help_link');
    $this->config->expects($this->exactly(3))
      ->method('get')
      ->willReturnMap([
        ['ldapUserHelpLinkText', 'Some text'],
        ['ldapUserHelpLinkUrl', 'http://example.com'],
      ]);

    $result = $this->derivative->getDerivativeDefinitions([
      'ldap_authentication' => 'ldap_authentication',
    ]);

    $this->assertIsArray($result);
    $this->assertCount(1, $result);
  }

  /**
   * Test create.
   */
  public function testCreate(): void {
    $this->configFactory->expects($this->once())
      ->method('get')
      ->with('ldap_authentication.settings')
      ->willReturn($this->config);

    $derivative = DynamicUserHelpLink::create(\Drupal::getContainer(), 'dynamic_user_help_link');
    $this->assertInstanceOf(DynamicUserHelpLink::class, $derivative);
  }

}
