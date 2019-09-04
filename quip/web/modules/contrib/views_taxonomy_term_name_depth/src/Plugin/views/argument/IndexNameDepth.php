<?php

namespace Drupal\views_taxonomy_term_name_depth\Plugin\views\argument;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Argument handler for taxonomy terms with depth.
 *
 * This handler is actually part of the node table and has some restrictions,
 * because it uses a subquery to find nodes with.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("taxonomy_index_name_depth")
 */
class IndexNameDepth extends ArgumentPluginBase implements ContainerFactoryPluginInterface {

  /**
   * @var EntityStorageInterface
   */
  protected $termStorage;

  /**
   * Database Service Object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityStorageInterface $termStorage, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->termStorage = $termStorage;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity.manager')
      ->getStorage('taxonomy_term'), $container->get('database'));
  }

  /**
   * @inheritdoc
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['depth'] = array('default' => 0);
    $options['vocabularies'] = array('default' => []);
    $options['break_phrase'] = array('default' => FALSE);
    $options['use_taxonomy_term_path'] = array('default' => FALSE);

    return $options;
  }

  /**
   * @inheritdoc
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['depth'] = array(
      '#type' => 'weight',
      '#title' => $this->t('Depth'),
      '#default_value' => $this->options['depth'],
      '#description' => $this->t('The depth will match nodes tagged with terms in the hierarchy. For example, if you have the term "fruit" and a child term "apple", with a depth of 1 (or higher) then filtering for the term "fruit" will get nodes that are tagged with "apple" as well as "fruit". If negative, the reverse is true; searching for "apple" will also pick up nodes tagged with "fruit" if depth is -1 (or lower).'),
    );

    // Load all the available vocabularies to create a list of options for the
    // select list.
    $vocabularies = Vocabulary::loadMultiple();
    $vocab_options = [];

    foreach($vocabularies as $machine_name => $vocabulary) {
      $vocab_options[$machine_name] = $vocabulary->label();
    }

    $form['vocabularies'] = array(
      '#type' => 'select',
      '#title' => $this->t('Vocabularies'),
      '#default_value' => $this->options['vocabularies'],
      '#options' => $vocab_options,
      '#multiple' => TRUE,
      '#description' => $this->t('Choose the vocabularies to check against. This is useful if you have terms of the same name across different vocabularies.'),
    );

    $form['break_phrase'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Allow multiple values'),
      '#description' => $this->t('If selected, users can enter multiple values in the form of 1+2+3. Due to the number of JOINs it would require, AND will be treated as OR with this filter.'),
      '#default_value' => !empty($this->options['break_phrase']),
    );

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * @inheritdoc
   *
   * Override defaultActions() to remove summary actions.
   */
  protected function defaultActions($which = NULL) {
    if ($which) {
      if (in_array($which, array('ignore', 'not found', 'empty', 'default'))) {
        return parent::defaultActions($which);
      }

      return FALSE;
    }

    $actions = parent::defaultActions();
    unset($actions['summary asc']);
    unset($actions['summary desc']);
    unset($actions['summary asc by count']);
    unset($actions['summary desc by count']);

    return $actions;
  }

  /**
   * @inheritdoc
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();

    if (!empty($this->options['break_phrase'])) {
      $break = static::breakString($this->argument);
      if ($break->value === array(-1)) {
        return FALSE;
      }

      $operator = (count($break->value) > 1) ? 'IN' : '=';
      $tids = $break->value;
    }
    else {
      $operator = "=";
      $tids = $this->argument;
    }

    // Now build the subqueries.
    if (is_string($tids)) {
      if (\Drupal::service('module_handler')->moduleExists('pathauto')) {
        $query = $this->database->select('taxonomy_term_field_data', 't')
          ->fields('t', array('tid', 'name'));

        // Filter by vocabulary ID if one or more are provided.
        if (!empty($this->options['vocabularies'])) {
          $query->condition('t.vid', $this->options['vocabularies'], 'IN');
        }

        $results = $query->execute()->fetchAll(\PDO::FETCH_OBJ);

        // Iterate results.
        foreach ($results as $row) {
          // Service container for alias cleaner.
          $alias = \Drupal::service('pathauto.alias_cleaner');
          if ($alias->cleanString($row->name) == $alias->cleanString($tids)) {
            $tids = $row->tid;
          }
        }
      }
      else {
        // Replaces "-" with space if exist.
        $argument = str_replace('-', ' ', $tids);
        $query = $this->database->select('taxonomy_term_field_data', 't')
          ->fields('t', array('tid', 'name'));

        // Filter by vocabulary ID if one or more are provided.
        if (!empty($this->options['vocabularies'])) {
          $query->condition('t.vid', $this->options['vocabularies'], 'IN');
        }

        $query->condition('t.name', $argument, '=');

        $results = $query->execute()->fetchAll(\PDO::FETCH_OBJ);

        // Iterate results
        foreach ($results as $row) {
          $tids = $row->tid;
        }
      }
    }
    $subquery = db_select('taxonomy_index', 'tn');
    $subquery->addField('tn', 'nid');
    $where = db_or()->condition('tn.tid', $tids, $operator);
    $last = "tn";

    if ($this->options['depth'] > 0) {
      $subquery->leftJoin('taxonomy_term_hierarchy', 'th', "th.tid = tn.tid");
      $last = "th";
      foreach (range(1, abs($this->options['depth'])) as $count) {
        $subquery->leftJoin('taxonomy_term_hierarchy', "th$count", "$last.parent = th$count.tid");
        $where->condition("th$count.tid", $tids, $operator);
        $last = "th$count";
      }
    }
    elseif ($this->options['depth'] < 0) {
      foreach (range(1, abs($this->options['depth'])) as $count) {
        $subquery->leftJoin('taxonomy_term_hierarchy', "th$count", "$last.tid = th$count.parent");
        $where->condition("th$count.tid", $tids, $operator);
        $last = "th$count";
      }
    }

    $subquery->condition($where);
    $this->query->addWhere(0, "$this->tableAlias.$this->realField", $subquery, 'IN');
  }

  /**
   * @inheritdoc
   */
  function title() {
    $term = $this->termStorage->load($this->argument);
    if (!empty($term)) {
      return $term->getName();
    }
    // TODO review text
    return $this->t('No name');
  }

}
