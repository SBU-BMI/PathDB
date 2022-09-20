<?php

/**
 * @file
 * Post update functions for Taxnomy Access Lite.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;

/**
 * Update taxonomy views for new grants cache context.
 */
function tac_lite_post_update_taxonomy_views_cache_context(&$sandbox = NULL) {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function ($view) {
    // We only care to update taxonomy views.
    if ($view->get('base_table') === 'taxonomy_term_field_data') {
      // We want cacheability to be recalculated.
      $view->setSyncing(FALSE);
      $view_storage = \Drupal::entityTypeManager()->getStorage('view');
      $view->preSave($view_storage);

      return TRUE;
    }

    return FALSE;
  });
}
