<?php

namespace Drupal\restrict_by_ip\Tests;
use Drupal\simpletest\WebTestBase;

/**
 * Tests logins are restricted to certain IP address range(s).
 *
 * @group restrict_by_ip
 *
 * Assumes that local testing environment has IP address of 127.0.0.1.
 */
class LoginTest extends WebTestBase {
  /**
   * @var \Drupal\user\Entity\User
   */
  private $regularUser;

  /**
   * @var \Drupal\Core\Config\Config
   */
  private $conf;

  public static $modules = ['restrict_by_ip'];

  public function setUp() {
    // Enable modules needed for these tests.
    parent::setUp();

    $this->conf = $this->config('restrict_by_ip.settings');

    // Create a user that we'll use to test logins.
    $this->regularUser = $this->drupalCreateUser();
  }

  // User can login when users IP matches global range.
  public function testIpMatchGlobal() {
    // Add global IP range.
    $this->conf->set('login_range', '127.0.0.0/8')->save();
    $this->drupalLogin($this->regularUser);
  }

  // User disallowed login outside global range.
  public function testIpDifferGlobal() {
    // Add global IP range.
    $this->conf->set('login_range', '10.0.0.0/8')->save();
    $this->assertNoLogin();
  }

  // User can login when users IP matchs users range.
  public function testIpMatchUser() {
    // Add in range user IP.
    $this->conf->set('user.' . $this->regularUser->id(), '127.0.0.0/8')->save();
    $this->drupalLogin($this->regularUser);
  }

  // User disallowed login outside user range.
  public function testIpDifferUser() {
    // Add out of range user IP.
    $this->conf->set('user.' . $this->regularUser->id(), '10.0.0.0/8')->save();
    $this->assertNoLogin();
  }

  // User allowed login when users IP doesn't match global range but matches
  // users range.
  public function testIpDifferGlobalMatchUser() {
    // Add out of range global IP.
    $this->conf->set('login_range', '10.0.0.0/8');
    // Add in range user IP.
    $this->conf->set('user.' . $this->regularUser->id(), '127.0.0.0/8');
    $this->conf->save();
    $this->drupalLogin($this->regularUser);
  }

  // User allowed login when users IP doesn't match users range but matches
  // global range.
  public function testIpMatchGlobalDifferUser() {
    // Add out of range global IP.
    $this->conf->set('login_range', '127.0.0.0/8');
    // Add in range user IP.
    $this->conf->set('user.' . $this->regularUser->id(), '10.0.0.0/8');
    $this->conf->save();
    $this->drupalLogin($this->regularUser);
  }

  // User disallowed login when users IP doesn't match global or users range.
  public function testIpDifferGlobalDiffUser() {
    // Add out of range global IP.
    $this->conf->set('login_range', '10.0.0.0/8');
    // Add in range user IP.
    $this->conf->set('user.' . $this->regularUser->id(), '10.0.0.0/8');
    $this->conf->save();
    $this->assertNoLogin();
  }

  // User can login when users IP matches global and users range.
  public function testIpMatchGlobalMatchUser() {
    // Add out of range global IP.
    $this->conf->set('login_range', '127.0.0.0/8');
    // Add in range user IP.
    $this->conf->set('user.' . $this->regularUser->id(), '127.0.0.0/8');
    $this->conf->save();
    $this->drupalLogin($this->regularUser);
  }

  // Test users are logged out forcefully
  public function testForceLogout() {
    // First login
    $this->drupalLogin($this->regularUser);
    // Add out of range global IP.
    $this->conf->set('login_range', '10.0.0.0/8')->save();
    // Load any page, and check if logged out.
    $this->dumpHeaders = TRUE;
    $this->drupalGet('user');
    $this->assertFalse($this->drupalUserIsLoggedIn($this->regularUser), t('User logged out.'));
  }

  // Test that deleting a user also removes any IP restrictions.
  public function testUserDelete() {
    $this->conf->set('user.' . $this->regularUser->id(), '10.0.0.0/8')->save();
    $this->regularUser->delete();
    $result = $this->conf->get('user.' . $this->regularUser->id());
    $this->assertNull($result);
  }

  // Assert user can't login.
  private function assertNoLogin() {
    $edit = [
      'name' => $this->regularUser->label(),
      'pass' => $this->regularUser->pass_raw
    ];
    $this->drupalPostForm('user', $edit, t('Log in'));

    $this->assertNoText('Member for', t('User %name unsuccessfully logged in.', ['%name' => $this->regularUser->label()]));
  }
}
