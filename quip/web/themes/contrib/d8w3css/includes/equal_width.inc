<?php

/**
 * @file
 * W3CSS Theme.theme.
 *
 * Filename:     drupal8_w3css_theme.theme
 * Website:      http://www.flashwebcenter.com
 * Description:  template
 * Author:       Alaa Haddad http://www.alaahaddad.com.
 */

/**
 * The theme settings.
 */

$form['drupal8_w3css_theme_settings']['w3_equal_width'] = [
  '#type' => 'details',
  '#title' => t("Main Container Columns' Width"),
  '#collapsible' => TRUE,
  '#collapsed' => FALSE,
  '#description'   => t('<p>The default settings for the columns in the main containers is <strong>(25% 50% 25%)</strong></p>'),
];
$form['drupal8_w3css_theme_settings']['w3_equal_width']['equal_width'] = [
  '#type' => 'checkbox',
  '#title' => t('Change the columns in the main container to (33% 33% 33%)'),
  '#default_value' => theme_get_setting('equal_width'),
];
