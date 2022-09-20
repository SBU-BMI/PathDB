<?php

declare(strict_types = 1);

namespace Drupal\ldap_query\Plugin\views\query;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * Views query plugin for an SQL query.
 *
 * @ingroup views_query_plugins
 *
 * @ViewsQuery(
 *   id = "ldap_query",
 *   title = @Translation("LDAP Query"),
 *   help = @Translation("Query will be generated and run via LDAP.")
 * )
 */
class LdapQuery extends QueryPluginBase {
  /**
   * Collection of filter criteria.
   *
   * @var array
   */
  public $where = [];

  /**
   * Collection of sort criteria.
   *
   * @var array
   */
  public $orderby = [];

  /**
   * Maps SQL operators to LDAP operators.
   *
   * @var array
   */
  private const LDAP_FILTER_OPERATORS = ['AND' => '&', 'OR' => '|'];

  /**
   * {@inheritdoc}
   */
  public function build(ViewExecutable $view): void {
    // Store the view in the object to be able to use it later.
    $this->view = $view;

    $view->initPager();

    // Let the pager modify the query to add limits.
    $view->pager->query();

    $view->build_info['query'] = $this->query();
    $view->build_info['count_query'] = $this->query(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return 0;
  }

  /**
   * Execute the query.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   *
   * @return bool|void
   *   Nothing if query can be executed.
   */
  public function execute(ViewExecutable $view) {
    if (!isset($this->options['query_id']) || empty($this->options['query_id'])) {
      \Drupal::logger('ldap')->error('You are trying to use Views without having chosen an LDAP Query under Advanced => Query settings.');
      return FALSE;
    }
    $start = microtime(TRUE);

    // @todo Dependency Injection.
    /** @var \Drupal\ldap_query\Controller\QueryController $controller */
    $controller = \Drupal::service('ldap.query');
    $controller->load($this->options['query_id']);
    $filter = $this->buildLdapFilter($controller->getFilter());

    $controller->execute($filter);
    $results = $controller->getRawResults();
    $fields = $controller->availableFields();

    $index = 0;
    $rows = [];
    foreach ($results as $result) {
      $row = [];
      foreach ($fields as $field_key => $void) {
        if ($result->hasAttribute($field_key, FALSE)) {
          $row[$field_key] = $result->getAttribute($field_key, FALSE);
        }
      }
      $row['dn'] = [0 => $result->getDn()];
      $row['index'] = $index++;
      $rows[] = $row;
    }

    if (!empty($this->orderby) && !empty($rows)) {
      $rows = $this->sortResults($rows);
    }

    foreach ($rows as $row) {
      $view->result[] = new ResultRow($row);
    }

    // Pager.
    $totalItems = count($view->result);
    $offset = $view->pager->getCurrentPage() * $view->pager->getItemsPerPage() + $view->pager->getOffset();
    $length = NULL;
    if ($view->pager->getItemsPerPage() > 0) {
      // A Views ExposedInput for items_for_page will return a string value
      // via a querystring parameter.
      $length = (int) $view->pager->getItemsPerPage();
    }

    if ($offset > 0 || $length > 0) {
      $view->result = array_splice($view->result, $offset, $length);
    }
    $view->pager->postExecute($view->result);
    $view->pager->total_items = $totalItems;
    $view->pager->updatePageInfo();
    $view->total_rows = $view->pager->getTotalItems();
    // Timing information.
    $view->execute_time = microtime(TRUE) - $start;
  }

  /**
   * Sort the results.
   *
   * @param array $rows
   *   Results to operate on.
   *
   * @return array
   *   Result data.
   *
   * @todo Should be private, public for easier testing.
   */
  public function sortResults(array $rows): array {
    $sorts = $this->orderby;
    foreach ($sorts as $sort) {
      foreach ($rows as $key => $row) {
        $rows[$key]['sort_' . $sort['field']] = $row[$sort['field']][0] ?? '';
      }
    }

    $multisortParameters = [];
    foreach ($sorts as $sort) {
      $multisortParameters[] = array_column($rows, 'sort_' . $sort['field']);
      $multisortParameters[] = mb_strtoupper($sort['direction']) === 'ASC' ? SORT_ASC : SORT_DESC;
    }
    $multisortParameters[] = &$rows;
    array_multisort(...$multisortParameters);

    return $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function ensureTable($table, $relationship = NULL): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function addField($table, $field, $alias = '', $params = []) {
    return $field;
  }

  /**
   * {@inheritdoc}
   */
  public function addOrderBy($table, $field, $order, $alias = '', $params = []): void {
    $this->orderby[] = [
      'field' => $field,
      'direction' => $order,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['query_id'] = [
      'default' => NULL,
    ];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);

    $queries = \Drupal::EntityQuery('ldap_query_entity')
      ->condition('status', 1)
      ->execute();

    $form['query_id'] = [
      '#type' => 'select',
      '#options' => $queries,
      '#title' => $this->t('Ldap Query'),
      '#default_value' => $this->options['query_id'],
      '#description' => $this->t('The LDAP query you want Views to use.'),
      '#required' => TRUE,
    ];
  }

  /**
   * Add a simple WHERE clause to the query.
   *
   * @param mixed $group
   *   The WHERE group to add these to; groups are used to create AND/OR
   *   sections. Groups cannot be nested. Use 0 as the default group.
   *   If the group does not yet exist it will be created as an AND group.
   * @param mixed $field
   *   The name of the field to check.
   * @param mixed $value
   *   The value to test the field against. In most cases, this is a scalar. For
   *   more complex options, it is an array. The meaning of each element in the
   *   array is dependent on the $operator.
   * @param mixed $operator
   *   The comparison operator, such as =, <, or >=. It also accepts more
   *   complex options such as IN, LIKE, LIKE BINARY, or BETWEEN. Defaults to =.
   *   If $field is a string you have to use 'formula' here.
   */
  public function addWhere($group, $field, $value = NULL, $operator = NULL): void {
    // Ensure all variants of 0 are actually 0. Thus '', 0 and NULL are all
    // the default group.
    if (empty($group)) {
      $group = 0;
    }
    // Check for a group.
    if (!isset($this->where[$group])) {
      $this->setWhereGroup('AND', $group);
    }
    if (!empty($operator) && $operator !== 'LIKE') {
      $this->where[$group]['conditions'][] = [
        'field' => $field,
        'value' => $value,
        'operator' => $operator,
      ];
    }
  }

  /**
   * Compiles all conditions into a set of LDAP requirements.
   *
   * @return string|null
   *   Condition string.
   */
  public function buildConditions(): ?string {

    $groups = [];
    foreach ($this->where as $group) {
      if (!empty($group['conditions'])) {
        $conditions = '';
        foreach ($group['conditions'] as $clause) {
          $conditions .= $this->translateCondition($clause['field'], $clause['value'], $clause['operator']);
        }
        if (count($group['conditions']) > 1) {
          $groups[] = '(' . self::LDAP_FILTER_OPERATORS[$group['type']] . $conditions . ')';
        }
        else {
          $groups[] = $conditions;
        }
      }
    }

    if (count($groups) > 1) {
      $output = '(' . self::LDAP_FILTER_OPERATORS[$this->groupOperator] . implode('', $groups) . ')';
    }
    else {
      $output = array_pop($groups);
    }

    return $output;
  }

  /**
   * Collates Views arguments and filters for a modified query.
   *
   * @param string $standardFilter
   *   The filter in LDAP query which gets overwritten.
   *
   * @return string
   *   Combined string.
   */
  private function buildLdapFilter(string $standardFilter): string {
    $searchFilter = $this->buildConditions();
    if (!empty($searchFilter)) {
      $finalFilter = '(&' . $standardFilter . $searchFilter . ')';
    }
    else {
      $finalFilter = $standardFilter;
    }
    return $finalFilter;
  }

  /**
   * Produces a filter condition and adds optional negation.
   *
   * @param string $field
   *   LDAP attribute name.
   * @param string $value
   *   Field value.
   * @param string $operator
   *   Negation operator.
   *
   * @return string
   *   LDAP filter such as (cn=Example).
   */
  private function translateCondition(string $field, string $value, string $operator): string {
    if (mb_strpos($operator, '!') === 0) {
      $condition = sprintf('(!(%s=%s))', $field, Html::escape($value));
    }
    else {
      $condition = sprintf('(%s=%s)', $field, Html::escape($value));
    }
    return $condition;
  }

  /**
   * Let modules modify the query just prior to finalizing it.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   View.
   */
  public function alter(ViewExecutable $view): void {
    \Drupal::moduleHandler()->invokeAll('views_query_alter', [$view, $this]);
  }

}
