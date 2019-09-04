<?php

namespace Drupal\ldap_user\Helper;

/**
 * Management of the semaphore.
 *
 * This helps us to not run the same operation multiple times per request
 * per user.
 */
class SemaphoreStorage {

  private static $accounts = [];

  /**
   * Set value.
   *
   * @param string $action
   *   Action to apply on (e.g. 'sync').
   * @param string $identifier
   *   User identifier.
   */
  public static function set($action, $identifier) {
    self::$accounts[$action][$identifier] = TRUE;
  }

  /**
   * Get value.
   *
   * @param string $action
   *   Action to apply on (e.g. 'sync').
   * @param string $identifier
   *   User identifier.
   *
   * @return bool
   *   Boolean answer whether identifier present for action.
   */
  public static function get($action, $identifier) {
    if (isset(self::$accounts[$action], self::$accounts[$action][$identifier])) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Flush saved data for account.
   *
   * @param string $action
   *   Action to apply on (e.g. 'sync').
   * @param string $identifier
   *   User identifier.
   */
  public static function flushValue($action, $identifier) {
    unset(self::$accounts[$action][$identifier]);
  }

  /**
   * Flush all values.
   */
  public static function flushAllValues() {
    self::$accounts = [];
  }

}
