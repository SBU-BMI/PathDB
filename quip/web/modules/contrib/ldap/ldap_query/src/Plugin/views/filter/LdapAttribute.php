<?php

declare(strict_types = 1);

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
  public function operator(): string {
    return $this->operator === '=' ? '=' : '!=';
  }

  /**
   * {@inheritdoc}
   */
  public function opEqual($field): void {
    $this->query->addWhere($this->options['group'], $this->realField, $this->value, $this->operator());
  }

  /**
   * {@inheritdoc}
   */
  protected function opContains($field): void {
    $this->query->addWhere($this->options['group'], $this->realField, "*$this->value*", '=');
  }

  /**
   * {@inheritdoc}
   */
  protected function opStartsWith($field): void {
    $this->query->addWhere($this->options['group'], $this->realField, "$this->value*", '=');
  }

  /**
   * {@inheritdoc}
   */
  protected function opNotStartsWith($field): void {
    $this->query->addWhere($this->options['group'], $this->realField, "$this->value*", '!=');
  }

  /**
   * {@inheritdoc}
   */
  protected function opEndsWith($field): void {
    $this->query->addWhere($this->options['group'], $this->realField, "*$this->value", '=');
  }

  /**
   * {@inheritdoc}
   */
  protected function opNotEndsWith($field): void {
    $this->query->addWhere($this->options['group'], $this->realField, "*$this->value", '!=');
  }

  /**
   * {@inheritdoc}
   */
  protected function opNotLike($field): void {
    $this->query->addWhere($this->options['group'], $this->realField, "*$this->value*", '!=');
  }

  /**
   * {@inheritdoc}
   */
  protected function opEmpty($field): void {
    if ($this->operator === 'empty') {
      $this->query->addWhere($this->options['group'], $this->realField, '*', '!=');
    }
    else {
      $this->query->addWhere($this->options['group'], $this->realField, '*', '=');
    }
  }

  // @todo Port numerical comparisons. Requires change of base type.
}
