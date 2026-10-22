<?php

namespace Drupal\moderated_content_bulk_publish\Hook;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\Plugin\views\field\NodeBulkForm;
use Drupal\views\ViewExecutable;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for moderated_content_bulk_publish.
 */
class ModeratedContentBulkPublishHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public static function help($route_name, RouteMatchInterface $route_match) {
    if ($route_name === 'help.page.moderated_content_bulk_publish') {
      $readme_file = file_exists(__DIR__ . '/README.md') ? __DIR__ . '/README.md' : __DIR__ . '/README.txt';
      if (!file_exists($readme_file)) {
        return NULL;
      }
      $text = file_get_contents($readme_file);
      if ($text && !\Drupal::moduleHandler()->moduleExists('markdown')) {
        return '<pre>' . $text . '</pre>';
      }
      else {
        // Use the Markdown filter to render the README.
        $filter_manager = \Drupal::service('plugin.manager.filter');
        $settings = \Drupal::configFactory()->get('markdown.settings')->getRawData();
        $config = [
          'settings' => $settings,
        ];
        $filter = $filter_manager->createInstance('markdown', $config);
        return $filter->process($text, 'en');
      }
    }
    return NULL;
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for \Drupal\node\NodeForm.
   */
  #[Hook('form_node_form_alter')]
  public static function formNodeFormAlter(&$form, FormStateInterface $form_state) {
    // Attaches the dialog library to node forms.
    _moderated_content_bulk_publish_attach_dialog_library($form, FALSE);
  }

  /**
   * Implements hook_views_pre_render().
   *
   * @see \Drupal\node\Plugin\views\field\NodeBulkForm
   */
  #[Hook('views_pre_render')]
  public static function viewsPreRender(ViewExecutable $view) {
    // Attaches the dialog library to views that have a NodeBulkForm field.
    foreach ($view->field as $field) {
      if ($field instanceof NodeBulkForm) {
        _moderated_content_bulk_publish_attach_dialog_library($view->element, TRUE);
        break;
      }
    }
  }

  /**
   * Implements hook_theme()
   */
  #[Hook('theme')]
  public static function theme($existing, $type, $theme, $path) {
    return [
      'moderated_content_bulk_publish' => [
        'variables' => [
          'test_var' => NULL,
        ],
      ],
    ];
  }

  // Thanks to https://drupal.stackexchange.com/questions/270396/add-language-switcher-on-admin-toolbar

  /**
   * Implements hook_toolbar() (Display a language switcher for available languages on admin toolbar if site has more than one language).
   */
  #[Hook('toolbar')]
  public function toolbar() {
    $config = \Drupal::config('moderated_content_bulk_publish.settings');
    $items = [];
    if ($config->get('disable_toolbar_language_switcher')) {
      return $items;
    }
    // Get languages.
    $current_language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $languages = \Drupal::languageManager()->getLanguages();
    // Check if Language module is enabled and there is more than one language.
    $moduleHandler = \Drupal::service('module_handler');
    if (count($languages) > 1 && $moduleHandler->moduleExists('language')) {
      // Get current route.
      $route = \Drupal::service('path.matcher')->isFrontPage() ? '<front>' : '<current>';
      // Get links.
      $links = [];
      foreach ($languages as $language) {
        $url = new Url($route, [], [
          'language' => $language,
        ]);
        $links[] = [
          '#markup' => Link::fromTextAndUrl($language->getName(), $url)->toString(),
        ];
      }
      // Set cache dependencies.
      $items['admin_toolbar_langswitch'] = [
        '#cache' => [
          'contexts' => [
            'languages:language_interface',
            'url',
          ],
          'tags' => $config->getCacheTags(),
        ],
      ];
      // Build toolbar item and tray.
      $items['admin_toolbar_langswitch'] += [
        '#type' => 'toolbar_item',
        '#weight' => 999,
        'tab' => [
          '#type' => 'link',
          '#url' => Url::fromRoute('entity.configurable_language.collection'),
          '#title' => $this->t('Language') . ': ' . strtoupper($current_language),
          '#attributes' => [
            'class' => [
              'toolbar-item-admin-toolbar-langswitch',
            ],
            'title' => $this->t('Admin Toolbar Langswitch'),
          ],
        ],
        'tray' => [
          '#heading' => $this->t('Admin Toolbar Langswitch'),
          'content' => [
            '#theme' => 'item_list',
            '#items' => $links,
            '#attributes' => [
              'class' => [
                'toolbar-menu',
              ],
            ],
          ],
        ],
      ];
    }
    return $items;
  }

}
