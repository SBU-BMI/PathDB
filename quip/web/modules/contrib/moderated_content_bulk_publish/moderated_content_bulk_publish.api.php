<?php

/**
 * @file
 * Hooks provided by the Moderated Content Bulk Publish module.
 */

/**
 * @verify hooks
 *
 * @param \Drupal\moderated_content_bulk_publish\HookObject with properties
 *
 * @return void
 */

function hook_moderated_content_bulk_publish_verify_archived($hookObject): void {
  $state = 'archived';
  $limit = 20;
  $nid = $hookObject->nid;
  $bundle = $hookObject->bundle;
  $show_button = $hookObject->show_button;
  $markup = $hookObject->markup;
  $error_message = $hookObject->error_message;
  if (!$hookObject->nid) {
    $hookObject->show_button = TRUE;
    $hookObject->markup = '<b>' . t('Invalid nid') . '</b>';
  }
}

/**
 * @verify hooks
 *
 * @param \Drupal\moderated_content_bulk_publish\HookObject with properties
 *
 * @return void
 */
function hook_moderated_content_bulk_publish_verify_publish($hookObject): void {
  $state = 'publish';
  $nid = $hookObject->nid;
  $body_field_val = $hookObject->body_field_val;
  $validate_failure = $hookObject->validate_failure;
  $error_message = $hookObject->error_message;
  $msgdetail_isToken = $hookObject->msgdetail_isToken;
  $msgdetail_isPublished = $hookObject->msgdetail_isPublished;
  $msgdetail_isAbsoluteURL = $hookObject->msgdetail_isAbsoluteURL;
}

/**
 * @} End of "verify hooks".
 */
