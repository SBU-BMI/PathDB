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

  // Theme settngs files.
  require_once dirname(__FILE__) . '/includes/website_width.inc';
  require_once dirname(__FILE__) . '/includes/match_height.inc';
  require_once dirname(__FILE__) . '/includes/equal_width.inc';
  require_once dirname(__FILE__) . '/includes/predefined_themes.inc';
  require_once dirname(__FILE__) . '/includes/advanced_site_colors.inc';
  require_once dirname(__FILE__) . '/includes/social_links.inc';
  require_once dirname(__FILE__) . '/includes/copyright.inc';
  require_once dirname(__FILE__) . '/includes/credit.inc';
}
