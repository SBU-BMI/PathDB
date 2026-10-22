<?php

declare(strict_types=1);

namespace Drupal\views_bulk_operations\Traits;

use Drupal\Core\Cache\Cache;

/**
 * Provides a helper to smoothly transition to strict PHP return types.
 */
trait ReturnTypeDeprecationTrait {

  /**
   * Per-request record of method signatures already resolved.
   *
   * @var array<string, array<string, bool>>
   */
  protected static array $checkedReturnTypeSignatures = [];

  /**
   * Warns when an overriding method lacks the expected return type.
   *
   * @param string $methodName
   *   The name of the method to inspect.
   * @param string $expectedType
   *   The expected native return type ('array', 'string', 'int', or a FQCN).
   * @param string $deprecatedInVersion
   *   The "project:X.Y.Z" version this deprecation started firing in.
   * @param string $removedInVersion
   *   The "project:X.Y.Z" version in which this type will be enforced.
   */
  protected function deprecateMissingReturnType(string $methodName, string $expectedType, string $deprecatedInVersion, string $removedInVersion): void {
    $currentClass = static::class;

    if ((self::$checkedReturnTypeSignatures[$currentClass][$methodName] ?? NULL) !== NULL) {
      return;
    }

    $cid = 'views_bulk_operations.return_type_deprecation:' . $currentClass . '::' . $methodName;
    $cache = \Drupal::cache('discovery');

    if ($cache->get($cid) !== FALSE) {
      self::$checkedReturnTypeSignatures[$currentClass][$methodName] = TRUE;
      return;
    }

    // Mark as resolved before the reflection work runs, and persist across
    // requests until the next cache rebuild.
    self::$checkedReturnTypeSignatures[$currentClass][$methodName] = TRUE;
    $cache->set($cid, TRUE, Cache::PERMANENT);

    try {
      $reflection = new \ReflectionMethod($this, $methodName);
      $baseClass = $reflection->getPrototype()->getDeclaringClass()->getName();

      if ($reflection->getDeclaringClass()->getName() !== $baseClass) {
        $returnType = $reflection->getReturnType();
        $typeName = $returnType instanceof \ReflectionNamedType ? $returnType->getName() : NULL;

        if ($typeName !== $expectedType) {
          @trigger_error(
            // Versions are parameters here, so phpcs can't validate them
            // against the literal sprintf() template.
            // phpcs:ignore
            \sprintf(
              '%s::%s() is deprecated in %s and will not be supported in %s. Add a "%s" return type declaration to your override. See https://www.drupal.org/project/views_bulk_operations/issues/3613375',
              $currentClass,
              $methodName,
              $deprecatedInVersion,
              $removedInVersion,
              $expectedType
            ),
            E_USER_DEPRECATED
          );
        }
      }
    }
    catch (\ReflectionException) {
      // Method doesn't exist; nothing to check.
    }
  }

}
