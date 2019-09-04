<?php

namespace Drupal\Tests\ldap_user\Unit;

use Drupal\ldap_user\Helper\ExternalAuthenticationHelper;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\ldap_user\Helper\ExternalAuthenticationHelper
 * @group ldap
 */
class ExternalAuthenticationHelperTests extends UnitTestCase {

  /**
   * Tests user exclusion for the authentication helper.
   */
  public function testUserExclusion() {

    // @TODO 2914053.
    /* Disallow user 1 */
    $account = $this->prophesize('\Drupal\user\Entity\User');
    $account->id()->willReturn(1);
    $this->assertTrue(ExternalAuthenticationHelper::excludeUser($account->reveal()));

    /* Disallow checkbox exclusion (everyone else allowed). */
    $account = $this->prophesize('\Drupal\user\Entity\User');
    $account->id()->willReturn(2);
    $value = new \stdClass();
    $value->value = 1;
    $account->get('ldap_user_ldap_exclude')->willReturn($value);
    $this->assertTrue(ExternalAuthenticationHelper::excludeUser($account->reveal()));

    /* Everyone else allowed. */
    $account = $this->prophesize('\Drupal\user\Entity\User');
    $account->id()->willReturn(2);
    $value = new \stdClass();
    $value->value = '';
    $account->get('ldap_user_ldap_exclude')->willReturn($value);
    $this->assertFalse(ExternalAuthenticationHelper::excludeUser($account->reveal()));

  }

}
