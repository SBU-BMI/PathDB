<?php

namespace Drupal\ldap_query\Plugin\views\argument;

use Drupal\views\Plugin\views\argument\Standard;

/**
 * LDAP Attribute Views Argument.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("ldap_attribute")
 */
class LdapAttribute extends Standard {

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    parent::query($group_by);
    $this->query->addWhere(0, $this->realField, $this->argument, '=');
  }

}
