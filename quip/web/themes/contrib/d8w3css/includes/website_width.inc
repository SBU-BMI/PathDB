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

$form['drupal8_w3css_theme_settings']['w3_website_width'] = [
  '#type' => 'details',
  '#title' => t("Website's Width"),
  '#collapsible' => TRUE,
  '#collapsed' => FALSE,
  '#description'   => t("<p><strong>You can change the whole website's width by entering any of the desired width in pixel.</strong></p><p>w3-width-100-percent, w3-width-2560, w3-width-1920, w3-width-1600, w3-width-1360, w3-width-1280, w3-width-1024 and w3-width-800</p>"),
];
$form['drupal8_w3css_theme_settings']['w3_website_width']['website_width'] = [
  '#type' => 'textfield',
  '#title' => t("Enter Your Desired Website's Width"),
  '#default_value' => theme_get_setting('website_width'),
];
