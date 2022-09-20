<?php

namespace Drupal\Tests\user_current_paths\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * This class provides methods specifically for testing something.
 *
 * @group user_current_paths
 */
class UserCurrentPathFunctionalTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'test_page_test',
    'user',
    'shortcut',
    'user_current_paths',
  ];

  /**
   * A user with authenticated permissions.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * A user with admin permissions.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('system.site')->set('page.front', '/test-page')->save();
    $this->user = $this->drupalCreateUser([], 'userNotLoggedIn', FALSE, ['id' => 2]);
    $this->adminUser = $this->drupalCreateUser([], 'userLoggedIn', TRUE, ['id' => 3]);
    $this->adminUser->addRole($this->createAdminRole('admin', 'admin'));
    $this->adminUser->save();
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests if the module installation, won't break the site.
   */
  public function testInstallation() {
    $session = $this->assertSession();
    $this->drupalGet('<front>');
    $session->statusCodeEquals(200);
  }

  /**
   * Tests if uninstalling the module, won't break the site.
   */
  public function testUninstallation() {
    // Go to uninstallation page an uninstall user_current_paths:
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->drupalGet('/admin/modules/uninstall');
    $session->statusCodeEquals(200);
    $page->checkField('edit-uninstall-user-current-paths');
    $page->pressButton('edit-submit');
    $session->statusCodeEquals(200);
    // Confirm deinstall:
    $page->pressButton('edit-submit');
    $session->statusCodeEquals(200);
    $session->pageTextContains('The selected modules have been uninstalled.');
  }

  /**
   * Tests, that the /user/edit route redirects to the currently logged in user.
   */
  public function testUserEditRouteIsCurrentUser() {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // Test first route:
    $this->drupalGet('/user/edit');
    $session->statusCodeEquals(200);
    $currentUrl = $this->getUrl();
    // Check if this page edits the current logged in user:
    $this->assertStringContainsString('user/3', $currentUrl);
    $session->elementAttributeContains('css', '#edit-name', 'value', 'userLoggedIn');
  }

  /**
   * Tests, that the /user/current/edit route redirects to the current user.
   */
  public function testUserCurrentRouteIsCurrentUser() {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // Test first route:
    $this->drupalGet('/user/current/edit');
    $session->statusCodeEquals(200);
    $currentUrl = $this->getUrl();
    // Check if this page edits the current logged in user:
    $this->assertStringContainsString('user/3', $currentUrl);
    $session->elementAttributeContains('css', '#edit-name', 'value', 'userLoggedIn');
  }

  /**
   * Tests, user wildcard action.
   */
  public function testWildCardActions() {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // Test user shortcut page:
    $this->drupalGet('/user/current/shortcuts');
    $session->statusCodeEquals(200);
    $currentUrl = $this->getUrl();
    $this->assertStringContainsString('user/3', $currentUrl);
    $session->pageTextContains('Shortcuts');
  }

}
