<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_authentication\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests mixed-mode access to the password reset form.
 */
#[Group('ldap')]
#[RunTestsInSeparateProcesses]
class ResetPasswordAccessTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'ldap_authentication',
    'ldap_servers',
    'externalauth',
    'ldap_user',
    'ldap_query',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->config('ldap_authentication.settings')
      ->set('authenticationMode', 'mixed')
      ->save();
  }

  /**
   * Local users can follow Reset your password from the user edit form.
   */
  public function testLocalUserCanResetPassword(): void {
    $account = $this->drupalCreateUser();
    $this->drupalLogin($account);

    $this->drupalGet('/user/' . $account->id() . '/edit');
    $this->assertSession()->linkExists('Reset your password');
    $this->clickLink('Reset your password');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals('/user/password');
    $this->assertSession()->pageTextContains('Reset your password');
  }

  /**
   * LDAP-mapped users are denied the password reset form.
   */
  public function testLdapUserCannotResetPassword(): void {
    $account = $this->drupalCreateUser();
    $this->container->get('externalauth.authmap')
      ->save($account, 'ldap_user', 'hpotter');
    $this->drupalLogin($account);

    $this->drupalGet('/user/password');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Anonymous users can still use the password reset form in mixed mode.
   */
  public function testAnonymousCanResetPassword(): void {
    $this->drupalGet('/user/password');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Reset your password');
  }

}
