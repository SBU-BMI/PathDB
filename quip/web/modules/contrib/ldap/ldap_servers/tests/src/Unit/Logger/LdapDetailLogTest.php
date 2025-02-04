<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_servers\Unit\Logger;

use Drupal\ldap_servers\Logger\LdapDetailLog;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests LdapDetailLog class.
 *
 * @group ldap_servers
 */
class LdapDetailLogTest extends UnitTestCase {

  /**
   * The logger factory.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $loggerFactory;

  /**
   * The config factory.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $this->loggerFactory = $this->prophesize('Drupal\Core\Logger\LoggerChannelFactoryInterface');
    $container->set('logger.factory', $this->loggerFactory->reveal());

    $this->configFactory = $this->prophesize('Drupal\Core\Config\ConfigFactoryInterface');
    $container->set('config.factory', $this->configFactory->reveal());

    \Drupal::setContainer($container);
  }

  /**
   * Test log.
   */
  public function testLogMessage(): void {

    $logger = $this->prophesize('Psr\Log\LoggerInterface');
    $logger->debug('test message', [])->shouldBeCalled();
    $this->loggerFactory->get('ldap_servers')->willReturn($logger->reveal());
    $config = $this->prophesize('Drupal\Core\Config\Config');
    $config->get('watchdog_detail')->willReturn(TRUE);
    $this->configFactory->get('ldap_servers.settings')->willReturn($config->reveal());

    $logger = new LdapDetailLog($this->loggerFactory->reveal(), $this->configFactory->reveal());
    $context = [];
    $logger->log('test message', $context);
  }

}
