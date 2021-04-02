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

use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_system_theme_settings_alter().
 */
function drupal8_w3css_theme_form_system_theme_settings_alter(&$form, FormStateInterface $form_state) {

  // Theme settings files.
  require_once __DIR__ . '/includes/external_libraries.inc';
  require_once __DIR__ . '/includes/website_width.inc';
  require_once __DIR__ . '/includes/full_opacity_onscroll.inc';
  require_once __DIR__ . '/includes/match_height.inc';
  require_once __DIR__ . '/includes/equal_width.inc';
  require_once __DIR__ . '/includes/predefined_themes.inc';
  require_once __DIR__ . '/includes/advanced_site_colors.inc';
  require_once __DIR__ . '/includes/social_links.inc';
  require_once __DIR__ . '/includes/copyright.inc';
  require_once __DIR__ . '/includes/credit.inc';
}
