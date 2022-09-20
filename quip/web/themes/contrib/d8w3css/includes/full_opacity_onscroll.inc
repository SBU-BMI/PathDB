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

$form['drupal8_w3css_theme_settings']['w3_full_opacity'] = [
  '#type' => 'details',
  '#title' => t("Enable Full Opacity On Scroll"),
  '#collapsible' => TRUE,
  '#collapsed' => FALSE,
  '#description'   => t('<p>When you visit the page, the hidden blocks are invisible and they will start to show/animate when you scroll down. The default setting is enabled.</p>'),
];
$form['drupal8_w3css_theme_settings']['w3_full_opacity']['full_opacity_onscroll'] = [
  '#type' => 'checkbox',
  '#title' => t('Check this box to animate the opacity on scroll down.'),
  '#default_value' => theme_get_setting('full_opacity_onscroll'),
];
