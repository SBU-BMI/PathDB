<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_authorization\Unit;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

require_once __DIR__ . '/../../../ldap_authorization.module';

/**
 * Test the hook_help function.
 *
 * @package Drupal\Tests\ldap_authorization\Unit
 */
class HookHelpTest extends UnitTestCase {

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
   * @coversFunction ::ldap_authorization_help
   */
  public function testHookHelp(): void {
    $route_match = $this->createMock('Drupal\Core\Routing\RouteMatchInterface');

    $output = ldap_authorization_help('help.page.ldap_authorization', $route_match);
    $this->assertNotEmpty($output);
  }

}
