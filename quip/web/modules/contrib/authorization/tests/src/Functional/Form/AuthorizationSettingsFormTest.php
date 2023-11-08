<?php

namespace Drupal\Tests\authorization\Functional\Form;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the AuthorizationSettingsForm form.
 *
 * @group authorization
 */
class AuthorizationSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['authorization'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create and log in a user with the necessary permissions.
    $this->drupalLogin($this->drupalCreateUser(['administer site configuration']));
  }

  /**
   * Tests the AuthorizationSettingsForm form.
   */
  public function testAuthorizationSettingsForm(): void {
    // Visit the form page.
    $this->drupalGet('admin/config/people/authorization/profile/settings');

    // Check the form elements.
    $this->assertSession()->checkboxNotChecked('authorization_message');

    // Submit the form with a different value.
    $this->submitForm(['authorization_message' => FALSE], 'Save configuration');

    // Check if the form was saved successfully.
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Check the saved configuration.
    $this->assertConfigValue('authorization.settings', 'authorization_message', FALSE);
  }

  /**
   * Asserts a configuration value.
   *
   * @param string $name
   *   The configuration name.
   * @param string $key
   *   The configuration key.
   * @param mixed $expected_value
   *   The expected value.
   */
  protected function assertConfigValue(string $name, string $key, $expected_value): void {
    $config = $this->config($name);
    $this->assertEquals($expected_value, $config->get($key), sprintf('The configuration value %s:%s is correct.', $name, $key));
  }

}
