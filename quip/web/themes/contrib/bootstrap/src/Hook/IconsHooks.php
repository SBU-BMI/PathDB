<?php

namespace Drupal\bootstrap\Hook;

use Drupal\bootstrap\Bootstrap;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for bootstrap.
 */
class IconsHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_icon_providers().
   */
  #[Hook('icon_providers')]
  public function iconProviders() {
    $providers['bootstrap'] = [
      'title' => $this->t('Bootstrap'),
      'url' => 'https://getbootstrap.com/docs/3.4/components/#glyphicons',
    ];
    return $providers;
  }

  /**
   * Implements hook_icon_bundles().
   */
  #[Hook('icon_bundles')]
  public function iconBundles() {
    $bundles = [];
    if (Bootstrap::getTheme()->hasGlyphicons()) {
      $bundles['bootstrap'] = [
        'render' => 'sprite',
        'provider' => 'bootstrap',
        'title' => $this->t('Bootstrap'),
        'version' => $this->t('Icons by Glyphicons'),
        'variations' => [
          'icon-white' => $this->t('White'),
        ],
        'settings' => [
          'tag' => 'span',
        ],
        'icons' => Bootstrap::glyphicons(),
      ];
    }
    return $bundles;
  }

}
