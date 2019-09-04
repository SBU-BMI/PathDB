<?php

namespace Drupal\views_base_url\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * A handler to output site's base url.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("base_url")
 */
class BaseUrl extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {}

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['show_link'] = ['default' => FALSE];
    $options['show_link_options']['contains'] = [
      'link_path' => ['default' => ''],
      'link_text' => ['default' => ''],
      'link_class' => ['default' => ''],
      'link_title' => ['default' => ''],
      'link_rel' => ['default' => ''],
      'link_fragment' => ['default' => ''],
      'link_query' => ['default' => ''],
      'link_target' => ['default' => ''],
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['show_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display as link'),
      '#description' => $this->t('Show base URL as link. You can create a custom link using this option.'),
      '#default_value' => $this->options['show_link'],
    ];

    $form['show_link_options'] = [
      '#type' => 'container',
      '#states' => [
        'invisible' => [
          ':input[type=checkbox][name="options[show_link]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['show_link_options']['link_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link path'),
      '#description' => $this->t('Drupal path for this link. The base url will be prepended to this path. If nothing provided then base url will appear as link.'),
      '#default_value' => $this->options['show_link_options']['link_path'],
    ];

    $form['show_link_options']['link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text'),
      '#description' => $this->t('Link text. If nothing provided then link path will appear as link text.'),
      '#default_value' => $this->options['show_link_options']['link_text'],
    ];

    $form['show_link_options']['link_class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link class'),
      '#description' => $this->t('CSS class to be applied to this link.'),
      '#default_value' => $this->options['show_link_options']['link_class'],
    ];

    $form['show_link_options']['link_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link title'),
      '#description' => $this->t('Title attribute for this link.'),
      '#default_value' => $this->options['show_link_options']['link_title'],
    ];

    $form['show_link_options']['link_rel'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link rel'),
      '#description' => $this->t('Rel attribute for this link.'),
      '#default_value' => $this->options['show_link_options']['link_rel'],
    ];

    $form['show_link_options']['link_fragment'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fragment'),
      '#description' => $this->t('Provide the ID with which you want to create fragment link.'),
      '#default_value' => $this->options['show_link_options']['link_fragment'],
    ];

    $form['show_link_options']['link_query'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link query'),
      '#description' => $this->t('Attach queries to the link. If there are multiple queries separate them using a space. For eg: %example1 OR %example2', [
        '%example1' => 'destination=node/add/page',
        '%example2' => 'destination=node/add/page q=some/page',
      ]),
      '#default_value' => $this->options['show_link_options']['link_query'],
    ];

    $form['show_link_options']['link_target'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link target'),
      '#description' => $this->t('Target attribute for this link.'),
      '#default_value' => $this->options['show_link_options']['link_target'],
    ];

    // This construct uses 'hidden' and not markup because process doesn't
    // run. It also has an extra div because the dependency wants to hide
    // the parent in situations like this, so we need a second div to
    // make this work.
    $form['show_link_options']['help'] = [
      '#type' => 'details',
      '#title' => $this->t('Replacement patterns'),
      '#value' => $this->getReplacementTokens(),
    ];

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    global $base_url;
    global $language;

    if ($this->options['show_link']) {
      $tokens = $this->getRenderTokens('');
      $link_query = [];

      // Link path.
      if (!empty($this->options['show_link_options']['link_path'])) {
        $aliased_path = $this->viewsTokenReplace($this->options['show_link_options']['link_path'], $tokens);
        $aliased_path = \Drupal::service('path.alias_manager')->getAliasByPath($aliased_path);
        $link_path = "$base_url$aliased_path";
      }
      else {
        $link_path = $base_url;
      }

      // Link text.
      if (!empty($this->options['show_link_options']['link_text'])) {
        $link_text = [
          '#plain_text' => $this->options['show_link_options']['link_text'],
        ];
      }
      else {
        $link_text = [
          '#plain_text' => $link_path,
        ];
      }

      // Link query.
      if (!empty($this->options['show_link_options']['link_query'])) {
        $queries = explode(' ', $this->options['show_link_options']['link_query']);
        foreach ($queries as $query) {
          $param = explode('=', $query);
          $link_query[$param[0]] = $param[1];
        }
      }

      // Create link with options.
      $url = Url::fromUri($link_path, [
        'attributes' => [
          'class' => explode(' ', $this->options['show_link_options']['link_class']),
          'title' => $this->options['show_link_options']['link_title'],
          'rel' => $this->options['show_link_options']['link_rel'],
          'target' => $this->options['show_link_options']['link_target'],
        ],
        'fragment' => $this->options['show_link_options']['link_fragment'],
        'query' => $link_query,
        'language' => $language,
      ]);

      // Replace token with values and return it as output.
      return [
        '#markup' => $this->viewsTokenReplace(Link::fromTextAndUrl($link_text, $url)->toString(), $tokens),
      ];
    }
    else {
      return [
        '#plain_text' => $base_url,
      ];
    }
  }

  /**
   * Returns a list of the available fields and arguments for token replacement.
   *
   * @return array
   *   Array of default help text and list of tokens.
   */
  protected function getReplacementTokens() {
    // Setup the tokens for fields.
    $previous = $this->getPreviousFieldLabels();
    $optgroup_arguments = (string) $this->t('Arguments');
    $optgroup_fields = (string) $this->t('Fields');
    foreach ($previous as $id => $label) {
      $options[$optgroup_fields]["{{ $id }}"] = substr(strrchr($label, ":"), 2);
    }
    // Add the field to the list of options.
    $options[$optgroup_fields]["{{ {$this->options['id']} }}"] = substr(strrchr($this->adminLabel(), ":"), 2);

    foreach ($this->view->display_handler->getHandlers('argument') as $arg => $handler) {
      $options[$optgroup_arguments]["{{ arguments.$arg }}"] = $this->t('@argument title', [
        '@argument' => $handler->adminLabel(),
      ]);
      $options[$optgroup_arguments]["{{ raw_arguments.$arg }}"] = $this->t('@argument input', [
        '@argument' => $handler->adminLabel(),
      ]);
    }

    $this->documentSelfTokens($options[$optgroup_fields]);

    // Default text.
    $output[] = [
      [
        '#markup' => '<p>' . $this->t('You must add some additional fields to this display before using this field. These fields may be marked as <em>Exclude from display</em> if you prefer. Note that due to rendering order, you cannot use fields that come after this field; if you need a field not listed here, rearrange your fields.') . '</p>',
      ],
      [
        '#markup' => '<p>' . $this->t("The following replacement tokens are available for this field. Note that due to rendering order, you cannot use fields that come after this field; if you need a field not listed here, rearrange your fields.") . '</p>',
      ],
    ];

    foreach (array_keys($options) as $type) {
      if (!empty($options[$type])) {
        $items = [];
        foreach ($options[$type] as $key => $value) {
          $items[] = $key . ' == ' . $value;
        }
        $item_list = [
          '#theme' => 'item_list',
          '#items' => $items,
        ];
        $output[] = $item_list;
      }
    }

    return $output;
  }

}
