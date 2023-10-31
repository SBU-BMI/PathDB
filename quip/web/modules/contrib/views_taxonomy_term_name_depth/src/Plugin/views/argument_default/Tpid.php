<?php

namespace Drupal\views_taxonomy_term_name_depth\Plugin\views\argument_default;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\taxonomy\VocabularyStorageInterface;

/**
 * Taxonomy parent id default argument.
 *
 * @ViewsArgumentDefault(
 *   id = "taxonomy_tpid",
 *   title = @Translation("Taxonomy term parent ID from URL")
 * )
 */
class Tpid extends ArgumentDefaultPluginBase implements CacheableDependencyInterface {

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The vocabulary storage.
   *
   * @var \Drupal\taxonomy\VocabularyStorageInterface
   */
  protected $vocabularyStorage;

  /**
   * Constructs a new Tpid instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\taxonomy\VocabularyStorageInterface $vocabulary_storage
   *   The vocabulary storage.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, VocabularyStorageInterface $vocabulary_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->routeMatch = $route_match;
    $this->vocabularyStorage = $vocabulary_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager')->getStorage('taxonomy_vocabulary')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['term_page'] = ['default' => TRUE];
    $options['node'] = ['default' => FALSE];
    $options['anyall'] = ['default' => ','];
    $options['limit'] = ['default' => FALSE];
    $options['vids'] = ['default' => []];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['term_page'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Load default filter from term page'),
      '#default_value' => $this->options['term_page'],
    ];
    $form['node'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Load default filter from node page, that's good for related taxonomy blocks"),
      '#default_value' => $this->options['node'],
    ];

    $form['limit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Limit terms by vocabulary'),
      '#default_value' => $this->options['limit'],
      '#states' => [
        'visible' => [
          ':input[name="options[argument_default][taxonomy_tid][node]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $options = [];
    $vocabularies = $this->vocabularyStorage->loadMultiple();
    foreach ($vocabularies as $voc) {
      $options[$voc->id()] = $voc->label();
    }

    $form['vids'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Vocabularies'),
      '#options' => $options,
      '#default_value' => $this->options['vids'],
      '#states' => [
        'visible' => [
          ':input[name="options[argument_default][taxonomy_tid][limit]"]' => ['checked' => TRUE],
          ':input[name="options[argument_default][taxonomy_tid][node]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['anyall'] = [
      '#type' => 'radios',
      '#title' => $this->t('Multiple-value handling'),
      '#default_value' => $this->options['anyall'],
      '#options' => [
        ',' => $this->t('Filter to items that share all terms'),
        '+' => $this->t('Filter to items that share any term'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="options[argument_default][taxonomy_tid][node]"]' => ['checked' => TRUE],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state, &$options = []) {
    // Filter unselected items so we don't unnecessarily store giant arrays.
    $options['vids'] = array_filter($options['vids']);
  }

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    // Load default argument from taxonomy page.
    if (!empty($this->options['term_page'])) {
      if (($taxonomy_term = $this->routeMatch->getParameter('taxonomy_term')) && $taxonomy_term instanceof TermInterface) {
        return $this->getParentId($taxonomy_term);
      }
    }
    // Load default argument from node.
    if (!empty($this->options['node'])) {
      // Just check, if a node could be detected.
      if (($node = $this->routeMatch->getParameter('node')) && $node instanceof NodeInterface) {
        $taxonomy = [];
        foreach ($node->getFieldDefinitions() as $field) {
          if ($field->getType() == 'entity_reference' && $field->getSetting('target_type') == 'taxonomy_term') {
            $taxonomy_terms = $node->{$field->getName()}->referencedEntities();
            /** @var \Drupal\taxonomy\TermInterface $taxonomy_term */
            foreach ($taxonomy_terms as $taxonomy_term) {
              $taxonomy[$this->getParentId($taxonomy_term)] = $taxonomy_term->bundle();
            }
          }
        }
        if (!empty($this->options['limit'])) {
          $tids = [];
          // Filter by vocabulary.
          foreach ($taxonomy as $tid => $vocab) {
            if (!empty($this->options['vids'][$vocab])) {
              $tids[] = $tid;
            }
          }
          return implode($this->options['anyall'], $tids);
        }
        // Return all tids.
        else {
          return implode($this->options['anyall'], array_keys($taxonomy));
        }
      }
    }
  }

  /**
   * Callback function to get the Taxonomy parent ID.
   *
   * @param object $taxonomy_term
   *   Taxonomy term object.
   *
   * @return mixed|void
   *   Taxonomy id.
   */
  protected function getParentId($taxonomy_term) {
    if ($taxonomy_term->get('parent') && ($taxonomy_term->get('parent')->getValue()[0]['target_id'] != '0')) {
      return $taxonomy_term->get('parent')->getValue()[0]['target_id'];
    }
    else {
      return $taxonomy_term->id();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['url'];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    foreach ($this->vocabularyStorage->loadMultiple(array_keys($this->options['vids'])) as $vocabulary) {
      $dependencies[$vocabulary->getConfigDependencyKey()][] = $vocabulary->getConfigDependencyName();
    }
    return $dependencies;
  }

}
