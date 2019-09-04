<?php

namespace Drupal\ldap_query\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\ldap_query\QueryEntityInterface;

/**
 * Defines the LDAP Queries entity.
 *
 * @ConfigEntityType(
 *   id = "ldap_query_entity",
 *   label = @Translation("LDAP Queries"),
 *   handlers = {
 *     "list_builder" = "Drupal\ldap_query\QueryEntityListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ldap_query\Form\QueryEntityForm",
 *       "edit" = "Drupal\ldap_query\Form\QueryEntityForm",
 *       "delete" = "Drupal\ldap_query\Form\QueryEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\ldap_query\QueryEntityHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "ldap_query_entity",
 *   admin_permission = "administer ldap",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/config/people/ldap/query/add",
 *     "edit-form" = "/admin/config/people/ldap/query/{ldap_query_entity}/edit",
 *     "delete-form" = "/admin/config/people/ldap/query/{ldap_query_entity}/delete",
 *     "collection" = "/admin/config/people/ldap/query",
 *     "test" = "/admin/config/people/ldap/query/{ldap_query_entity}/test"
 *   }
 * )
 */
class QueryEntity extends ConfigEntityBase implements QueryEntityInterface {
  /**
   * The LDAP Queries ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The LDAP Queries label.
   *
   * @var string
   */
  protected $label;

  protected $status;
  protected $base_dn;
  protected $filter;
  protected $attributes;
  protected $size_limit;
  protected $time_limit;
  protected $dereference;
  protected $scope;

  /**
   * Returns all base DN.
   *
   * @return array
   *   Processed base DN
   */
  public function getProcessedBaseDns() {
    return explode("\r\n", $this->base_dn);
  }

  /**
   * Returns all processed attributes.
   *
   * @return array
   *   Processed attributes.
   */
  public function getProcessedAttributes() {
    if (!empty($this->attributes)) {
      return explode(',', $this->attributes);
    }
    else {
      return [];
    }
  }

}
