<?php

declare(strict_types=1);

namespace Drupal\Tests\views_bulk_operations\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views_bulk_operations\Traits\ReturnTypeDeprecationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * ReturnTypeDeprecationTrait cache behavior test.
 */
#[CoversClass(ReturnTypeDeprecationTrait::class)]
#[Group('views_bulk_operations')]
final class ReturnTypeDeprecationTraitTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->resetCheckedSignatures();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->resetCheckedSignatures();
    parent::tearDown();
  }

  /**
   * Clears the trait's per-request static array to simulate a new request.
   */
  private function resetCheckedSignatures(): void {
    $property = new \ReflectionProperty(ReturnTypeDeprecationTraitTestBase::class, 'checkedReturnTypeSignatures');
    $property->setAccessible(TRUE);
    $property->setValue(NULL, []);
  }

  /**
   * Tests the reflection check only runs once per cache lifetime.
   */
  public function testDeprecationOnlyFiresOncePerCacheLifetime(): void {
    $cacheItems = [];
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->expects(self::exactly(2))
      ->method('get')
      ->willReturnCallback(function ($cid) use (&$cacheItems) {
        // $cacheItems is mutated by the 'set' callback below; phpstan
        // analyzes each closure in isolation and can't see that.
        // @phpstan-ignore-next-line function.impossibleType
        return \array_key_exists($cid, $cacheItems) ? $cacheItems[$cid] : FALSE;
      });
    $cache->expects(self::once())
      ->method('set')
      ->willReturnCallback(function ($cid, $data) use (&$cacheItems) {
        $cacheItems[$cid] = (object) ['data' => $data];
      });

    $container = new ContainerBuilder();
    $container->set('cache.discovery', $cache);
    \Drupal::setContainer($container);

    $deprecations = 0;
    \set_error_handler(function (int $errno) use (&$deprecations): bool {
      if ($errno === E_USER_DEPRECATED) {
        $deprecations++;
      }
      return TRUE;
    }, E_USER_DEPRECATED);

    try {
      // Two calls on the same object: the second is short-circuited by the
      // per-request static array, never touching the cache backend again.
      $child = new ReturnTypeDeprecationTraitTestChild();
      $child->execute([]);
      $child->execute([]);

      // Simulate a fresh request: the static array resets, but the
      // persistent cache populated above should still prevent the
      // reflection check (and the deprecation notice) from firing again.
      $this->resetCheckedSignatures();
      $child2 = new ReturnTypeDeprecationTraitTestChild();
      $child2->execute([]);
    }
    finally {
      \restore_error_handler();
    }

    self::assertSame(1, $deprecations);
  }

}

/**
 * Test fixture: base class with an untyped method calling the trait.
 */
class ReturnTypeDeprecationTraitTestBase {

  use ReturnTypeDeprecationTrait;

  /**
   * Executes something, deprecating overrides missing the return type.
   */
  // @phpstan-ignore-next-line missingType.return (intentionally untyped)
  public function execute(array $items) {
    $this->deprecateMissingReturnType('execute', 'array', 'test:1.0.0', 'test:2.0.0');
    return $items;
  }

}

/**
 * Test fixture: overrides execute() without a native "array" return type.
 */
class ReturnTypeDeprecationTraitTestChild extends ReturnTypeDeprecationTraitTestBase {

  /**
   * {@inheritdoc}
   */
  // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found
  public function execute(array $items) {
    // Deliberately re-declared with no return type: this is the override
    // the trait is meant to detect and warn about.
    return parent::execute($items);
  }

}
