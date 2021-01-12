<?php

namespace Drupal\Tests\views_bulk_operations\FunctionalJavaScript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\views_bulk_operations\Form\ViewsBulkOperationsFormTrait;

/**
 * @coversDefaultClass \Drupal\views_bulk_operations\Plugin\views\field\ViewsBulkOperationsBulkForm
 * @group views_bulk_operations
 */
class ViewsBulkOperationsBulkFormTest extends WebDriverTestBase {

  use ViewsBulkOperationsFormTrait;

  const TEST_NODE_COUNT = 15;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stable';


  /**
   * The assert session.
   *
   * @var \Drupal\Tests\WebAssert
   */
  protected $assertSession;

  /**
   * The page element.
   *
   * @var \Behat\Mink\Element\DocumentElement
   */
  protected $page;


  /**
   * The selected indexes of rows.
   *
   * @var array
   */
  protected $selectedIndexes = [];

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = [
    'node',
    'views',
    'views_bulk_operations',
    'views_bulk_operations_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create some nodes for testing.
    $this->drupalCreateContentType(['type' => 'page']);
    for ($i = 1; $i <= self::TEST_NODE_COUNT; $i++) {
      $this->drupalCreateNode([
        'type' => 'page',
        'title' => 'Title ' . $i,
      ]);
    }
    $admin_user = $this->drupalCreateUser(
      [
        'edit any page content',
        'create page content',
        'delete any page content',
      ]);
    $this->drupalLogin($admin_user);

    $this->assertSession = $this->assertSession();
    $this->page = $this->getSession()->getPage();

    $this->drupalGet('/views-bulk-operations-test');

    // Make sure a checkbox appears on all rows and the button exists.
    for ($i = 0; $i < 4; $i++) {
      $this->assertSession->fieldExists('edit-views-bulk-operations-bulk-form-' . $i);
    }
    $this->assertSession->buttonExists('Simple test action');

    $this->selectedIndexes = [0, 1, 3];

    foreach ($this->selectedIndexes as $selected_index) {
      $this->page->checkField('edit-views-bulk-operations-bulk-form-' . $selected_index);
    }

  }

  /**
   * Tests the VBO bulk form without dynamic insertion.
   */
  public function testViewsBulkOperationsWithOutDynamicInsertion() {

    $this->page->pressButton('Simple test action');

    foreach ($this->selectedIndexes as $index) {
      $this->assertSession->pageTextContains(sprintf('Test action (preconfig: Test setting, label: Title %s)', self::TEST_NODE_COUNT - $index));
    }
    $this->assertSession->pageTextContains(sprintf('Action processing results: Test (%s)', count($this->selectedIndexes)));

  }

  /**
   * Tests the VBO bulk form with dynamic insertion.
   *
   * Nodes inserted right after selecting targeted row(s) of the view.
   */
  public function testViewsBulkOperationsWithDynamicInsertion() {

    // Insert nodes.
    $nodes = [];
    for ($i = 100; $i < 100 + self::TEST_NODE_COUNT; $i++) {
      $nodes[] = $this->drupalCreateNode([
        'type' => 'page',
        'title' => 'Title ' . $i,
      ]);
    }

    $this->page->pressButton('Simple test action');

    foreach ($this->selectedIndexes as $index) {
      $this->assertSession->pageTextContains(sprintf('Test action (preconfig: Test setting, label: Title %s)', self::TEST_NODE_COUNT - $index));
    }
    $this->assertSession->pageTextContains(sprintf('Action processing results: Test (%s)', count($this->selectedIndexes)));

    // Remove nodes inserted in the middle.
    foreach ($nodes as $node) {
      $node->delete();
    }

  }

}
