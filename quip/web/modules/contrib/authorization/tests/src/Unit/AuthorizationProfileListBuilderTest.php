<?php

declare(strict_types=1);

namespace Drupal\Tests\authorization\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\authorization\AuthorizationProfileInterface;
use Drupal\authorization\AuthorizationProfileListBuilder;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests AuthorizationProfileListBuilder.
 *
 * @coversDefaultClass \Drupal\authorization\AuthorizationProfileListBuilder
 *
 * @group authorization
 */
class AuthorizationProfileListBuilderTest extends UnitTestCase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * The entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityType;

  /**
   * The storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $storage;

  /**
   * The list builder.
   *
   * @var \Drupal\authorization\AuthorizationProfileListBuilder
   */
  protected $listBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $string_translation = $this->getStringTranslationStub();
    $container->set('string_translation', $string_translation);

    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $container->set('module_handler', $this->moduleHandler);

    \Drupal::setContainer($container);

    $this->entityType = $this->createMock(EntityTypeInterface::class);
    $this->storage = $this->createMock(EntityStorageInterface::class);

    $this->listBuilder = new AuthorizationProfileListBuilder($this->entityType, $this->storage);
  }

  /**
   * Test buildRow.
   */
  public function testBuildHeader() {
    $header = $this->listBuilder->buildHeader();

    $this->assertEquals('Profile', $header['label']);
    $this->assertEquals('Provider', $header['provider']);
    $this->assertEquals('Consumer', $header['consumer']);
    $this->assertEquals('Enabled', $header['enabled']);

  }

  /**
   * Test buildRow.
   */
  public function testBuildRow() {

    $entity = $this->createMock(AuthorizationProfileInterface::class);
    $entity->expects($this->once())
      ->method('label')
      ->willReturn('Test Profile');
    $entity->expects($this->exactly(3))
      ->method('get')
      ->willReturnOnConsecutiveCalls('Test Provider', 'Test Consumer', 1);

    $this->moduleHandler->expects($this->once())
      ->method('invokeAll')
      ->with('entity_operation', [$entity])
      ->willReturn(['test' => 'Test Provider']);

    $row = $this->listBuilder->buildRow($entity);

    $this->assertEquals('Test Profile', $row['label']);
    $this->assertEquals('Test Provider', $row['provider']);
    $this->assertEquals('Test Consumer', $row['consumer']);
    $this->assertEquals('Yes', $row['enabled']);
  }

}
