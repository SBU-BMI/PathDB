<?php

namespace Drupal\Tests\restrict_by_ip\Functional;

/**
 * Test admin interfaces.
 *
 * @group restrict_by_ip
 */
class UiTest extends RestrictByIPWebTestBase {

  /**
   * Admin user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $adminUser;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    // Enable modules needed for these tests.
    parent::setUp();

    // Create admin user that can configure restrict_by_ip module and users.
    $this->adminUser = $this->drupalCreateUser([
      'administer restrict by ip',
      'administer users',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test validation function on user add form.
   */
  public function testUserRegisterValidation() {
    $form = [];
    $form['name'] = $this->randomMachineName(8);
    $form['mail'] = $this->randomMachineName(5) . '@domain.notatld';
    $pass = $this->randomMachineName(5);
    $form['pass[pass1]'] = $pass;
    $form['pass[pass2]'] = $pass;
    $form['restrict_by_ip_address'] = 'not_an_ip';
    $this->drupalGet('admin/people/create');
    $this->submitForm($form, t('Create new account'));
    $this->assertSession()->pageTextContains('IP address must be in CIDR notation.');
  }

  /**
   * Test adding ip restriction while adding a user.
   */
  public function testUserRegisterSubmit() {
    $form = [];
    $form['name'] = $this->randomMachineName(8);
    $form['mail'] = $this->randomMachineName(5) . '@domain.notatld';
    $pass = $this->randomMachineName(5);
    $form['pass[pass1]'] = $pass;
    $form['pass[pass2]'] = $pass;
    $form['restrict_by_ip_address'] = $this->currentIPCIDR;
    $this->drupalGet('admin/people/create');
    $this->submitForm($form, t('Create new account'));
    $user = user_load_by_name($form['name']);
    $this->assertSession()->pageTextContains(t('Created a new user account for @name. No email has been sent.', [
      '@name' => $form['name'],
    ]));
    $this->drupalGet('user/' . $user->id() . '/edit');
    $this->assertSession()->fieldValueEquals('restrict_by_ip_address', $form['restrict_by_ip_address']);
  }

  /**
   * Test validation function on user edit form.
   */
  public function testUserEditValidation() {
    $user = $this->drupalCreateUser();
    $this->drupalGet('user/' . $user->id() . '/edit');
    $this->assertSession()->fieldValueEquals('restrict_by_ip_address', '');

    $form = [];
    $form['restrict_by_ip_address'] = 'not_an_ip';
    $this->drupalGet('user/' . $user->id() . '/edit');
    $this->submitForm($form, t('Save'));
    $this->assertSession()->pageTextContains('IP address must be in CIDR notation.');
    $this->assertSession()->pageTextNotContains('The changes have been saved.');
  }

  /**
   * Test changing ip restrictions on user edit form.
   */
  public function testUserEditSubmit() {
    $user = $this->drupalCreateUser();
    $this->drupalGet('user/' . $user->id() . '/edit');
    $this->assertSession()->fieldValueEquals('restrict_by_ip_address', '');

    $form = [];
    $form['restrict_by_ip_address'] = $this->currentIPCIDR;
    $this->drupalGet('user/' . $user->id() . '/edit');
    $this->submitForm($form, t('Save'));
    $this->assertSession()->pageTextContains('The changes have been saved.');
    $this->assertSession()->fieldValueEquals('restrict_by_ip_address', $form['restrict_by_ip_address']);
  }

  /**
   * Test validation function on admin/config/people/restrict_by_ip/login/user.
   */
  public function testAdminAddUserValidation() {
    $user = $this->drupalCreateUser();
    $form = [];
    $form['name'] = $user->label() . ' (' . $user->id() . ')';
    $form['restriction'] = 'not_an_ip';
    $this->drupalGet('admin/config/people/restrict_by_ip/login/user');
    $this->submitForm($form, t('Save configuration'));
    $this->assertSession()->pageTextContains('IP address must be in CIDR notation.');
  }

  /**
   * Test add ip restrictions on admin/config/people/restrict_by_ip/login/user.
   */
  public function testAdminAddUserSubmit() {
    $user = $this->drupalCreateUser();
    $form = [];
    $form['name'] = $user->label() . ' (' . $user->id() . ')';
    $form['restriction'] = $this->currentIPCIDR;
    $this->drupalGet('admin/config/people/restrict_by_ip/login/user');
    $this->submitForm($form, t('Save configuration'));
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSession()->fieldValueEquals('restrict_by_ip_user_' . $user->id(), $form['restriction']);
  }

