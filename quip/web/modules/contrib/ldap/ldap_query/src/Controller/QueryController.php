<?php

declare(strict_types = 1);

namespace Drupal\ldap_query\Controller;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ldap_servers\LdapBridgeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Ldap\Exception\LdapException;

/**
 * Controller class for LDAP queries, in assistance to the entity itself.
 */
class QueryController {

  /**
   * LDAP Entry.
   *
   * @var \Symfony\Component\Ldap\Entry[]
   */
  private $results = [];

  /**
   * Query ID.
   *
   * @var string
   */
  private $qid;

  /**
   * Query.
   *
   * @var \Drupal\ldap_query\Entity\QueryEntity
   */
  private $query;

  /**
   * Entity Storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * LDAP Bridge.
   *
   * @var \Drupal\ldap_servers\LdapBridge
   */
  protected $ldapBridge;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * QueryController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager.
   * @param \Drupal\ldap_servers\LdapBridgeInterface $ldap_bridge
   *   LDAP bridge.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LdapBridgeInterface $ldap_bridge,
    LoggerInterface $logger
  ) {
    $this->storage = $entity_type_manager->getStorage('ldap_query_entity');
    $this->ldapBridge = $ldap_bridge;
    $this->logger = $logger;
  }

  /**
   * Load Query.
   *
   * @param string $id
   *   ID.
   */
  public function load(string $id): void {
    $this->qid = $id;
    $this->query = $this->storage->load($this->qid);
  }

  /**
   * Returns the filter.
   *
   * @return string
   *   Set filter.
   */
  public function getFilter(): string {
    return $this->query->getFilter();
  }

  /**
   * Execute query.
   *
   * @param null|string $filter
   *   Optional parameter to override filters. Useful for Views and other
   *   queries requiring filtering.
   */
  public function execute(?string $filter = NULL): void {
    if ($this->query) {
      if ($filter === NULL) {
        $filter = $this->query->getFilter();
      }

      // @todo exception handling.
      $this->ldapBridge->setServerById($this->query->getServerId());

      if ($this->ldapBridge->bind()) {

        $base_dn_results = [];
        foreach ($this->query->getProcessedBaseDns() as $base_dn) {
          $options = [
            'filter' => $this->query->getProcessedAttributes(),
            'maxItems' => $this->query->getSizeLimit(),
            'timeout' => $this->query->getTimeLimit(),
            'deref' => $this->query->getDereference(),
            'scope' => $this->query->getScope(),
            // @todo Make this configurable on query or, ideally, server.
            'pageSize' => 1000,
          ];

          try {
            $ldap_response = $this->ldapBridge
              ->get()
              ->query($base_dn, $filter, $options)
              ->execute()
              ->toArray();
          }
          catch (LdapException $e) {
            $this->logger->warning('LDAP query exception @message', ['@message' => $e->getMessage()]);
            $ldap_response = FALSE;
          }

          if ($ldap_response) {
            $base_dn_results[] = $ldap_response;
          }
        }
        $this->results = array_merge(...$base_dn_results);
      }
    }
    else {
      $this->logger->warning('Could not load query @query', ['@query' => $this->qid]);
    }
  }

  /**
   * Return raw results.
   *
   * @return \Symfony\Component\Ldap\Entry[]
   *   Raw results.
   */
  public function getRawResults(): array {
    return $this->results;
  }

  /**
   * Return available fields.
   *
   * @return array
   *   Available fields.
   */
  public function availableFields(): array {
    $attributes = [];
    // We loop through all results since some users might not have fields set
    // for them and those are missing and not null.
    foreach ($this->results as $result) {
      foreach ($result->getAttributes() as $field_name => $field_value) {
        $attributes[$field_name] = $field_name;
      }
    }
    return $attributes;
  }

}
