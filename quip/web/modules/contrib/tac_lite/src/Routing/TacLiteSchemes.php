<?php

namespace Drupal\tac_lite\Routing;

use Symfony\Component\Routing\Route;

/**
 * Defines a route subscriber to register a url for serving Schemes.
 */
class TacLiteSchemes {
  /**
   * {@inheritdoc}
   */
  public function routes() {
    $routes = [];
    $config = \Drupal::config('tac_lite.settings');
    $schemes = $config->get('tac_lite_schemes');
    for ($i = 1; $i <= $schemes; $i++) {
      $routes['tac_lite.scheme_' . $i] = new Route(
        // Path to attach this route to.
        '/admin/config/people/tac_lite/scheme_' . $i,
        // Route defaults.
        [
          '_form' => '\Drupal\tac_lite\Form\SchemeForm',
          '_title' => 'Access by Taxonomy',
          'scheme' => $i,
        ],
        // Route requirements.
        [
          '_permission'  => 'administer tac_lite',
        ]
      );
    }
    return $routes;
  }

}
