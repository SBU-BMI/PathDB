<?php

namespace Drupal\bootstrap\Plugin\Preprocess;

use Drupal\bootstrap\Bootstrap;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\filter\FilterFormatRepositoryInterface;
use Drupal\bootstrap\Utility\Variables;
use Drupal\Core\Url;

/**
 * Pre-processes variables for the "filter_tips" theme hook.
 *
 * @ingroup plugins_preprocess
 *
 * @BootstrapPreprocess("filter_tips",
 *   replace = "template_preprocess_filter_tips"
 * )
 */
class FilterTips extends PreprocessBase implements PreprocessInterface {

  /**
   * {@inheritdoc}
   */
  public function preprocessVariables(Variables $variables) {
    /** @var \Drupal\filter\FilterFormatInterface $current_format */
    $current_format = Bootstrap::service('current_route_match')->getParameter('filter_format');
    $current_format_id = $current_format ? $current_format->id() : FALSE;

    // Create a place holder for the tabs.
    $build['tabs'] = [
      '#theme' => 'item_list__filter_tips__tabs',
      '#items' => [],
      '#attributes' => [
        'class' => ['nav', 'nav-tabs', 'filter-formats'],
        'role' => 'tablist',
      ],
    ];

    // Create a placeholder for the panes.
    $build['panes'] = [
      '#theme_wrappers' => ['container__filter_tips__panes'],
      '#attributes' => [
        'class' => ['tab-content'],
      ],
    ];

    foreach (DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.4.0', fn() => Bootstrap::service(FilterFormatRepositoryInterface::class)->getFormatsForAccount(Bootstrap::service('current_user')), fn() => filter_formats(Bootstrap::service('current_user'))) as $format_id => $format) {
      // Set the current format ID to the first format.
      if (!$current_format_id) {
        $current_format_id = $format_id;
      }

      $tab = [
        '#type' => 'link',
        '#title' => $format->label(),
        '#url' => Url::fromRoute('filter.tips', ['filter_format' => $format_id]),
        '#attributes' => [
          'role' => 'tab',
          'data-toggle' => 'tab',
          'data-target' => "#$format_id",
        ],
      ];
      if ($current_format_id === $format_id) {
        $tab['#wrapper_attributes']['class'][] = 'active';
      }
      $build['tabs']['#items'][] = $tab;

      $tips = [];

      // Iterate over each format's enabled filters.
      /** @var \Drupal\filter\Plugin\FilterBase $filter */
      foreach ($format->filters() as $name => $filter) {
        // Ignore filters that are not enabled.
        if (!$filter->status) {
          continue;
        }

        $tip = $filter->tips(TRUE);
        if (isset($tip)) {
          $tips[] = ['#markup' => $tip];
        }
      }

      // Construct the pane.
      $pane = [
        '#theme_wrappers' => ['container__filter_tips'],
        '#attributes' => [
          'class' => ['tab-pane', 'fade'],
          'id' => $format_id,
        ],
        'list' => [
          '#theme' => 'item_list',
          '#items' => $tips,
        ],
      ];
      if ($current_format_id === $format_id) {
        $pane['#attributes']['class'][] = 'active';
        $pane['#attributes']['class'][] = 'in';
      }
      $build['panes'][] = $pane;
    }

    $variables['tips'] = $build;
  }

}
