<?php

namespace Drupal\prepopulate;

/**
 * Provides a service to populate a form.
 */
interface PopulateInterface {

  /**
   * Populate form with values.
   *
   * @param array &$form
   *   The form or form element to populate.
   * @param null|array|string $request_slice
   *   (optional) The values to populate.
   */
  public function populateForm(array &$form, $request_slice = NULL): void;

}
