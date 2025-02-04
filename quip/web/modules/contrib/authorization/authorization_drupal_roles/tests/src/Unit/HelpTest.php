<?php

declare(strict_types=1);

namespace Drupal\Tests\authorization_drupal_roles\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

require_once __DIR__ . '/../../../authorization_drupal_roles.module';

/**
 * Tests hook_help().
 *
 * @group authorization_drupal_roles
 */
class HelpTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->container = new ContainerBuilder();
    $string_translation = $this->getStringTranslationStub();
    $this->container->set('string_translation', $string_translation);
    \Drupal::setContainer($this->container);
  }

  /**
   * Test help.
   */
  public function testHelp() {
    $route_match = $this->prophesize(RouteMatchInterface::class);
    $help = authorization_drupal_roles_help('help.page.authorization_drupal_roles', $route_match->reveal());

    $this->assertNotEmpty($help);
  }

}
