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
  '#title' => t('D8W3CSS Predefined Color Themes'),
  '#collapsible' => TRUE,
  '#collapsed' => FALSE,
  '#description'   => t('<p><strong>Copy and paste one of the names.</strong></p><p>w3-theme-amber, w3-theme-black, w3-theme-blue-grey, w3-theme-blue, w3-theme-brown, w3-theme-cyan, w3-theme-dark-grey, w3-theme-deep-orange, w3-theme-deep-purple, w3-theme-green, w3-theme-grey, w3-theme-indigo, w3-theme-khaki, w3-theme-light-blue, w3-theme-light-green, w3-theme-lime, w3-theme-orange, w3-theme-pink, w3-theme-purple, w3-theme-red, w3-theme-teal and w3-theme-yellow.</p><p>w3-theme-00000, w3-theme-00001, w3-theme-00002, w3-theme-00003, w3-theme-00004, w3-theme-00005, w3-theme-00006, w3-theme-00007, w3-theme-00008, w3-theme-00009, w3-theme-00010, w3-theme-00011, w3-theme-00012, w3-theme-00013, w3-theme-00014, w3-theme-00015, w3-theme-00016, w3-theme-00017, w3-theme-00018, w3-theme-00019, w3-theme-00020, w3-theme-00021, w3-theme-00022, w3-theme-00023, w3-theme-00024, w3-theme-00025, w3-theme-00026, w3-theme-00027 and w3-theme-00028</p><p>More will be added here. </p><p>BTW, you can generate a custom theme from https://www.w3schools.com/w3css/w3css_color_themes.asp and add it to styles.css in the sub theme.</p>'),
];
$form['drupal8_w3css_theme_settings']['w3_theme_current']['current_theme'] = [
  '#type' => 'textfield',
  '#title' => t('Enter Your Desired Theme Name'),
  '#default_value' => theme_get_setting('current_theme'),

];
