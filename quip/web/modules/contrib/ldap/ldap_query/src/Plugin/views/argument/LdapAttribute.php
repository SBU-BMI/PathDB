<?php

declare(strict_types = 1);

namespace Drupal\ldap_query\Plugin\views\argument;

use Drupal\views\Plugin\views\argument\ArgumentPluginBase;

/**
 * LDAP Attribute Views Argument.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("ldap_attribute")
 */
class LdapAttribute extends ArgumentPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE): void {
    $this->ensureMyTable();
    $this->query->addWhere(0, $this->realField, $this->argument, '=');
  }

}
