<?php

declare(strict_types = 1);

namespace Drupal\ldap_query\Plugin\views\field;

use Drupal\ldap_query\Plugin\views\VariableAttributeCustomization;

/**
 * Let's the user choose which LDAP attribute to use from the query.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("ldap_variable_attribute")
 */
class LdapVariableAttribute extends LdapAttribute {

  use VariableAttributeCustomization;

}
