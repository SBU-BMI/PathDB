<?php

/**
 * @file
 * Post-update functions for the Bootstrap theme.
 */

/**
 * Clears legacy HTTP cache entries containing incompatible Symfony objects.
 */
function bootstrap_post_update_clear_legacy_http_cache(&$sandbox) {
  foreach (\Drupal::service('theme_handler')->listInfo() as $theme_name => $theme) {
    if ($theme_name === 'bootstrap' || isset($theme->base_themes['bootstrap'])) {
      \Drupal::keyValueExpirable("theme:$theme_name:http")->deleteAll();
    }
  }

  return t('Cleared legacy Bootstrap HTTP cache entries.');
}