  /**
   * Test validation function on admin/config/people/restrict_by_ip/login/user.
   */
  public function testAdminEditUserValidation() {
    // First add a user.
    $user = $this->drupalCreateUser();
    $form = [];
    $form['name'] = $user->label() . ' (' . $user->id() . ')';
    $form['restriction'] = $this->currentIPCIDR;
    $this->drupalGet('admin/config/people/restrict_by_ip/login/user');
    $this->submitForm($form, t('Save configuration'));
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSession()->fieldValueEquals('restrict_by_ip_user_' . $user->id(), $form['restriction']);

    // Then update it's IP.
    $form = [];
    $form['restrict_by_ip_user_' . $user->id()] = 'not_an_ip';
    $this->drupalGet('admin/config/people/restrict_by_ip/login/user');
    $this->submitForm($form, t('Save configuration'));
    $this->assertSession()->pageTextContains('IP address must be in CIDR notation.');
  }

  /**
   * Test edit ip restrictions on admin/config/people/restrict_by_ip/login/user.
   */
  public function testAdminEditUserSubmit() {
    // First add a user.
    $user = $this->drupalCreateUser();
    $form = [];
    $form['name'] = $user->label() . ' (' . $user->id() . ')';
    $form['restriction'] = $this->currentIPCIDR;
    $this->drupalGet('admin/config/people/restrict_by_ip/login/user');
    $this->submitForm($form, t('Save configuration'));
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSession()->fieldValueEquals('restrict_by_ip_user_' . $user->id(), $form['restriction']);

    // Then update it's IP.
    $form = [];
    $form['restrict_by_ip_user_' . $user->id()] = $this->outOfRangeCIDR;
    $this->drupalGet('admin/config/people/restrict_by_ip/login/user');
    $this->submitForm($form, t('Save configuration'));
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSession()->fieldValueEquals('restrict_by_ip_user_' . $user->id(), $form['restrict_by_ip_user_' . $user->id()]);
  }

  /**
   * Test delete ip restrictions on admin/config/people/restrict_by_ip/login/user.
   */
  public function testAdminDeleteUserSubmit() {
    // First add a user.
    $user = $this->drupalCreateUser();
    $form = [];
    $form['name'] = $user->label() . ' (' . $user->id() . ')';
    $form['restriction'] = $this->currentIPCIDR;
    $this->drupalGet('admin/config/people/restrict_by_ip/login/user');
    $this->submitForm($form, t('Save configuration'));
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSession()->fieldValueEquals('restrict_by_ip_user_' . $user->id(), $form['restriction']);

    // Then delete it's IP.
    $form = [];
    $form['restrict_by_ip_user_' . $user->id()] = '';
    $this->drupalGet('admin/config/people/restrict_by_ip/login/user');
    $this->submitForm($form, t('Save configuration'));
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSession()->fieldNotExists('restrict_by_ip_user_' . $user->id());
  }

  /**
   * Test the restrict by ip general settings form.
   */
  public function testGeneralSettings() {
    $form = [];
    $form['restrict_by_ip_header'] = $this->randomMachineName(5);
    $this->drupalGet('admin/config/people/restrict_by_ip');
    $this->submitForm($form, t('Save configuration'));
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSession()->fieldValueEquals('restrict_by_ip_header', $form['restrict_by_ip_header']);
  }

  /**
   * Test validation on restrict login by ip settings form.
   */
  public function testLoginByIpSettingsValidation() {
    $form = [];
    $form['restrict_by_ip_login_range'] = 'not_an_ip';
    $this->drupalGet('admin/config/people/restrict_by_ip/login');
    $this->submitForm($form, t('Save configuration'));
    $this->assertSession()->pageTextContains('IP address must be in CIDR notation.');
  }

  /**
   * Test restrict login by ip settings form.
   */
  public function testLoginByIpSettingsSubmit() {
    $form = [];
    $form['restrict_by_ip_error_page'] = $this->randomMachineName(5);
    $form['restrict_by_ip_login_range'] = $this->currentIPCIDR;
    $this->drupalGet('admin/config/people/restrict_by_ip/login');
    $this->submitForm($form, t('Save configuration'));
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSession()->fieldValueEquals('restrict_by_ip_error_page', $form['restrict_by_ip_error_page']);
    $this->assertSession()->fieldValueEquals('restrict_by_ip_login_range', $form['restrict_by_ip_login_range']);
  }

  /**
   * Test validation on restrict role by ip settings form.
   */
  public function testRoleByIpSettingsValidation() {
    // Create a role to test.
    $this->drupalCreateRole([], 'test');

    $form = [];
    $form['restrict_by_ip_role_test'] = 'not_an_ip';
    $this->drupalGet('admin/config/people/restrict_by_ip/role');
    $this->submitForm($form, t('Save configuration'));
    $this->assertSession()->pageTextContains('IP address must be in CIDR notation.');
  }

  /**
   * Test restrict role by ip settings form.
   */
  public function testRoleByIpSettingsSubmit() {
    // Create a role to test.
    $this->drupalCreateRole([], 'test');

    $form = [];
    $form['restrict_by_ip_role_test'] = $this->currentIPCIDR;
    $this->drupalGet('admin/config/people/restrict_by_ip/role');
    $this->submitForm($form, t('Save configuration'));
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSession()->fieldValueEquals('restrict_by_ip_role_test', $form['restrict_by_ip_role_test']);
  }

}
