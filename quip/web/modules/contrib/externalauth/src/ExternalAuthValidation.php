<?php

namespace Drupal\externalauth;

use Drupal\externalauth\Exception\ExternalAuthRegisterException;

/**
 * Shared validation helpers for external auth values.
 */
final class ExternalAuthValidation {

  /**
   * Validates authmap values before storing them.
   *
   * @param string $provider
   *   The name of the service providing external authentication.
   * @param string $authname
   *   The external authentication name to store in authmap.
   *
   * @throws \Drupal\externalauth\Exception\ExternalAuthRegisterException
   *   Thrown when one of the values exceeds a supported storage limit.
   */
  public static function validateAuthmapData(string $provider, string $authname): void {
    self::validateValueLength($provider, ExternalAuthStorageLimits::AUTHMAP_PROVIDER_MAX_LENGTH, 'authentication provider', FALSE);
    self::validateValueLength($authname, ExternalAuthStorageLimits::AUTHMAP_AUTHNAME_MAX_LENGTH, 'external authentication name');
  }

  /**
   * Validates a value length and throws a registration exception if too long.
   *
   * @param string $value
   *   The value to validate.
   * @param int $max_length
   *   The maximum supported length.
   * @param string $label
   *   A human-readable label for the value.
   * @param bool $multibyte
   *   Whether the value should be measured as a Unicode string.
   *
   * @throws \Drupal\externalauth\Exception\ExternalAuthRegisterException
   *   Thrown when the value exceeds the maximum supported length.
   */
  public static function validateValueLength(string $value, int $max_length, string $label, bool $multibyte = TRUE): void {
    $length = $multibyte ? mb_strlen($value) : strlen($value);
    if ($length > $max_length) {
      throw new ExternalAuthRegisterException(sprintf('The %s exceeds the maximum length of %d characters.', $label, $max_length));
    }
  }

}
