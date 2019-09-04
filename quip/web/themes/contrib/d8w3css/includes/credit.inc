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

$form['drupal8_w3css_theme_settings']['credit_link'] = [
  '#type' => 'details',
  '#title' => t('Credit'),
  '#collapsible' => TRUE,
  '#collapsed' => FALSE,
];
$form['drupal8_w3css_theme_settings']['credit_link']['show_credit_link'] = [
  '#type' => 'checkbox',
  '#title' => t('Show/Hide Credit Text'),
  '#default_value' => theme_get_setting('show_credit_link'),
  '#description'   => t('Please display credit in footer'),
];
