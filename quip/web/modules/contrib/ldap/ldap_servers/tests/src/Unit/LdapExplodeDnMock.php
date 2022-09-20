<?php

declare(strict_types = 1);

namespace Drupal\Tests\ldap_servers\Unit;

/**
 * Helper class to make it possible to simulate ldap_explode_dn().
 */
class LdapExplodeDnMock {

  /**
   * Simulate explode_dn.
   *
   * @return array
   *   DN exploded, input ignored.
   */
  public static function ldapExplodeDn($input): array {
    return [
      'count' => 4,
      0 => 'cn=hpotter',
      1 => 'ou=Gryffindor',
      2 => 'ou=student',
      3 => 'ou=people',
      4 => 'dc=hogwarts',
      5 => 'dc=edu',
    ];
  }

}
