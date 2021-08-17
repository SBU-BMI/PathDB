<?php

declare(strict_types = 1);

namespace Drupal\ldap_query\Plugin\views\argument;

use Drupal\ldap_query\Plugin\views\VariableAttributeCustomization;

/**
 * Let's the user choose which LDAP attribute to use from the query.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("ldap_variable_attribute")
 */
class LdapVariableAttribute extends LdapAttribute {

  use VariableAttributeCustomization;

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE): void {
    $this->ensureMyTable();
    $this->realField = $this->options['attribute_name'];
    $this->query->addWhere(0, $this->realField, $this->argument, '=');
  }

}
