<?php

declare(strict_types = 1);

namespace Drupal\Tests\ldap_authentication\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test the profile update form.
 *
 * @group ldap
 */
class LdapAuthenticationProfileUpdateFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  protected const FORM_PATH = '/user/ldap-profile-update';

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
  public function setUp(): void {
    parent::setUp();
    $this->config('ldap_authentication.settings')
      ->set('emailTemplateUsagePromptRegex', '.*@invalid\\.com')
      ->save();
  }

  /**
   * Test the form.
   */
  public function testForm(): void {
    // Anon not allowed.
    $this->drupalGet(self::FORM_PATH);
    $this->assertSession()->statusCodeEquals(403);

    // Regular user not allowed.
    $user = $this->drupalCreateUser();
    $this->drupalLogin($user);
    $this->drupalGet(self::FORM_PATH);
    $this->assertSession()
      ->pageTextContains('This form is only available to profiles which need an update.');

    // Regular user not allowed.
    $user = $this->drupalCreateUser([], NULL, FALSE, ['mail' => 'tester@invalid.com']);
    $this->drupalLogin($user);
    $this->drupalGet(self::FORM_PATH);
    $this->assertSession()
      ->pageTextNotContains('This form is only available to profiles which need an update.');

    $edit = [
      'mail' => 'tester2@invalid.com',
    ];
    $this->submitForm($edit, 'op');

    $this->assertSession()->pageTextContains('This email address still matches the invalid email template.');

    $edit = [
      'mail' => 'tester2@valid.com',
    ];
    $this->submitForm($edit, 'op');
    $this->assertSession()->pageTextContains('Your profile has been updated.');
  }

}
