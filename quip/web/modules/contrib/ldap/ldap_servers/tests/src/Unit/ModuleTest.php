<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_servers\Unit;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

require_once __DIR__ . '/../../../ldap_servers.module';

/**
 * Test the hook_help function.
 *
 * @package Drupal\Tests\ldap_servers\Unit
 */
class ModuleTest extends UnitTestCase {

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
   * Test hook_help().
   *
   * @coversFunction ::ldap_servers_help
   */
  public function testHookHelpRouteHelpPage(): void {
    $route_match = $this->createMock('Drupal\Core\Routing\RouteMatchInterface');

    $output = ldap_servers_help('help.page.ldap_servers', $route_match);
    $this->assertNotEmpty($output);
  }

  /**
   * Test hook_help().
   *
   * @coversFunction ::ldap_servers_help
   */
  public function testHookHelpRouteServerCollection(): void {
    $route_match = $this->createMock('Drupal\Core\Routing\RouteMatchInterface');

    $output = ldap_servers_help('entity.ldap_server.collection', $route_match);
    $this->assertNotEmpty($output);
  }

  /**
   * Test hook_help().
   *
   * @coversFunction ::ldap_servers_help
   */
  public function testHookHelpRouteServerEditForm(): void {
    $server = $this->createMock('Drupal\ldap_servers\Entity\Server');
    $route_match = $this->createMock('Drupal\Core\Routing\RouteMatchInterface');
    $route_match->expects($this->any())
      ->method('getParameter')
      ->with('ldap_server')
      ->willReturn($server);
    $output = ldap_servers_help('entity.ldap_server.edit_form', $route_match);
    $this->assertNotEmpty($output);
  }

  /**
   * Test hook_help().
   *
   * @coversFunction ::ldap_servers_help
   */
  public function testHookHelpRouteNoHelpProvided(): void {
    $route_match = $this->createMock('Drupal\Core\Routing\RouteMatchInterface');

    $output = ldap_servers_help('entity.ldap_server.nothing', $route_match);
    $this->assertEmpty($output);
  }

  /**
   * Test hook_user_login().
   *
   * @coversFunction ::ldap_servers_user_login
   */
  public function testHookUserLogout(): void {
    $default_cache = $this->createMock('Drupal\Core\Cache\CacheBackendInterface');
    $default_cache->expects($this->once())
      ->method('get')
      ->with('ldap_servers:user_data:hpotter')
      ->willReturn(TRUE);
    $default_cache->expects($this->once())
      ->method('delete')
      ->with('ldap_servers:user_data:hpotter');
    $this->container->set('cache.default', $default_cache);

    $account = $this->createMock('Drupal\user\Entity\User');
    $account->expects($this->once())
      ->method('getAccountName')
      ->willReturn('hpotter');
    $output = ldap_servers_user_logout($account);
    $this->assertNull($output);
  }

}
