<?php

declare(strict_types=1);

namespace Drupal\Tests\authorization_drupal_roles\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\authorization_drupal_roles\Service\AuthorizationDrupalRolesService;
use Drupal\user\UserDataInterface;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Authorization Drupal roles service test.
 *
 * @group authorization_drupal_roles
 */
class AuthorizationDrupalRolesServiceTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * The service.
   *
   * @var \Drupal\authorization_drupal_roles\Service\AuthorizationDrupalRolesService
   */
  protected $service;

  /**
   * The user data.
   *
   * @var \Drupal\user\UserDataInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $userData;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->userData = $this->prophesize(UserDataInterface::class);

    $this->service = new AuthorizationDrupalRolesService($this->userData->reveal());
  }

  /**
   * Test get roles. Has roles.
   */
  public function testGetRoles() {
    $this->userData->get('authorization_drupal_roles', 1, 'roles')
      ->willReturn([
        'student' => 'test',
        'gryffindor' => 'test',
        'english' => 'test2',
      ])
      ->shouldBeCalled($this->once());
    $roles = $this->service->getRoles(1, 'test');

    $this->assertEquals($roles, ['student', 'gryffindor']);
  }

  /**
   * Test get roles. No roles.
   */
  public function testGetRolesNone() {
    $this->userData->get('authorization_drupal_roles', 1, 'roles')
      ->willReturn(NULL)
      ->shouldBeCalled($this->once());
    $roles = $this->service->getRoles(1, 'test');

    $this->assertEquals($roles, []);
  }

  /**
   * Test set roles. No existing roles.
   */
  public function testSetRoles() {
    $this->userData->get('authorization_drupal_roles', 1, 'roles')
      ->willReturn(NULL)
      ->shouldBeCalled($this->once());
    $this->userData->set('authorization_drupal_roles', 1, 'roles', [
      'student' => 'test',
      'gryffindor' => 'test',
    ])
      ->willReturn(NULL)
      ->shouldBeCalled($this->once());
    $this->service->setRoles(1, 'test', ['student', 'gryffindor']);
  }

  /**
   * Test set roles. Existing roles.
   */
  public function testSetRolesExistingRoles() {
    $this->userData->get('authorization_drupal_roles', 1, 'roles')
      ->willReturn([
        'english' => 'test2',
        'student' => 'test',
      ])
      ->shouldBeCalled($this->once());
    $this->userData->set('authorization_drupal_roles', 1, 'roles', [
      'english' => 'test2',
      'student' => 'test',
      'gryffindor' => 'test',
    ])
      ->willReturn(NULL)
      ->shouldBeCalled($this->once());
    $this->service->setRoles(1, 'test', ['student', 'gryffindor']);
  }

}
