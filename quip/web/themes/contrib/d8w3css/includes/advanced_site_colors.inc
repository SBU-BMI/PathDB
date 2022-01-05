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

$form['drupal8_w3css_theme_settings']['w3_colors'] = [
  '#type' => 'details',
  '#title' => t('Advanced Site Colors'),
  '#collapsible' => TRUE,
  '#collapsed' => FALSE,
  '#description'   => t('<table>
<tr>
  <td> </td>
  <th>Copy and paste your desired backgournd color, text color and link color in any region. </th>
 </tr>
  <tr>
    <th>Background Colors:</th>
    <td>w3-red, w3-pink, w3-purple, w3-deep-purple, w3-indigo, w3-blue, w3-light-blue, w3-cyan, w3-aqua, w3-teal, w3-green, w3-light-green, w3-lime, w3-sand, w3-khaki, w3-yellow, w3-amber, w3-orange, w3-deep-orange, w3-blue-grey, w3-brown, w3-light-grey, w3-grey, w3-dark-grey, w3-black, w3-white, w3-pale-red, w3-pale-yellow, w3-pale-green and w3-pale-blue</td>
  </tr>
  <tr>
    <th>Text Colors:</th>
    <td>w3-text-red, w3-text-pink, w3-text-purple, w3-text-deep-purple, w3-text-indigo, w3-text-blue, w3-text-light-blue, w3-text-cyan, w3-text-aqua, w3-text-teal, w3-text-green, w3-text-light-green, w3-text-lime, w3-text-sand, w3-text-khaki, w3-text-yellow, w3-text-amber, w3-text-orange, w3-text-deep-orange, w3-text-blue-grey, w3-text-brown, w3-text-light-grey, w3-text-grey, w3-text-dark-grey, w3-text-black and w3-text-white</td>
  </tr>
  <tr>
    <th>Link Text Colors:</th>
    <td>w3-link-red, w3-link-pink, w3-link-purple, w3-link-deep-purple, w3-link-indigo, w3-link-blue, w3-link-light-blue, w3-link-cyan, w3-link-aqua, w3-link-teal, w3-link-green, w3-link-light-green, w3-link-lime, w3-link-sand, w3-link-khaki, w3-link-yellow, w3-link-amber, w3-link-orange, w3-link-deep-orange, w3-link-blue-grey, w3-link-brown, w3-link-light-grey, w3-link-grey, w3-link-dark-grey, w3-link-black and w3-link-white</td>
  </tr>
  <tr>
    <th>Link Background Colors:</th>
    <td>w3-link-bg-red, w3-link-bg-green, w3-link-bg-blue, w3-link-bg-yellow, w3-link-bg-white, w3-link-bg-black, w3-link-bg-grey, w3-link-bg-amber, w3-link-bg-aqua, w3-link-bg-light-blue, w3-link-bg-brown, w3-link-bg-cyan, w3-link-bg-blue-grey, w3-link-bg-blue-gray, w3-link-bg-light-green, w3-link-bg-indigo, w3-link-bg-khaki, w3-link-bg-lime, w3-link-bg-orange, w3-link-bg-deep-orange, w3-link-bg-pink, w3-link-bg-purple, w3-link-bg-deep-purple, w3-link-bg-sand, w3-link-bg-teal, w3-link-bg-light-gray, w3-link-bg-light-grey, w3-link-bg-dark-gray, w3-link-bg-dark-grey</td>
  </tr>
  <tr>
    <th>Link Hover Colors:</th>
    <td>w3-link-red-hover, w3-link-green-hover, w3-link-blue-hover, w3-link-yellow-hover, w3-link-white-hover, w3-link-black-hover, w3-link-grey-hover, w3-link-amber-hover, w3-link-aqua-hover, w3-link-light-blue-hover, w3-link-brown-hover, w3-link-cyan-hover, w3-link-blue-grey-hover, w3-link-blue-gray-hover, w3-link-light-green-hover, w3-link-indigo-hover, w3-link-khaki-hover, w3-link-lime-hover, w3-link-orange-hover, w3-link-deep-orange-hover, w3-link-pink-hover, w3-link-purple-hover, w3-link-deep-purple-hover, w3-link-sand-hover, w3-link-teal-hover, w3-link-light-gray-hover, w3-link-light-grey-hover, w3-link-dark-gray-hover, w3-link-dark-grey-hover</td>
  </tr>
  <tr>
    <th>To Disable:</th>
    <td>To disable shadow: w3-box-shadow-0 | To disable border: w3-border-0 | To disable border round: w3-round-0 | To disable margin: w3-margin-0 | To disable padding: w3-padding-0 | To disable the whole page opacity: w3-animate-opacity-disabled </td>
  </tr>
</table>'),
];

$form['drupal8_w3css_theme_settings']['w3_colors']['color_page_wrapper'] = [
  '#type' => 'textfield',
  '#title' => t('Page Wrapper'),
  '#default_value' => theme_get_setting('color_page_wrapper'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_header'] = [
  '#type' => 'textfield',
  '#title' => t('Header'),
  '#default_value' => theme_get_setting('color_header'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_primary_menu'] = [
  '#type' => 'textfield',
  '#title' => t('Main Menu'),
  '#default_value' => theme_get_setting('color_primary_menu'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_welcome_text'] = [
  '#type' => 'textfield',
  '#title' => t('Welcome Text'),
  '#default_value' => theme_get_setting('color_welcome_text'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_highlighted'] = [
  '#type' => 'textfield',
  '#title' => t('Highlighted'),
  '#default_value' => theme_get_setting('color_highlighted'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_top_container'] = [
  '#type' => 'textfield',
  '#title' => t('Top Container Wrapper'),
  '#default_value' => theme_get_setting('color_top_container'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_top_first'] = [
  '#type' => 'textfield',
  '#title' => t('Top First'),
  '#default_value' => theme_get_setting('color_top_first'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_top_second'] = [
  '#type' => 'textfield',
  '#title' => t('Top Second'),
  '#default_value' => theme_get_setting('color_top_second'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_top_third'] = [
  '#type' => 'textfield',
  '#title' => t('Top Third'),
  '#default_value' => theme_get_setting('color_top_third'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_page_title'] = [
  '#type' => 'textfield',
  '#title' => t('Page Title'),
  '#default_value' => theme_get_setting('color_page_title'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_main'] = [
  '#type' => 'textfield',
  '#title' => t('Main Container Wrapper'),
  '#default_value' => theme_get_setting('color_main'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_breadcrumb'] = [
  '#type' => 'textfield',
  '#title' => t('Breadcrumb'),
  '#default_value' => theme_get_setting('color_breadcrumb'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_sidebar_first'] = [
  '#type' => 'textfield',
  '#title' => t('Sidebar First'),
  '#default_value' => theme_get_setting('color_sidebar_first'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_content'] = [
  '#type' => 'textfield',
  '#title' => t('W3CSS Content'),
  '#default_value' => theme_get_setting('color_content'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_sidebar_second'] = [
  '#type' => 'textfield',
  '#title' => t('Sidebar Second'),
  '#default_value' => theme_get_setting('color_sidebar_second'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_bottom_container'] = [
  '#type' => 'textfield',
  '#title' => t('Bottom Container Wrapper'),
  '#default_value' => theme_get_setting('color_bottom_container'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_bottom_first'] = [
  '#type' => 'textfield',
  '#title' => t('Bottom First'),
  '#default_value' => theme_get_setting('color_bottom_first'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_bottom_second'] = [
  '#type' => 'textfield',
  '#title' => t('Bottom Second'),
  '#default_value' => theme_get_setting('color_bottom_second'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_bottom_third'] = [
  '#type' => 'textfield',
  '#title' => t('Bottom Third'),
  '#default_value' => theme_get_setting('color_bottom_third'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_bottom_forth'] = [
  '#type' => 'textfield',
  '#title' => t('Bottom Forth'),
  '#default_value' => theme_get_setting('color_bottom_forth'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_footer_container'] = [
  '#type' => 'textfield',
  '#title' => t('Footer Container Wrapper'),
  '#default_value' => theme_get_setting('color_footer_container'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_footer_first'] = [
  '#type' => 'textfield',
  '#title' => t('Footer First'),
  '#default_value' => theme_get_setting('color_footer_first'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_footer_second'] = [
  '#type' => 'textfield',
  '#title' => t('Footer Second'),
  '#default_value' => theme_get_setting('color_footer_second'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_footer_third'] = [
  '#type' => 'textfield',
  '#title' => t('Footer Third'),
  '#default_value' => theme_get_setting('color_footer_third'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_footer_menu'] = [
  '#type' => 'textfield',
  '#title' => t('Footer Menu'),
  '#default_value' => theme_get_setting('color_footer_menu'),
];
$form['drupal8_w3css_theme_settings']['w3_colors']['color_copyright'] = [
  '#type' => 'textfield',
  '#title' => t('Copyright'),
  '#default_value' => theme_get_setting('color_copyright'),
];
