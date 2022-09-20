<?php

declare(strict_types = 1);

namespace Drupal\Tests\ldap_user\Kernel;

/**
 * Helper function for getString().
 */
class GetStringHelper {

  /**
   * Value.
   *
   * @var mixed
   */
  public $value;

  /**
   * Return $value as string.
   *
   * @return string
   *   Value.
   */
  public function getString(): string {
    return (string) $this->value;
  }

}
