<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the LANGUAGE_PATH_PREFIX_AS_SEGMENT configuration.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbLanguagePathPrefixAsSegmentTest extends EasyBreadcrumbBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'easy_breadcrumb',
    'block',
    'content_translation',
    'language',
    'path',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->easyBreadcrumbCreateAndLoginAdminUser();
    $this->drupalCreateContentType(['type' => 'page']);
    ConfigurableLanguage::createFromLangcode('hu')->save();

    $this->container->get('content_translation.manager')
      ->setEnabled('node', 'page', TRUE);

    $this->container->get('entity_type.bundle.info')
      ->clearCachedBundles();

    $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Test page',
      'path' => ['alias' => '/test-page'],
      'langcode' => 'hu',
    ]);

  }

  /**
   * Tests the LANGUAGE_PATH_PREFIX_AS_SEGMENT configuration.
   */
  public function testEasyBreadcrumbLanguagePathPrefixAsSegment() {
    // Tests that the language code is not present in the breadcrumb by default.
    $this->drupalGet('hu/test-page');
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Test Page');

    // Tests that the language code is not present in the breadcrumb when
    // just INCLUDE_INVALID_PATHS option is enabled.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::INCLUDE_INVALID_PATHS, TRUE);
    $this->drupalGet('hu/test-page');
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Test Page');

    // Tests that the language code is present in the breadcrumb when both
    // INCLUDE_INVALID_PATHS and LANGUAGE_PATH_PREFIX_AS_SEGMENT are enabled.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::LANGUAGE_PATH_PREFIX_AS_SEGMENT, TRUE);
    $this->drupalGet('hu/test-page');
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Hu');

    // Tests that the language code is present in the breadcrumb when only
    // LANGUAGE_PATH_PREFIX_AS_SEGMENT is enabled.
    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::INCLUDE_INVALID_PATHS, FALSE);
    $this->drupalGet('hu/test-page');
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Hu');
  }

}
