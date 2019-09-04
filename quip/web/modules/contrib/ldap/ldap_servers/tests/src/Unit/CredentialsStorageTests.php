<?php

namespace Drupal\Tests\ldap_servers\Unit;

use Drupal\ldap_servers\Helper\CredentialsStorage;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\ldap_servers\Helper\CredentialsStorage
 * @group ldap
 */
class CredentialsStorageTests extends UnitTestCase {

  /**
   * Test the temporary storage of passwords.
   */
  public function testCredentialsStorage() {
    $user = 'my-user';
    $password = 'my-pass';

    // Verify storage.
    $helper = new CredentialsStorage();
    $helper::storeUserDn($user);
    $helper::storeUserPassword($password);
    $this->assertEquals($user, $helper::getUserDn());
    $this->assertEquals($password, $helper::getPassword());

    // Verify storage across instance.
    $helper = new CredentialsStorage();
    $this->assertEquals($user, $helper::getUserDn());
    $this->assertEquals($password, $helper::getPassword());
    // Verify storage without instance.
    $this->assertEquals($user, CredentialsStorage::getUserDn());
    $this->assertEquals($password, CredentialsStorage::getPassword());

    // Unset storage.
    CredentialsStorage::storeUserDn(NULL);
    CredentialsStorage::storeUserPassword(NULL);
    $this->assertEquals(NULL, CredentialsStorage::getUserDn());
    $this->assertEquals(NULL, CredentialsStorage::getPassword());
  }

}
