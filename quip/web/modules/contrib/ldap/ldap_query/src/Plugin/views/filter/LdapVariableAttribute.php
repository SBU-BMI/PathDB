<?php

namespace Drupal\ldap_query\Plugin\views\filter;

use Drupal\ldap_query\Plugin\views\VariableAttributeCustomization;

/**
 * Let's the user choose which LDAP attribute to use from the query.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("ldap_variable_attribute")
 */
class LdapVariableAttribute extends LdapAttribute {

  use VariableAttributeCustomization;

  /**
   * Adjust the query.
   */
  public function query() {
    $this->ensureMyTable();
    $this->realField = $this->options['attribute_name'];
    $field = "$this->tableAlias.$this->realField";

    $info = $this->operators();
    if (!empty($info[$this->operator]['method'])) {
      $this->{$info[$this->operator]['method']}($field);
    }
  }

}
