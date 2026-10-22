<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the dynamic page cache while using Easy Breadcrumb.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbDynamicPageCacheTest extends EasyBreadcrumbBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['easy_breadcrumb', 'block', 'cva'];

  /**
   * Tests dynamic cache with Mercury theme and exception pages.
   */
  public function testDynamicPageCacheWithMercury(): void {
    $this->container->get('theme_installer')->install(['mercury']);
    $this->container->get('config.factory')->getEditable('system.theme')
      ->set('default', 'mercury')
      ->save();

    // Visit a 404 page to warm the cache_dynamic_page_cache table.
    $this->drupalGet('/test-404-page-1');
    $this->assertSession()->statusCodeEquals(404);

    // The table row count for cache_dynamic_page_cache before visiting a second
    // 404 page.
    $tableRowCountBefore = \Drupal::database()
      ->select('cache_dynamic_page_cache')
      ->countQuery()
      ->execute()
      ->fetchField();

    // Visit a second 404 page.
    $this->drupalGet('/test-404-page-2');
    $this->assertSession()->statusCodeEquals(404);

    // The table row count for cache_dynamic_page_cache after visiting a second
    // 404 page.
    $tableRowCountAfter = \Drupal::database()
      ->select('cache_dynamic_page_cache')
      ->countQuery()
      ->execute()
      ->fetchField();

    // Assert that the cache_dynamic_page_cache has not grown.
    $this->assertEquals($tableRowCountBefore, $tableRowCountAfter);
  }

}
