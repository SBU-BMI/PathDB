<?php

namespace Drupal\Tests\restrict_by_ip\Functional;

use Drupal\user\Entity\Role;

/**
 * Tests roles are restricted to certain IP address range(s).
 *
 * @group restrict_by_ip
 */
class RoleTest extends RestrictByIPWebTestBase {

  /**
   * Test role.
   *
   * @var \Drupal\user\Entity\Role
   */
  private $role;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create a role with administer permissions so we can load the user edit,
    // page and test if the user has this role when logged in.
    $random_name = $this->randomMachineName();
    $rid = $this->drupalCreateRole(['administer permissions'], NULL, $random_name);
    $this->role = Role::load($rid);

    // Add created role to user.
    $this->regularUser->addRole($this->role->id());
    $this->regularUser->save();
  }

  /**
   * Test role no restrictions.
   */
  public function testRoleAppliedNoRestrictions() {
    $this->drupalLogin($this->regularUser);
    $this->drupalGet('user/' . $this->regularUser->id() . '/edit');
    $this->assertSession()->pageTextContains($this->role->label());
  }

  /**
   * Test role match IP.
   */
  public function testRoleAppliedMatchIp() {
    $this->conf->set('role.' . $this->role->id(), $this->currentIPCIDR)->save();
    $this->drupalLogin($this->regularUser);
    $this->drupalGet('user/' . $this->regularUser->id() . '/edit');
    $this->assertSession()->pageTextContains($this->role->label());
  }

  /**
   * Test role denied IP.
   */
  public function testRoleDeniedDifferIp() {
    $this->conf->set('role.' . $this->role->id(), $this->outOfRangeCIDR)->save();
    $this->drupalLogin($this->regularUser);
    $this->drupalGet('user/' . $this->regularUser->id() . '/edit');
    $this->assertSession()->pageTextNotContains($this->role->label());
  }

  /**
   * Test IP restrictions.
   */
  public function testUiRoleRenamed() {
    $this->conf->set('role.' . $this->role->id(), $this->currentIPCIDR)->save();
    $this->drupalLogin($this->regularUser);
    $edit = [];
    $edit['label'] = 'a new role name';
    $this->drupalGet('admin/people/roles/manage/' . $this->role->id());
    $this->submitForm($edit, t('Save'));
    $this->assertSession()->pageTextContains('Role a new role name has been updated.');
    $updatedConf = $this->config('restrict_by_ip.settings');
    $ip = $updatedConf->get('role.' . $this->role->id());
    $this->assertEquals($ip, $this->currentIPCIDR, 'IP restriction updated');
  }

  /**
   * Test role deleted.
   */
  public function testUiRoleDeleted() {
    $this->conf->set('role.' . $this->role->id(), $this->currentIPCIDR)->save();
    $this->drupalLogin($this->regularUser);
    $edit = [];
    $this->drupalGet('admin/people/roles/manage/' . $this->role->id() . '/delete');
    $this->submitForm($edit, t('Delete'));
    $this->assertSession()->pageTextContains('The role ' . $this->role->label() . ' has been deleted.');
    // If we get the default, we know the variable is deleted.
    $updatedConf = $this->config('restrict_by_ip.settings');
    $ip = $updatedConf->get('role.' . $this->role->id());
    $this->assertNull($ip, 'IP restriction deleted');
  }

}
