<?php

namespace Drupal\Tests\bootstrap\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Bootstrap theme settings form.
 *
 * @group bootstrap
 */
#[RunTestsInSeparateProcesses]
class ThemeSettingsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'jquery_ui',
    'jquery_ui_draggable',
    'jquery_ui_resizable',
  ];

  /**
   * {@inheritdoc}
   *
   * Bootstrap's theme settings schema is incomplete. This test specifically
   * covers the settings form render path.
   *
   * @see https://www.drupal.org/project/bootstrap/issues/3511614
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container->get('theme_installer')->install(['bootstrap']);
  }

  /**
   * Tests that the Bootstrap theme settings form opens without an error.
   */
  public function testThemeSettingsForm(): void {
    $account = $this->drupalCreateUser(['administer themes']);
    $this->drupalLogin($account);

    $this->drupalGet('admin/appearance/settings/bootstrap');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Bootstrap');
  }

}
