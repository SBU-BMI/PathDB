<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the manage vocabulary page.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbVocabularyManageTest extends EasyBreadcrumbBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'easy_breadcrumb',
    'block',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->easyBreadcrumbCreateAndLoginAdminUser();
    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
  }

  /**
   * Tests the manage vocabulary page.
   */
  public function testEasyBreadcrumbVocabularyManage() {
    $this->drupalGet('admin/structure/taxonomy/manage/tags');
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Administration');
    $this->easyBreadcrumbAssertSegmentTextEquals(3, 'Structure');
    $this->easyBreadcrumbAssertSegmentTextEquals(4, 'Taxonomy');
    $this->easyBreadcrumbAssertSegmentTextEquals(5, 'Edit Tags');
  }

}
