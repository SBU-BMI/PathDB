<?php

declare(strict_types=1);

namespace Drupal\Tests\views_bulk_operations\Functional;

use Drupal\views_bulk_operations\Plugin\views\field\ViewsBulkOperationsBulkForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that another handler's button on the same view still works.
 *
 * @see \Drupal\views_bulk_operations_test\Hook\ViewsBulkOperationsTestHooks::formAlter()
 */
#[CoversClass(ViewsBulkOperationsBulkForm::class)]
#[Group('views_bulk_operations')]
final class ViewsBulkOperationsOtherHandlerTest extends ViewsBulkOperationsFunctionalTestBase {

  /**
   * Tests that pressing another handler's button runs that handler.
   */
  public function testOtherHandlerButtonRuns(): void {
    $admin_user = $this->drupalCreateUser([
      'edit any page content',
      'access content overview',
    ]);
    $this->drupalLogin($admin_user);

    $this->drupalGet('views-bulk-operations-test');
    $assert_session = $this->assertSession();
    $assert_session->buttonExists('Other handler submit');

    $this->submitForm([], 'Other handler submit');
    $assert_session->pageTextContains('Other handler submit ran.');
    $assert_session->pageTextNotContains('Please select an action to perform.');
    $assert_session->pageTextNotContains('No items selected.');
  }

}
