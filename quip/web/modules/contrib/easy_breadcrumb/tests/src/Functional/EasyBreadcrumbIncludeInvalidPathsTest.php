<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the INCLUDE_INVALID_PATHS configuration.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbIncludeInvalidPathsTest extends EasyBreadcrumbBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'easy_breadcrumb',
    'block',
    'node',
    'path',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'page']);

    $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Test Page 1',
      'status' => 1,
      'path' => [
        'alias' => '/test/invalid/paths',
      ],
    ]);
  }

  /**
   * Tests the INCLUDE_INVALID_PATHS configuration.
   */
  public function testIncludeInvalidPaths() {
    // Tests breadcrumbs when the config is not set.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::INCLUDE_INVALID_PATHS, FALSE);
    $this->drupalGet('test/invalid/paths');
    $this->easyBreadcrumbAssertSegmentTextEquals(1, 'Home');
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Test Page 1');

    // Tests breadcrumbs when the config is set.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::INCLUDE_INVALID_PATHS, TRUE);
    $this->drupalGet('test/invalid/paths');
    $this->easyBreadcrumbAssertSegmentTextEquals(1, 'Home');
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Test');
    $this->easyBreadcrumbAssertSegmentTextEquals(3, 'Invalid');
    $this->easyBreadcrumbAssertSegmentTextEquals(4, 'Test Page 1');
  }

}
