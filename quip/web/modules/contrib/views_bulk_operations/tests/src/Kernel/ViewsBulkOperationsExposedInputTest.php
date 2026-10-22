<?php

declare(strict_types=1);

namespace Drupal\Tests\views_bulk_operations\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\views\Views;
use Drupal\views_bulk_operations\Plugin\views\field\ViewsBulkOperationsBulkForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests bulk form exposed input that contains an empty array.
 *
 * @see https://www.drupal.org/project/views_bulk_operations/issues/3625570
 */
#[CoversClass(ViewsBulkOperationsBulkForm::class)]
#[Group('views_bulk_operations')]
final class ViewsBulkOperationsExposedInputTest extends ViewsBulkOperationsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->createTestNodes([
      'page' => [
        'count' => 1,
      ],
    ]);
  }

  /**
   * Empty arrays in exposed input are kept and do not recurse.
   */
  public function testExposedInputWithEmptyArray(): void {
    $view = Views::getView('views_bulk_operations_test');
    self::assertNotNull($view);
    $view->setDisplay('page_1');
    // Top-level and nested empty arrays, plus unsorted keys so the
    // recursive sort is still asserted. The sticky filter default is set
    // explicitly, Drupal 10 adds it to the raw input otherwise.
    // @phpstan-ignore argument.type
    $view->setExposedInput([
      'zeta' => 'last',
      'sticky' => 'All',
      'empty' => [],
      'nested' => [
        'inner_empty' => [],
        'b' => 'second',
        'a' => 'first',
      ],
      'alpha' => 'first',
    ]);
    $view->execute();

    $field = $view->field['views_bulk_operations_bulk_form'];
    self::assertInstanceOf(ViewsBulkOperationsBulkForm::class, $field);

    $form = [];
    $form_state = new FormState();
    $form_state->setUserInput([]);
    $field->viewsForm($form, $form_state);

    self::assertArrayHasKey('views_bulk_operations_bulk_form', $form);

    $stored = $this->container->get('tempstore.private')
      ->get('views_bulk_operations_views_bulk_operations_test_page_1')
      ->get((string) $this->container->get('current_user')->id());

    self::assertIsArray($stored);
    self::assertSame([
      'alpha' => 'first',
      'empty' => [],
      'nested' => [
        'a' => 'first',
        'b' => 'second',
        'inner_empty' => [],
      ],
      'sticky' => 'All',
      'zeta' => 'last',
    ], $stored['exposed_input']);
  }

}
