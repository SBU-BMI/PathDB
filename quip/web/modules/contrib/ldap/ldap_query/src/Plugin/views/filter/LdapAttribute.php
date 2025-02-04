<?php

declare(strict_types=1);

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
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $query->addWhere($this->options['group'], $this->realField, $this->value, $this->operator());
  }

  /**
   * {@inheritdoc}
   */
  protected function opContains($field): void {
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $query->addWhere($this->options['group'], $this->realField, "*$this->value*", '=');
  }

  /**
   * {@inheritdoc}
   */
  protected function opStartsWith($field): void {
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $query->addWhere($this->options['group'], $this->realField, "$this->value*", '=');
  }

  /**
   * {@inheritdoc}
   */
  protected function opNotStartsWith($field): void {
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $query->addWhere($this->options['group'], $this->realField, "$this->value*", '!=');
  }

  /**
   * {@inheritdoc}
   */
  protected function opEndsWith($field): void {
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $query->addWhere($this->options['group'], $this->realField, "*$this->value", '=');
  }

  /**
   * {@inheritdoc}
   */
  protected function opNotEndsWith($field): void {
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $query->addWhere($this->options['group'], $this->realField, "*$this->value", '!=');
  }

  /**
   * {@inheritdoc}
   */
  protected function opNotLike($field): void {
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $query->addWhere($this->options['group'], $this->realField, "*$this->value*", '!=');
  }

  /**
   * {@inheritdoc}
   */
  protected function opEmpty($field): void {
    if ($this->operator === 'empty') {
      /** @var \Drupal\views\Plugin\views\query\Sql $query */
      $query = $this->query;
      $query->addWhere($this->options['group'], $this->realField, '*', '!=');
    }
    else {
      /** @var \Drupal\views\Plugin\views\query\Sql $query */
      $query = $this->query;
      $query->addWhere($this->options['group'], $this->realField, '*', '=');
    }
  }

  // @todo Port numerical comparisons. Requires change of base type.
}
