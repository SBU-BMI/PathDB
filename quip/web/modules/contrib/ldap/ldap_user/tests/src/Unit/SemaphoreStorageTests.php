<?php

namespace Drupal\Tests\ldap_user\Unit;

use Drupal\ldap_user\Helper\SemaphoreStorage;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\ldap_user\Helper\SemaphoreStorage
 * @group ldap
 */
class SemaphoreStorageTests extends UnitTestCase {

  /**
   * Tests the password storage.
   */
  public function testPasswordStorage() {

    $this->assertFalse(SemaphoreStorage::get('sync', 'hpotter'));

    SemaphoreStorage::set('sync', 'hpotter');
    SemaphoreStorage::set('sync', 'hgranger');
    SemaphoreStorage::set('provision', 'hpotter');
    $this->assertTrue(SemaphoreStorage::get('sync', 'hpotter'));

    SemaphoreStorage::flushValue('sync', 'hpotter');
    $this->assertFalse(SemaphoreStorage::get('sync', 'hpotter'));
    $this->assertTrue(SemaphoreStorage::get('provision', 'hpotter'));

    SemaphoreStorage::set('sync', 'hpotter');
    SemaphoreStorage::flushAllValues();
    $this->assertFalse(SemaphoreStorage::get('sync', 'hpotter'));
    $this->assertFalse(SemaphoreStorage::get('sync', 'hgranger'));

  }

}
