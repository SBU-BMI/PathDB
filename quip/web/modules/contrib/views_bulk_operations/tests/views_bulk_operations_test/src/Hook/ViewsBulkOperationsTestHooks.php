<?php

declare(strict_types=1);

namespace Drupal\views_bulk_operations_test\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Contains test module hook implementations.
 */
class ViewsBulkOperationsTestHooks {

  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!\str_starts_with($form_id, 'views_form_views_bulk_operations_test')) {
      return;
    }

    $form['actions']['vbo_test_other_handler'] = [
      '#type' => 'submit',
      '#value' => 'Other handler submit',
      '#submit' => [[self::class, 'otherHandlerSubmit']],
    ];
  }

  /**
   * Submit callback for the other handler's button.
   */
  public static function otherHandlerSubmit(array &$form, FormStateInterface $form_state): void {
    \Drupal::messenger()->addStatus('Other handler submit ran.');
  }

}
