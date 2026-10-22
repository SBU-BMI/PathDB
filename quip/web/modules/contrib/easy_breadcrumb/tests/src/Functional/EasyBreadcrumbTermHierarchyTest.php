<?php

declare(strict_types=1);

namespace Drupal\Tests\easy_breadcrumb\Functional;

use Drupal\easy_breadcrumb\EasyBreadcrumbConstants;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the TERM_HIERARCHY configuration.
 */
#[Group('easy_breadcrumb')]
class EasyBreadcrumbTermHierarchyTest extends EasyBreadcrumbBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'easy_breadcrumb',
    'block',
    'taxonomy',
  ];

  /**
   * The Parent Term.
   */
  protected Term $parent;

  /**
   * The Child Term.
   */
  protected Term $child;

  /**
   * The Grandchild Term.
   */
  protected Term $grandchild;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $vocabularyId = 'tags';
    Vocabulary::create(['vid' => $vocabularyId])->save();

    $this->parent = Term::create([
      'name' => 'Parent',
      'vid' => $vocabularyId,
    ]);
    $this->parent->save();

    $this->child = Term::create([
      'name' => 'Child',
      'vid' => $vocabularyId,
      'parent' => [$this->parent->id()],
    ]);
    $this->child->save();

    $this->grandchild = Term::create([
      'name' => 'Grandchild',
      'vid' => $vocabularyId,
      'parent' => [$this->child->id()],
    ]);
    $this->grandchild->save();

    $this->easyBreadcrumbSetConfig(EasyBreadcrumbConstants::TERM_HIERARCHY, TRUE);
    $this->drupalGet($this->grandchild->toUrl());
  }

  /**
   * Tests the TERM_HIERARCHY configuration.
   */
  public function testEasyBreadcrumbTermHierarchy() {
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Parent');
    $this->easyBreadcrumbAssertSegmentTextEquals(3, 'Child');
    $this->easyBreadcrumbAssertSegmentTextEquals(4, 'Grandchild');
  }

  /**
   * Tests access to crumbs of unpublished terms.
   */
  public function testEasyBreadcrumbTermHierarchyAccess() {
    $this->parent->setUnpublished()->save();
    $this->drupalGet($this->grandchild->toUrl());
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Child');
    $this->easyBreadcrumbAssertSegmentTextEquals(3, 'Grandchild');

    $this->child->setUnpublished()->save();
    $this->drupalGet($this->grandchild->toUrl());
    $this->easyBreadcrumbAssertSegmentTextEquals(2, 'Grandchild');
  }

}
