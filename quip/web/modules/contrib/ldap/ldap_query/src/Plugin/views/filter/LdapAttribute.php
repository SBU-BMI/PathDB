<?php

namespace Drupal\ldap_query\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\StringFilter;

/**
 * LDAP Attribute Views Filter.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("ldap_attribute")
 */
class LdapAttribute extends StringFilter {

  /**
   * {@inheritdoc}
   */
  public function operator() {
    return $this->operator == '=' ? '=' : '!=';
  }

  /**
   * {@inheritdoc}
   */
  public function opEqual($field) {
    $this->query->addWhere($this->options['group'], $this->realField, $this->value, $this->operator());
  }

  /**
   * {@inheritdoc}
   */
  protected function opContains($field) {
    $this->query->addWhere($this->options['group'], $this->realField, "*$this->value*", '=');
  }

  /**
   * {@inheritdoc}
   */
  protected function opStartsWith($field) {
    $this->query->addWhere($this->options['group'], $this->realField, "$this->value*", '=');
  }

  /**
   * {@inheritdoc}
   */
  protected function opNotStartsWith($field) {
    $this->query->addWhere($this->options['group'], $this->realField, "$this->value*", '!=');
  }

  /**
   * {@inheritdoc}
   */
  protected function opEndsWith($field) {
    $this->query->addWhere($this->options['group'], $this->realField, "*$this->value", '=');
  }

  /**
   * {@inheritdoc}
   */
  protected function opNotEndsWith($field) {
    $this->query->addWhere($this->options['group'], $this->realField, "*$this->value", '!=');
  }

  /**
   * {@inheritdoc}
   */
  protected function opNotLike($field) {
    $this->query->addWhere($this->options['group'], $this->realField, "*$this->value*", '!=');
  }

  /**
   * {@inheritdoc}
   */
  protected function opEmpty($field) {
    if ($this->operator == 'empty') {
      $this->query->addWhere($this->options['group'], $this->realField, '*', '!=');
    }
    else {
      $this->query->addWhere($this->options['group'], $this->realField, '*', '=');
    }
  }

  // TODO: Port numerical comparisons. Requires change of base type.
}
