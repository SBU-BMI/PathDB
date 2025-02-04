<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_servers\Unit\Controller;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\ldap_servers\Controller\DebugController;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the DebugController class.
 *
 * @group ldap_servers
 */
class DebugControllerTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The debug controller.
   *
   * @var \Drupal\ldap_servers\Controller\DebugController
   */
  protected $debugController;

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerBuilder
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

    $this->config = $this->prophesize(ConfigFactoryInterface::class);
    $this->container->set('config.factory', $this->config->reveal());

    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->container->set('module_handler', $this->moduleHandler);

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->container->set('entity_type.manager', $this->entityTypeManager);

    $messenger = $this->createMock('Drupal\Core\Messenger\MessengerInterface');
    $this->container->set('messenger', $messenger);

    \Drupal::setContainer($this->container);

    $this->debugController = DebugController::create($this->container);

    $user_settings = $this->prophesize(Config::class);
    $user_settings->get('register')
      ->willReturn(UserInterface::REGISTER_VISITORS);
    $ldap_servers_settings = $this->prophesize(Config::class);
    $this->config->get('ldap_servers.settings')
      ->willReturn($ldap_servers_settings->reveal());
    $this->config->get('user.settings')
      ->willReturn($user_settings->reveal());
  }

  /**
   * Test the report method.
   */
  public function testJustServerInstalled(): void {
    $server_config = $this->prophesize(Config::class);
    $server_config->get('binddn')
      ->willReturn('cn=admin,dc=example,dc=com');
    $server_config->get('bindpw')
      ->willReturn('password');
    $server_config->set('binddn', '***')
      ->shouldBeCalled($this->once());
    $server_config->set('bindpw', '***')
      ->shouldBeCalled($this->once());
    $server_config->getRawData()
      ->willReturn(['binddn' => 'cn=admin,dc=example,dc=com', 'bindpw' => 'password']);

    $this->config->getEditable('ldap_servers.server.test')
      ->willReturn($server_config->reveal());
    $server = $this->prophesize('Drupal\ldap_servers\ServerInterface');
    $server->label()
      ->willReturn('Test Server');

    $server_storage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
    $server_storage->expects($this->once())
      ->method('loadMultiple')
      ->willReturn(['test' => $server->reveal()]);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('ldap_server')
      ->willReturn($server_storage);

    $this->moduleHandler->expects($this->exactly(3))
      ->method('moduleExists')
      ->willReturn(FALSE);

    $output = $this->debugController->report();
    $this->assertNotEmpty($output);
    $this->assertIsArray($output);
    $this->assertCount(7, $output);
    $this->assertEquals('LDAP Debug Report', $output['#title']);

  }

  /**
   * Test the report method.
   */
  public function testAllInstalled(): void {
    $server_config = $this->prophesize(Config::class);
    $this->config->getEditable('ldap_servers.server.test')
      ->willReturn($server_config->reveal());
    $server = $this->prophesize('Drupal\ldap_servers\ServerInterface');
    $server->label()
      ->willReturn('Test Server');

    $server_storage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
    $server_storage->expects($this->once())
      ->method('loadMultiple')
      ->willReturn(['test' => $server->reveal()]);

    $this->moduleHandler->expects($this->exactly(3))
      ->method('moduleExists')
      ->willReturn(TRUE);

    $ldap_user_settings = $this->prophesize(Config::class);
    $this->config->get('ldap_user.settings')
      ->willReturn($ldap_user_settings->reveal())
      ->shouldBeCalled($this->once());

    $profile_config = $this->prophesize(Config::class);
    $this->config->get('authorization.authorization_profile.test_profile')
      ->willReturn($profile_config->reveal())
      ->shouldBeCalled($this->once());

    $profile = $this->createMock('Drupal\authorization\Entity\AuthorizationProfile');

    $entity_query = $this->createMock('Drupal\Core\Entity\Query\QueryInterface');
    $entity_query->expects($this->once())
      ->method('accessCheck')
      ->with(FALSE)
      ->willReturnSelf();
    $entity_query->expects($this->once())
      ->method('execute')
      ->willReturn(['test_profile']);

    $profile_storage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
    $profile_storage->expects($this->once())
      ->method('getQuery')
      ->willReturn($entity_query);

    $ldap_query_config = $this->prophesize(Config::class);
    $this->config->get('ldap_query.query.test_query')
      ->willReturn($ldap_query_config->reveal())
      ->shouldBeCalled($this->once());
    $ldap_query_query = $this->createMock('Drupal\Core\Entity\Query\QueryInterface');
    $ldap_query_query->expects($this->once())
      ->method('accessCheck')
      ->with(FALSE)
      ->willReturnSelf();
    $ldap_query_query->expects($this->once())
      ->method('execute')
      ->willReturn(['test_query']);
    $ldap_query_storage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
    $ldap_query_storage->expects($this->once())
      ->method('getQuery')
      ->willReturn($ldap_query_query);

    $this->entityTypeManager->expects($this->exactly(3))
      ->method('getStorage')
      ->willReturnOnConsecutiveCalls($server_storage, $profile_storage, $ldap_query_storage);

    $output = $this->debugController->report();
    $this->assertNotEmpty($output);
    $this->assertIsArray($output);
    $this->assertCount(7, $output);
    $this->assertEquals('LDAP Debug Report', $output['#title']);

  }

}
