<?php

/**
 * @file
 * Drupal8 W3CSS Theme.theme.
 *
 * Filename:     drupal8_w3css_theme.theme
 * Website:      http://www.flashwebcenter.com
 * Description:  template
 * Author:       Alaa Haddad http://www.alaahaddad.com.
 */

/**
 * The theme settings.
 */

$form['drupal8_w3css_theme_settings']['w3_theme_current'] = [
  '#type' => 'details',
  '#title' => t('W3CSS Predefined Themes'),
  '#collapsible' => TRUE,
  '#collapsed' => FALSE,
  '#description'   => t('<p><strong>Copy and paste one of the names.</strong></p><p>w3-theme-amber, w3-theme-black, w3-theme-blue-grey, w3-theme-blue, w3-theme-brown, w3-theme-cyan, w3-theme-dark-grey, w3-theme-deep-orange, w3-theme-deep-purple, w3-theme-green, w3-theme-grey, w3-theme-indigo, w3-theme-khaki, w3-theme-light-blue, w3-theme-light-green, w3-theme-lime, w3-theme-orange, w3-theme-pink, w3-theme-purple, w3-theme-red, w3-theme-teal, w3-theme-yellow and w3-theme-custom.</p><p>BTW, w3-theme-custom is a file for your custom theme colors you can generate from https://www.w3schools.com/w3css/w3css_color_themes.asp</p>'),
];
$form['drupal8_w3css_theme_settings']['w3_theme_current']['current_theme'] = [
  '#type' => 'textfield',
  '#title' => t('Enter Your Desired Theme Name'),
  '#default_value' => theme_get_setting('current_theme'),

];
