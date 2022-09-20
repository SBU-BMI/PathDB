<?php

declare(strict_types = 1);

namespace Drupal\ldap_servers;

/**
 * Helper functions to work around hard dependencies on the LDAP extension.
 */
trait LdapTransformationTraits {

  /**
   * Wrapper for ldap_escape().
   *
   * Helpful for unit testing without the PHP LDAP module.
   *
   * @param string $value
   *   String to escape.
   *
   * @return string
   *   Escaped string.
   */
  protected function ldapEscapeDn($value): string {
    if (\function_exists('ldap_escape')) {
      $value = ldap_escape($value, '', LDAP_ESCAPE_DN);
    }
    else {
      $value = self::php56PolyfillLdapEscape($value, '', 2);
    }

    // Copied from Symfonfy's Adapter.php for ease of use.
    // Per RFC 4514, leading/trailing spaces should be encoded in DNs,
    // as well as carriage returns.
    if (!empty($value) && strpos($value, ' ') === 0) {
      $value = '\\20' . substr($value, 1);
    }
    if (!empty($value) && $value[\strlen($value) - 1] === ' ') {
      $value = substr($value, 0, -1) . '\\20';
    }

    return str_replace("\r", '\0d', $value);
  }

  /**
   * Wrapper for ldap_escape().
   *
   * Helpful for unit testing without the PHP LDAP module.
   *
   * @param string $value
   *   String to escape.
   *
   * @return string
   *   Escaped string.
   */
  protected function ldapEscapeFilter($value): string {
    if (\function_exists('ldap_escape')) {
      $value = ldap_escape($value, '', LDAP_ESCAPE_FILTER);
    }
    else {
      $value = self::php56PolyfillLdapEscape($value, '', 1);
    }
    return $value;
  }

  /**
   * Stub implementation of the {@link ldap_escape()} function of ext-ldap.
   *
   * Escape strings for safe use in LDAP filters and DNs. Copied from polyfill
   * due to issues from testing infrastructure.
   *
   * @param string $subject
   *   Subject.
   * @param string $ignore
   *   Ignore.
   * @param int $flags
   *   Flags.
   *
   * @return string
   *   Escaped string.
   *
   * @see http://stackoverflow.com/a/8561604
   * @author Chris Wright <ldapi@daverandom.com>
   */
  public static function php56PolyfillLdapEscape(string $subject, $ignore = '', $flags = 0): string {

    $ldap_escape_filter = 1;
    $ldap_escape_dn = 2;

    static $charMaps = NULL;

    if (NULL === $charMaps) {
      $charMaps = [
        $ldap_escape_filter => ['\\', '*', '(', ')', "\x00"],
        $ldap_escape_dn => ['\\', ',', '=', '+', '<', '>', ';', '"', '#', "\r"],
      ];

      $charMaps[0] = [];

      for ($i = 0; $i < 256; ++$i) {
        $charMaps[0][\chr($i)] = sprintf('\\%02x', $i);
      }

      for ($i = 0, $l = \count($charMaps[$ldap_escape_filter]); $i < $l; ++$i) {
        $chr = $charMaps[$ldap_escape_filter][$i];
        unset($charMaps[$ldap_escape_filter][$i]);
        $charMaps[$ldap_escape_filter][$chr] = $charMaps[0][$chr];
      }

      for ($i = 0, $l = \count($charMaps[$ldap_escape_dn]); $i < $l; ++$i) {
        $chr = $charMaps[$ldap_escape_dn][$i];
        unset($charMaps[$ldap_escape_dn][$i]);
        $charMaps[$ldap_escape_dn][$chr] = $charMaps[0][$chr];
      }
    }

    // Create the base char map to escape.
    $flags = (int) $flags;
    $charMap = [];

    if ($flags & $ldap_escape_filter) {
      $charMap += $charMaps[$ldap_escape_filter];
    }

    if ($flags & $ldap_escape_dn) {
      $charMap += $charMaps[$ldap_escape_dn];
    }

    if (!$charMap) {
      $charMap = $charMaps[0];
    }

    // Remove any chars to ignore from the list.
    $ignore = (string) $ignore;

    for ($i = 0, $l = \strlen($ignore); $i < $l; ++$i) {
      unset($charMap[$ignore[$i]]);
    }

    // Do the main replacement.
    $result = strtr($subject, $charMap);

    // Encode leading/trailing spaces if self::LDAP_ESCAPE_DN is passed.
    if ($flags & $ldap_escape_dn) {
      if ($result[0] === ' ') {
        $result = '\\20' . substr($result, 1);
      }

      if ($result[\strlen($result) - 1] === ' ') {
        $result = substr($result, 0, -1) . '\\20';
      }
    }

    return $result;
  }

  /**
   * Wrapper for ldap_explode_dn().
   *
   * Try to avoid working with DN directly and instead use Entry objects.
   *
   * @param string $dn
   *   DN to explode.
   *
   * @return array
   *   Exploded DN.
   */
  public static function splitDnWithAttributes(string $dn): array {
    if (\function_exists('ldap_explode_dn')) {
      return ldap_explode_dn($dn, 0);
    }

    $rdn = explode(',', $dn);
    $rdn = array_map(static function ($attribute) {
      $attribute = trim($attribute);
      // This is a workaround for OpenLDAP escaping Unicode values.
      [$key, $value] = explode('=', $attribute);
      $value = str_replace('%', '\\', urlencode($value));
      return implode('=', [$key, $value]);
    }, $rdn);
    return ['count' => count($rdn)] + $rdn;
  }

  /**
   * Wrapper for ldap_explode_dn().
   *
   * Try to avoid working with DN directly and instead use Entry objects.
   *
   * @param string $dn
   *   DN to explode.
   *
   * @return array|false
   *   Exploded DN.
   */
  public static function splitDnWithValues(string $dn) {
    if (function_exists('ldap_explode_dn')) {
      return ldap_explode_dn($dn, 1);
    }

    $rdn = explode(',', $dn);
    $rdn = array_map(static function ($attribute) {
      $attribute = trim($attribute);
      // This is a workaround for OpenLDAP escaping Unicode values.
      /** @var string[] $elements */
      $elements = explode('=', $attribute);
      return str_replace('%', '\\', urlencode($elements[1]));
    }, $rdn);
    return ['count' => count($rdn)] + $rdn;
  }

}
