<?php

declare(strict_types=1);

namespace Drupal\Tests\authorization_drupal_roles\Unit;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

require_once __DIR__ . '/../../../authorization_drupal_roles.install';

/**
 * Tests update 8004.
 *
 * Migrates roles from a field to user data service.
 *
 * @group authorization_drupal_roles
 */
class Update8004Test extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity storage.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $storage;

  /**
   * The definition update manager.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $definitionUpdateManager;

  /**
   * The datetime service.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $datetime;

  /**
   * The user data service.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $userData;

  /**
   * The logger.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->container = new ContainerBuilder();

    $string_translation = $this->getStringTranslationStub();
    $this->container->set('string_translation', $string_translation);

    $this->definitionUpdateManager = $this->prophesize(EntityDefinitionUpdateManagerInterface::class);
    $this->container->set('entity.definition_update_manager', $this->definitionUpdateManager->reveal());

    $this->database = $this->createMock(Connection::class);
    $this->container->set('database', $this->database);

    $this->datetime = $this->prophesize('Drupal\Component\Datetime\TimeInterface');
    $this->container->set('datetime.time', $this->datetime->reveal());

    $this->userData = $this->prophesize('Drupal\user\UserDataInterface');
    $this->container->set('user.data', $this->userData->reveal());

    $this->logger = $this->prophesize(LoggerChannelInterface::class);
    $logger_factory = $this->prophesize(LoggerChannelFactoryInterface::class);
    $logger_factory->get('authorization_drupal_roles')
      ->willReturn($this->logger->reveal());
    $this->container->set('logger.factory', $logger_factory->reveal());

    $this->storage = $this->prophesize('Drupal\Core\Entity\EntityStorageInterface');

    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->entityTypeManager
      ->getStorage('authorization_profile')
      ->willReturn($this->storage->reveal());
    $this->container->set('entity_type.manager', $this->entityTypeManager->reveal());

    \Drupal::setContainer($this->container);
  }

  /**
   * Tests update 8004, with no profiles.
   */
  public function testUpdateNoProfiles() {

    $this->storage
      ->loadMultiple()
      ->willReturn([])
      ->shouldBeCalled($this->once());

    $definition = $this->prophesize('Drupal\Core\Field\FieldStorageDefinitionInterface');
    $this->definitionUpdateManager
      ->getFieldStorageDefinition('authorization_drupal_roles_roles', 'user')
      ->willReturn($definition->reveal())
      ->shouldBeCalled($this->once());
    $this->definitionUpdateManager
      ->uninstallFieldStorageDefinition($definition->reveal())
      ->shouldBeCalled($this->once());

    $this->logger
      ->notice('No profiles have revoke_provider_provisioned enabled. No roles to move to user data.')
      ->shouldBeCalled($this->once());

    $sandbox = [];
    authorization_drupal_roles_update_8004($sandbox);

    $this->assertEquals(1, $sandbox['#finished']);
    $this->assertArrayHasKey('profiles', $sandbox);
    $this->assertEmpty($sandbox['profiles']);
  }

  /**
   * Tests update 8004, with no storage definition.
   */
  public function testUpdateNoStorageDefinition() {

    $this->definitionUpdateManager
      ->getFieldStorageDefinition('authorization_drupal_roles_roles', 'user')
      ->willReturn(NULL)
      ->shouldBeCalled($this->once());

    $this->logger
      ->error('"authorization_drupal_roles_roles" field is not present. Unable to move roles to user data.')
      ->shouldBeCalled($this->once());

    $sandbox = [];
    authorization_drupal_roles_update_8004($sandbox);

    $this->assertEquals(1, $sandbox['#finished']);
    $this->assertArrayNotHasKey('profiles', $sandbox);
  }

  /**
   * Tests update 8004, with two profiles and no users.
   */
  public function testTwoProfilesAndNoUsers() {
    $profile1 = $this->prophesize('Drupal\authorization\AuthorizationProfileInterface');
    $profile1->id()->willReturn('profile1');
    $profile1->get('synchronization_actions')->willReturn([
      'revoke_provider_provisioned' => TRUE,
    ]);
    $profile1->getConsumerMappings()->willReturn([
      ['role' => 'role1'],
      ['role' => 'none'],
      ['role' => 'role2'],
    ]);
    $profile2 = $this->prophesize('Drupal\authorization\AuthorizationProfileInterface');
    $profile2->id()->willReturn('profile2');
    $profile2->get('synchronization_actions')->willReturn([
      'revoke_provider_provisioned' => FALSE,
    ]);
    $profile2->getConsumerMappings()->willReturn([
      ['role' => 'role3'],
      ['role' => 'none'],
      ['role' => 'role4'],
    ]);
    $this->storage
      ->loadMultiple()
      ->willReturn([$profile1->reveal(), $profile2->reveal()])
      ->shouldBeCalled($this->once());

    $definition = $this->prophesize('Drupal\Core\Field\FieldStorageDefinitionInterface');
    $this->definitionUpdateManager
      ->getFieldStorageDefinition('authorization_drupal_roles_roles', 'user')
      ->willReturn($definition->reveal())
      ->shouldBeCalled($this->once());
    $this->definitionUpdateManager
      ->uninstallFieldStorageDefinition($definition->reveal())
      ->shouldBeCalled($this->once());

    $statement = $this->createMock('Drupal\Core\Database\StatementInterface');
    $statement->expects($this->once())
      ->method('fetchCol')
      ->willReturn([]);

    $select = $this->createMock('Drupal\Core\Database\Query\SelectInterface');
    $select->expects($this->once())
      ->method('fields')
      ->with('ur', ['entity_id'])
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('distinct')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('execute')
      ->willReturn($statement);

    $this->database->expects($this->once())
      ->method('select')
      ->with('user__authorization_drupal_roles_roles', 'ur')
      ->willReturn($select);

    $sandbox = [];
    authorization_drupal_roles_update_8004($sandbox);

    $this->assertEquals(1, $sandbox['#finished']);
    $this->assertArrayHasKey('profiles', $sandbox);
    $this->assertCount(1, $sandbox['profiles']);
    $this->assertArrayHasKey('profile1', $sandbox['profiles']);
  }

  /**
   * Tests update 8004, with two profiles and three users.
   */
  public function testTwoProfilesAndThreeUser() {
    $profile1 = $this->prophesize('Drupal\authorization\AuthorizationProfileInterface');
    $profile1->id()->willReturn('profile1');
    $profile1->get('synchronization_actions')->willReturn([
      'revoke_provider_provisioned' => TRUE,
    ]);
    $profile1->getConsumerMappings()->willReturn([
      ['role' => 'role1'],
      ['role' => 'none'],
      ['role' => 'role2'],
    ]);
    $profile2 = $this->prophesize('Drupal\authorization\AuthorizationProfileInterface');
    $profile2->id()->willReturn('profile2');
    $profile2->get('synchronization_actions')->willReturn([
      'revoke_provider_provisioned' => TRUE,
    ]);
    $profile2->getConsumerMappings()->willReturn([
      ['role' => 'none'],
      ['role' => 'none'],
      ['role' => 'none'],
    ]);
    $this->storage
      ->loadMultiple()
      ->willReturn([$profile1->reveal(), $profile2->reveal()])
      ->shouldBeCalled($this->once());

    $definition = $this->prophesize('Drupal\Core\Field\FieldStorageDefinitionInterface');
    $this->definitionUpdateManager
      ->getFieldStorageDefinition('authorization_drupal_roles_roles', 'user')
      ->willReturn($definition->reveal())
      ->shouldBeCalled($this->once());

    $statement = $this->createMock('Drupal\Core\Database\StatementInterface');
    $statement->expects($this->once())
      ->method('fetchCol')
      ->willReturn([1, 2, 3]);

    $select = $this->createMock('Drupal\Core\Database\Query\SelectInterface');
    $select->expects($this->once())
      ->method('fields')
      ->with('ur', ['entity_id'])
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('distinct')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('execute')
      ->willReturn($statement);

    $this->database->expects($this->once())
      ->method('select')
      ->with('user__authorization_drupal_roles_roles', 'ur')
      ->willReturn($select);

    $sandbox = [];
    authorization_drupal_roles_update_8004($sandbox);

    $this->assertEquals(0, $sandbox['#finished']);
    $this->assertArrayHasKey('profiles', $sandbox);
    $this->assertCount(1, $sandbox['profiles']);
    $this->assertArrayHasKey('profile1', $sandbox['profiles']);
    $this->assertArrayHasKey('total', $sandbox);
    $this->assertEquals(3, $sandbox['total']);
    $this->assertArrayHasKey('user_ids', $sandbox);
    $this->assertEquals([1, 2, 3], $sandbox['user_ids']);
  }

  /**
   * Tests update 8004, with two profiles and three users. 2nd iteration.
   */
  public function testTwoProfilesAndThreeUserAllIterations() {
    $sandbox = [
      'profiles' => [
        'profile_a' => ['role1'],
        'profile_b' => ['role2'],
      ],
      'current' => 0,
      'total' => 3,
      'user_ids' => [1, 2, 3],
    ];

    $user1 = $this->prophesize('Drupal\user\UserInterface');
    $user1->get('authorization_drupal_roles_roles')->willReturn(['role1', 'role2']);
    $user1->hasRole('role1')
      ->willReturn(FALSE)
      ->shouldBeCalled($this->once());
    $user1->hasRole('role2')
      ->willReturn(FALSE)
      ->shouldBeCalled($this->once());
    $user2 = $this->prophesize('Drupal\user\UserInterface');
    $user2->get('authorization_drupal_roles_roles')->willReturn(['role1', 'role2']);
    $user2->hasRole('role1')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());

    $user3 = $this->prophesize('Drupal\user\UserInterface');
    $user3->get('authorization_drupal_roles_roles')->willReturn(['role1', 'role2']);
    $user3->hasRole('role1')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());

    $this->userData->set('authorization_drupal_roles', 3, 'roles', [
      'role1' => 'profile_a',
    ])
      ->shouldBeCalled($this->once());

    $this->userData->set('authorization_drupal_roles', 2, 'roles', [
      'role1' => 'profile_a',
    ])
      ->shouldBeCalled($this->once());

    $user_storage = $this->prophesize('Drupal\Core\Entity\EntityStorageInterface');
    $user_storage
      ->load(1)
      ->willReturn($user1->reveal())
      ->shouldBeCalled($this->once());
    $user_storage
      ->load(2)
      ->willReturn($user2->reveal())
      ->shouldBeCalled($this->once());
    $user_storage
      ->load(3)
      ->willReturn($user3->reveal())
      ->shouldBeCalled($this->once());
    $this->entityTypeManager
      ->getStorage('user')
      ->willReturn($user_storage->reveal());

    $this->datetime->getRequestTime()->willReturn(100);
    $this->datetime->getCurrentTime()->willReturn(110, 120, 200);

    $definition = $this->prophesize('Drupal\Core\Field\FieldStorageDefinitionInterface');
    $this->definitionUpdateManager
      ->getFieldStorageDefinition('authorization_drupal_roles_roles', 'user')
      ->willReturn($definition->reveal())
      ->shouldBeCalled($this->once());
    $this->definitionUpdateManager
      ->uninstallFieldStorageDefinition($definition->reveal())
      ->shouldBeCalled($this->once());

    $statement = $this->createMock('Drupal\Core\Database\StatementInterface');
    $statement->expects($this->exactly(3))
      ->method('fetchCol')
      ->willReturnOnConsecutiveCalls(['role1'], ['role1'], ['role1', 'role2']);

    $select = $this->createMock('Drupal\Core\Database\Query\SelectInterface');
    $select->expects($this->exactly(3))
      ->method('fields')
      ->with('ur', ['authorization_drupal_roles_roles_value'])
      ->willReturnSelf();
    $select->expects($this->exactly(6))
      ->method('condition')
      ->willReturnSelf();
    $select->expects($this->exactly(3))
      ->method('execute')
      ->willReturn($statement);

    $this->database->expects($this->exactly(3))
      ->method('select')
      ->willReturn($select);

    $sandbox['current'] = 1;
    authorization_drupal_roles_update_8004($sandbox);

    $this->assertEquals(1, $sandbox['#finished']);
    $this->assertArrayHasKey('profiles', $sandbox);
    $this->assertCount(2, $sandbox['profiles']);
    $this->assertArrayHasKey('profile_a', $sandbox['profiles']);
    $this->assertArrayHasKey('profile_b', $sandbox['profiles']);
    $this->assertArrayHasKey('total', $sandbox);
    $this->assertEquals(3, $sandbox['total']);
    $this->assertArrayHasKey('current', $sandbox);
    $this->assertEquals(4, $sandbox['current']);
    $this->assertArrayHasKey('user_ids', $sandbox);
    $this->assertEquals([], $sandbox['user_ids']);

  }

  /**
   * Tests update 8004, with two profiles and three users. 2nd iteration.
   */
  public function testTwoProfilesAndThreeUserOneIteration() {
    $sandbox = [
      'profiles' => [
        'profile1' => ['role1', 'role2'],
      ],
      'current' => 0,
      'total' => 3,
      'user_ids' => [1, 2, 3],
    ];

    $user3 = $this->prophesize('Drupal\user\UserInterface');
    $user3->get('authorization_drupal_roles_roles')->willReturn(['role1', 'role2']);
    $user3->hasRole('role1')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());

    $this->userData->set('authorization_drupal_roles', 3, 'roles', [
      'role1' => 'profile1',
    ])
      ->shouldBeCalled($this->once());

    $user_storage = $this->prophesize('Drupal\Core\Entity\EntityStorageInterface');
    $user_storage
      ->load(3)
      ->willReturn($user3->reveal())
      ->shouldBeCalled($this->once());
    $this->entityTypeManager
      ->getStorage('user')
      ->willReturn($user_storage->reveal());

    $this->datetime->getRequestTime()->willReturn(100);
    $this->datetime->getCurrentTime()->willReturn(200);

    $statement = $this->createMock('Drupal\Core\Database\StatementInterface');
    $statement->expects($this->once())
      ->method('fetchCol')
      ->willReturnOnConsecutiveCalls(['role1'], ['role1'], ['role1', 'role2']);

    $select = $this->createMock('Drupal\Core\Database\Query\SelectInterface');
    $select->expects($this->once())
      ->method('fields')
      ->with('ur', ['authorization_drupal_roles_roles_value'])
      ->willReturnSelf();
    $select->expects($this->exactly(2))
      ->method('condition')
      ->willReturnSelf();
    $select->expects($this->once())
      ->method('execute')
      ->willReturn($statement);

    $this->database->expects($this->once())
      ->method('select')
      ->willReturn($select);

    $sandbox['current'] = 1;
    authorization_drupal_roles_update_8004($sandbox);

    $this->assertEquals((2 / 3), $sandbox['#finished']);
    $this->assertArrayHasKey('profiles', $sandbox);
    $this->assertCount(1, $sandbox['profiles']);
    $this->assertArrayHasKey('profile1', $sandbox['profiles']);
    $this->assertArrayHasKey('total', $sandbox);
    $this->assertEquals(3, $sandbox['total']);
    $this->assertArrayHasKey('current', $sandbox);
    $this->assertEquals(2, $sandbox['current']);
    $this->assertArrayHasKey('user_ids', $sandbox);
    $this->assertEquals([1, 2], $sandbox['user_ids']);

  }

}
