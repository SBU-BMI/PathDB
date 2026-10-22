<?php

namespace Drupal\bootstrap\Plugin\Preprocess;

use Drupal\bootstrap\Bootstrap;
use Drupal\bootstrap\Utility\Variables;

/**
 * Pre-processes variables for the "region" theme hook.
 *
 * @ingroup plugins_preprocess
 *
 * @BootstrapPreprocess("region")
 */
class Region extends PreprocessBase implements PreprocessInterface {

  /**
   * {@inheritdoc}
   */
  public function preprocessVariables(Variables $variables) {
    $region = $variables['elements']['#region'];
    $variables['region'] = $region;

    // Core's region preprocess already provides "content", so it is not set
    // here: it is the rendered string when the region is a #theme_wrappers
    // wrapper, or an array of block render arrays keyed by block ID when the
    // region uses #theme.
    // @see https://www.drupal.org/project/drupal/issues/3178202
    if ($region === 'help') {
      if (is_array($variables['content'])) {
        // The help block is not rendered yet and may still render to nothing
        // (e.g. no help text on this route). Render it now so the alert below
        // is only added when there is help to show. Preprocess runs inside the
        // region's own render, so the active render context collects the
        // bubbled cache metadata.
        $content = $variables['content'];
        $rendered = Bootstrap::renderer()->render($content);
        $variables['content'] = trim((string) $rendered) === '' ? '' : $rendered;
      }
      if (!empty($variables['content'])) {
        $variables['content'] = [
          'icon' => Bootstrap::glyphicon('question-sign'),
          'content' => ['#markup' => $variables['content']],
        ];
        $variables->addClass(['alert', 'alert-info', 'messages', 'info']);
      }
    }

    // Support for "well" classes in regions.
    static $region_wells;
    if (!isset($region_wells)) {
      $region_wells = $this->theme->getSetting('region_wells');
    }
    if (!empty($region_wells[$region])) {
      $variables->addClass($region_wells[$region]);
    }
  }

}
