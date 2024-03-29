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

$form['drupal8_w3css_theme_settings']['w3_match_height'] = [
  '#type' => 'details',
  '#title' => t('[Deprecated] Enable/Disable Match Height'),
  '#collapsible' => TRUE,
  '#collapsed' => FALSE,
  '#description'   => t('<p>The column height used to be controlled by javascript. Now they are controlled by CSS flexbox. Feel free to disable the checkboxes, since the default is now that the all columns will have matching height.</p>'),
];
$form['drupal8_w3css_theme_settings']['w3_match_height']['top_region'] = [
  '#type' => 'checkbox',
  '#title' => t('Check this box to enable Match Height for Top Containers'),
  '#default_value' => theme_get_setting('top_region'),
];
$form['drupal8_w3css_theme_settings']['w3_match_height']['main_region'] = [
  '#type' => 'checkbox',
  '#title' => t('Check this box to enable Match Height for Main Containers'),
  '#default_value' => theme_get_setting('main_region'),
];
$form['drupal8_w3css_theme_settings']['w3_match_height']['bottom_region'] = [
  '#type' => 'checkbox',
  '#title' => t('Check this box to enable Match Height for Bottom Containers'),
  '#default_value' => theme_get_setting('bottom_region'),
];
$form['drupal8_w3css_theme_settings']['w3_match_height']['footer_region'] = [
  '#type' => 'checkbox',
  '#title' => t('Check this box to enable Match Height for Footer Containers'),
  '#default_value' => theme_get_setting('footer_region'),
];
