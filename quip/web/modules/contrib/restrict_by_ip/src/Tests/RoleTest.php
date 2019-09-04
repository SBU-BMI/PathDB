<?php

namespace Drupal\restrict_by_ip\Tests;
use Drupal\simpletest\WebTestBase;

/**
 * Tests roles are restricted to certain IP address range(s).
 *
 * @group restrict_by_ip
 *
 * Assumes that local testing environment has IP address of 127.0.0.1.
 */
class RoleTest extends WebTestBase {
  private $regularUser;
  private $role = [];

  public static $modules = ['restrict_by_ip'];

  public function setUp() {
    // Enable modules needed for these tests.
    parent::setUp();

    // Create a user that we'll use to test logins.
    $this->regularUser = $this->drupalCreateUser();

    // Create a role with administer permissions so we can load the user edit,
    // page and test if the user has this role when logged in.
    $rid = $this->drupalCreateRole(['administer permissions']);
    $roles = user_roles();
    $this->role['id'] = $rid;
    $this->role['name'] = $roles[$rid];

    // Add created role to user.
    $new_roles = $this->regularUser->roles + [$rid => $roles[$rid]];
    user_save($this->regularUser, ['roles' => $new_roles]);
  }

  public function testRoleAppliedNoRestrictions() {
    $this->drupalLogin($this->regularUser);
    $this->drupalGet('user/' . $this->regularUser->uid . '/edit');
    $this->assertText($this->role['name']);
  }

  public function testRoleAppliedMatchIP() {
    variable_set('restrict_by_ip_role_' . _restrict_by_ip_hash_role_name($this->role['name']), '127.0.0.0/8');
    $this->drupalLogin($this->regularUser);
    $this->drupalGet('user/' . $this->regularUser->uid . '/edit');
    $this->assertText($this->role['name']);
  }

  public function testRoleDeniedDifferIP() {
    variable_set('restrict_by_ip_role_' . _restrict_by_ip_hash_role_name($this->role['name']), '10.0.0.0/8');
    $this->drupalLogin($this->regularUser);
    $this->drupalGet('user/' . $this->regularUser->uid . '/edit');
    $this->assertNoText($this->role['name']);
  }

  // Test ip restrictions
  public function testUIRoleRenamed() {
    variable_set('restrict_by_ip_role_' . _restrict_by_ip_hash_role_name($this->role['name']), '127.0.0.0/8');
    $this->drupalLogin($this->regularUser);
    $form = [];
    $form['name'] = 'a new role name';
    $this->drupalPost('admin/people/permissions/roles/edit/' . $this->role['id'], $form, t('Save role'));
    $this->assertText('The role has been renamed.');
    $ip = variable_get('restrict_by_ip_role_' . _restrict_by_ip_hash_role_name('a new role name'), '');
    $this->assertEqual($ip, '127.0.0.0/8', 'IP restriction updated');
  }

  public function testUIRoleDeleted() {
    variable_set('restrict_by_ip_role_' . _restrict_by_ip_hash_role_name($this->role['name']), '127.0.0.0/8');
    $this->drupalLogin($this->regularUser);
    $form = [];
    $this->drupalPost('admin/people/permissions/roles/edit/' . $this->role['id'], $form, t('Delete role'));
    $this->drupalPost(NULL, $form, t('Delete'));
    $this->assertText('The role has been deleted.');
    // If we get the default, we know the variable is deleted.
    $ip = variable_get('restrict_by_ip_role_' . _restrict_by_ip_hash_role_name($this->role['name']), 'ip default');
    $this->assertEqual($ip, 'ip default', 'IP restriction deleted');
  }
}
