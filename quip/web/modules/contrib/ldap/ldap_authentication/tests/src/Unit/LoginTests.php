<?php

namespace Drupal\Tests\ldap_authentication\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\ldap_authentication\Controller\LoginValidator
 * @group ldap
 */
class LoginTests extends UnitTestCase {

  /**
   * Test mixed user mode.
   */
  public function testMixedUserMode() {
    $this->assertTrue(TRUE);
    // TODO: Write test
    // assert right credentials LDAP
    // assert
    // see example data.
  }

  /**
   * Test exclusive user mode.
   */
  public function testExclusiveUserMode() {
    $this->assertTrue(TRUE);
    // TODO: Write test
    // assert right credentials LDAP
    // assert local Drupal user without mapping (associated, not associated)
    // see example data.
  }

  /**
   * Test SSO validation.
   */
  public function testSsoValidation() {
    $this->assertTrue(TRUE);
    // TODO: Write test
    // assert right credentials LDAP
    // assert wrong credentials (i.e. password random as it should be)
    // assert local Drupal user without mapping (associated, not associated)
    // test exclusive/mixed
    // see example data.
    // consider moving to ldap_sso.
  }

  /**
   * Test the whitelist.
   */
  public function testWhiteList() {
    $this->assertTrue(TRUE);
    // TODO: Write test
    // one value, two values, zero values
    // logon with whitelisted and w/o.
  }

  /**
   * Test the blacklist.
   */
  public function testBlacklist() {
    $this->assertTrue(TRUE);
    // TODO: Write test
    // one value, two values, zero values
    // logon with blacklisted and w/o.
  }

  // TODO: Review remaining functions of LoginValidator for tests.
}
