<?php

namespace Drupal\views_base_url\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A handler to output site's base url.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("base_url")
 */
class BaseUrl extends FieldPluginBase {

  use TokenTrait;

  /**
   * Definition of path alias manager.
   *
   * @var \Drupal\path_alias\AliasManager
   */
  protected $pathAliasManager;

  /**
   * The request context.
   *
   * @var \Drupal\Core\Routing\RequestContext
   */
  protected $requestContext;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AliasManagerInterface $pathAliasManager, RequestContext $request_context, LanguageManagerInterface $language_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->pathAliasManager = $pathAliasManager;
    $this->requestContext = $request_context;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('path_alias.manager'),
      $container->get('router.request_context'),
      $container->get('language_manager'),
    );
  }

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
    $base_url = $this->requestContext->getCompleteBaseUrl();
    $language = $this->languageManager->getCurrentLanguage();

    if ($this->options['show_link']) {
      $tokens = $this->getRenderTokens('');

      // Link path.
      if (!empty($this->options['show_link_options']['link_path'])) {
        $aliased_path = $this->simpleTokenReplace($this->options['show_link_options']['link_path'], $tokens);
        $aliased_path = $this->pathAliasManager->getAliasByPath($aliased_path);
        $link_path = "$base_url$aliased_path";
      }
      else {
        $link_path = $base_url;
      }

      $url = Url::fromUri($link_path, ['language' => $language]);

      // Link text.
      if (!empty($this->options['show_link_options']['link_text'])) {
        $link_text = [
          '#markup' => $this->viewsTokenReplace($this->options['show_link_options']['link_text'], $tokens),
        ];
      }
      else {
        $link_text = [
          '#plain_text' => $link_path,
        ];
      }

      // Link query.
      if (!empty($this->options['show_link_options']['link_query'])) {
        $link_query = trim($this->simpleTokenReplace($this->options['show_link_options']['link_query'], $tokens));
        if (!empty($link_query)) {
          $query_items = preg_split('/[ &]+/', $link_query, -1, PREG_SPLIT_NO_EMPTY);
          $query = [];
          foreach ($query_items as $query_item) {
            $param = explode('=', $query_item);
            $query[$param[0]] = $param[1];
          }
          $url->setOption('query', $query);
        }
      }

      // Link fragment.
      if (!empty($this->options['show_link_options']['link_fragment'])) {
        $link_fragment = trim($this->simpleTokenReplace($this->options['show_link_options']['link_fragment'], $tokens));
        if (!empty($link_fragment)) {
          $url->setOption('fragment', $link_fragment);
        }
      }

      // Create link with options.
      $attributes = [];
      if (!empty($this->options['show_link_options']['link_class'])) {
        $link_class = trim($this->simpleTokenReplace($this->options['show_link_options']['link_class'], $tokens));
        if (!empty($link_class)) {
          $attributes['class'] = explode(' ', $link_class);
        }
      }
      if (!empty($this->options['show_link_options']['link_title'])) {
        $link_title = trim($this->simpleTokenReplace($this->options['show_link_options']['link_title'], $tokens));
        if (!empty($link_title)) {
          $attributes['title'] = $link_title;
        }
      }
      if (!empty($this->options['show_link_options']['link_rel'])) {
        $attributes['rel'] = $this->options['show_link_options']['link_rel'];
      }
      if (!empty($this->options['show_link_options']['link_target'])) {
        $attributes['target'] = $this->options['show_link_options']['link_target'];
      }
      if (!empty($attributes)) {
        $url->setOption('attributes', $attributes);
      }

      // Generate HTML link.
      return [
        '#markup' => Link::fromTextAndUrl($link_text, $url)->toString(),
      ];
    }
    else {
      return [
        '#plain_text' => $base_url,
      ];
    }
  }

}
