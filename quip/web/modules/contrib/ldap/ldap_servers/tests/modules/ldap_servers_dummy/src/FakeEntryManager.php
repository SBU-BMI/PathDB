<?php

declare(strict_types=1);

namespace Drupal\ldap_servers_dummy;

use Symfony\Component\Ldap\Adapter\EntryManagerInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\LdapException;

/**
 * Records LDAP entry manager operations for tests.
 */
class FakeEntryManager implements EntryManagerInterface {

  /**
   * Added entries.
   *
   * @var \Symfony\Component\Ldap\Entry[]
   */
  public array $added = [];

  /**
   * Updated entries.
   *
   * @var \Symfony\Component\Ldap\Entry[]
   */
  public array $updated = [];

  /**
   * Removed DNs.
   *
   * @var string[]
   */
  public array $removed = [];

  /**
   * Attribute values added, keyed by DN.
   *
   * @var array
   */
  public array $addedAttributeValues = [];

  /**
   * Attribute values removed, keyed by DN.
   *
   * @var array
   */
  public array $removedAttributeValues = [];

  /**
   * Whether the next operation should throw.
   *
   * @var bool
   */
  public bool $throw = FALSE;

  /**
   * {@inheritdoc}
   */
  public function add(Entry $entry) {
    $this->maybeThrow();
    $this->added[] = $entry;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function update(Entry $entry) {
    $this->maybeThrow();
    $this->updated[] = $entry;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function rename(Entry $entry, string $newRdn, bool $removeOldRdn = TRUE) {
    $this->maybeThrow();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function move(Entry $entry, string $newParent) {
    $this->maybeThrow();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function remove(Entry $entry) {
    $this->maybeThrow();
    $this->removed[] = $entry->getDn();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addAttributeValues(Entry $entry, string $attribute, array $values): void {
    $this->maybeThrow();
    $this->addedAttributeValues[] = [
      'dn' => $entry->getDn(),
      'attribute' => $attribute,
      'values' => $values,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function removeAttributeValues(Entry $entry, string $attribute, array $values): void {
    $this->maybeThrow();
    $this->removedAttributeValues[] = [
      'dn' => $entry->getDn(),
      'attribute' => $attribute,
      'values' => $values,
    ];
  }

  /**
   * Throws when tests request a failure.
   */
  private function maybeThrow(): void {
    if ($this->throw) {
      throw new LdapException('fake ldap error');
    }
  }

}
