<?php

namespace Drupal\tac_lite\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;

/**
 * Provides local tasks for each search page.
 */
class SchemeLocalTask extends DeriverBase {
  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];
    $config = \Drupal::config('tac_lite.settings');
    $schemes = $config->get('tac_lite_schemes');
    for ($i = 1; $i <= $schemes; $i++) {
      $scheme = $config->get('tac_lite_config_scheme_' . $i);
      $title = $scheme['name'] ? $scheme['name'] : 'Scheme ' . $i;
      $this->derivatives[] = [
        'title' => $title,
        'route_name' => 'tac_lite.scheme_' . $i,
        'base_route' => 'tac_lite.administration',
        'weight' => $i,
      ];
    }
    return $this->derivatives;
  }

}
