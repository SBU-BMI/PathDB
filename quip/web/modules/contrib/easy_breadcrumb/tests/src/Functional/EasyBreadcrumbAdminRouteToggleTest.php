<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests re-enabling "Applies to administration pages" without cache clear.
 *
 * @see https://www.drupal.org/project/easy_breadcrumb/issues/3619809
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbAdminRouteToggleTest extends EasyBreadcrumbBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'easy_breadcrumb',
    'block',
  ];

  /**
   * Tests that re-checking the setting restores the breadcrumb immediately.
   */
  public function testReEnablingAdminRoutesRestoresBreadcrumb() {
    $this->easyBreadcrumbCreateAndLoginAdminUser();

    // Start with the setting enabled: the breadcrumb title segment shows.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::APPLIES_ADMIN_ROUTES, TRUE);
    $this->drupalGet('admin');
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Administration');

    // Disable the setting: the breadcrumb title segment disappears.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::APPLIES_ADMIN_ROUTES, FALSE);
    $this->drupalGet('admin');
    $this->easyBreadcrumbAssertSegmentNotExists(2);

    // Re-enable the setting, without clearing caches: the breadcrumb title
    // segment should reappear immediately, matching the disable direction.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::APPLIES_ADMIN_ROUTES, TRUE);
    $this->drupalGet('admin');
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Administration');
  }

}
