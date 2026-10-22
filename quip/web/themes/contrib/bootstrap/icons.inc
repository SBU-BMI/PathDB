<?php

/**
 * @file
 * Icon API support to provide Bootstrap Framework glyphicons.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\bootstrap\Hook\IconsHooks;

/**
 * Implements hook_icon_providers().
 */
#[LegacyHook]
function bootstrap_icon_providers() {
  return \Drupal::service(IconsHooks::class)->iconProviders();
}

/**
 * Implements hook_icon_bundles().
 */
#[LegacyHook]
function bootstrap_icon_bundles() {
  return \Drupal::service(IconsHooks::class)->iconBundles();
}
