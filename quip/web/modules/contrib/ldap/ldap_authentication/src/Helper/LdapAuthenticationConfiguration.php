<?php

namespace Drupal\ldap_authentication\Helper;

use Drupal\user\UserInterface;

/**
 * Configuration helper class for LDAP authentication.
 *
 * @TODO: Make this class stateless.
 */
class LdapAuthenticationConfiguration {

  const MODE_MIXED = 1;
  const MODE_EXCLUSIVE = 2;

  public static $emailUpdateOnLdapChangeEnableNotify = 1;
  public static $emailUpdateOnLdapChangeEnable = 2;
  public static $emailUpdateOnLdapChangeDisable = 3;
  /**
   * Remove default later if possible, see also $emailUpdate.
   *
   * @var int
   */
  public static $emailUpdateOnLdapChangeDefault = 1;

  public static $passwordFieldShowDisabled = 2;
  public static $passwordFieldHide = 3;
  public static $passwordFieldAllow = 4;
  /**
   * Remove default later if possible, see also $passwordOption.
   *
   * @var int
   */
  public static $passwordFieldDefault = 2;

  public static $emailFieldRemove = 2;
  public static $emailFieldDisable = 3;
  public static $emailFieldAllow = 4;

  /**
   * Remove default later if possible, see also $emailOption.
   *
   * @var int
   */
  public static $emailFieldDefault = 3;

  /**
   * Are authentication servers available?
   *
   * @return bool
   *   Server available or not.
   */
  public static function hasEnabledAuthenticationServers() {
    return (count(self::getEnabledAuthenticationServers()) > 0) ? TRUE : FALSE;
  }

  /**
   * Return list of enabled authentication servers.
   *
   * @return \Drupal\ldap_servers\ServerFactory[]
   *   The list of available servers.
   */
  public static function getEnabledAuthenticationServers() {
    $servers = \Drupal::config('ldap_authentication.settings')->get('sids');
    /** @var \Drupal\ldap_servers\ServerFactory $factory */
    $factory = \Drupal::service('ldap.servers');
    $result = [];
    foreach ($servers as $server) {
      if ($factory->getServerByIdEnabled($server)) {
        $result[] = $server;
      }
    }
    return $result;
  }

  /**
   * Helper function to convert array to serialized lines.
   *
   * @param array $array
   *   List of items.
   *
   * @return string
   *   Serialized content.
   */
  public static function arrayToLines(array $array) {
    $lines = "";
    if (is_array($array)) {
      $lines = implode("\n", $array);
    }
    elseif (is_array(@unserialize($array))) {
      $lines = implode("\n", unserialize($array));
    }
    return $lines;
  }

  /**
   * Helper function to convert array to serialized lines.
   *
   * @param string $lines
   *   Serialized lines.
   *
   * @return array
   *   Deserialized content.
   */
  public static function linesToArray($lines) {
    $lines = trim($lines);

    if ($lines) {
      $array = preg_split('/[\n\r]+/', $lines);
      foreach ($array as $i => $value) {
        $array[$i] = trim($value);
      }
    }
    else {
      $array = [];
    }
    return $array;
  }

  /**
   * Should the password field be shown?
   *
   * @param \Drupal\user\UserInterface $user
   *   User account.
   *
   * @return bool
   *   Password status.
   */
  public static function showPasswordField(UserInterface $user = NULL) {

    if (!$user) {
      $user = \Drupal::currentUser();
    }

    // @TODO 2914053.
    if ($user->id() == 1) {
      return TRUE;
    }

    // Hide if LDAP authenticated and updating password is not allowed,
    // otherwise show.
    if (ldap_authentication_ldap_authenticated($user)) {
      if (\Drupal::config('ldap_authentication.settings')->get('passwordOption') == LdapAuthenticationConfiguration::$passwordFieldAllow) {
        return TRUE;
      }
      return FALSE;
    }
    return TRUE;

  }

}
