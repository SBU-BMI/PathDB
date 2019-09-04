<?php

namespace Drupal\ldap_query\Controller;

use Drupal\ldap_query\Entity\QueryEntity;
use Drupal\ldap_servers\Entity\Server;

/**
 * Controller class for LDAP queries, in assistance to the entity itself.
 */
class QueryController {

  private $results = [];
  private $qid;
  private $query;

  /**
   * Constructor.
   */
  public function __construct($id) {
    $this->qid = $id;
    $this->query = QueryEntity::load($this->qid);
  }

  /**
   * Returns the filter.
   *
   * @return string
   *   Set filter.
   */
  public function getFilter() {
    return $this->query->get('filter');
  }

  /**
   * Execute query.
   *
   * @param null|string $filter
   *   Optional parameter to override filters. Useful for Views and other
   *   queries requiring filtering.
   */
  public function execute($filter = NULL) {
    $count = 0;

    if ($this->query) {
      $ldap_server = Server::load($this->query->get('server_id'));
      $ldap_server->connectAndBindIfNotAlready();

      if ($filter == NULL) {
        $filter = $this->query->get('filter');
      }

      foreach ($this->query->getProcessedBaseDns() as $base_dn) {
        $result = $ldap_server->search(
          $base_dn,
          $filter,
          $this->query->getProcessedAttributes(),
          0,
          $this->query->get('size_limit'),
          $this->query->get('time_limit'),
          $this->query->get('dereference'),
          $this->query->get('scope')
        );

        if ($result !== FALSE && $result['count'] > 0) {
          $count = $count + $result['count'];
          $this->results = array_merge($this->results, $result);
        }
      }
      $this->results['count'] = $count;
    }
    else {
      \Drupal::logger('ldap_query')->warning('Could not load query @query', ['@query' => $this->qid]);
    }
  }

  /**
   * Return raw results.
   *
   * @return array
   *   Raw results.
   */
  public function getRawResults() {
    return $this->results;
  }

  /**
   * Return available fields.
   *
   * @return array
   *   Available fields.
   */
  public function availableFields() {
    $attributes = [];
    // We loop through all results since some users might not have fields set
    // for them and those are missing and not null.
    foreach ($this->results as $result) {
      if (is_array($result)) {
        foreach ($result as $k => $v) {
          if (is_numeric($k)) {
            $attributes[$v] = $v;
          }
        }
      }
    }
    return $attributes;
  }

  /**
   * Returns all available LDAP query entities.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Entity Queries.
   */
  public static function getAllQueries() {
    $query = \Drupal::entityQuery('ldap_query_entity');
    $ids = $query->execute();
    return QueryEntity::loadMultiple($ids);
  }

  /**
   * Returns all enabled LDAP query entities.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Entity Queries.
   */
  public static function getAllEnabledQueries() {
    $query = \Drupal::entityQuery('ldap_query_entity')
      ->condition('status', 1);
    $ids = $query->execute();
    return QueryEntity::loadMultiple($ids);
  }

}
