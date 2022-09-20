<?php

declare(strict_types = 1);


namespace Drupal\ldap_servers_dummy;

use Symfony\Component\Ldap\Adapter\CollectionInterface;

/**
 * Simulate the collection response.
 */
class FakeCollection implements CollectionInterface {

  /**
   * Result.
   *
   * @var \ArrayObject
   */
  protected $result;

  /**
   * New FakeCollection.
   *
   * @param \Symfony\Component\Ldap\Entry[] $result
   *   Entries.
   */
  public function __construct(array $result) {
    $this->result = new \ArrayObject($result);
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return (array) $this->result;
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator() {
    return $this->result->getIterator();
  }

  /**
   * {@inheritdoc}
   */
  public function offsetExists($offset): bool {
    return $this->result->offsetExists($offset);
  }

  /**
   * {@inheritdoc}
   */
  public function offsetGet($offset) {
    return $this->result->offsetGet($offset);
  }

  /**
   * {@inheritdoc}
   */
  public function offsetSet($offset, $value): void {
    $this->result->offsetSet($offset, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function offsetUnset($offset): void {
    $this->result->offsetUnset($offset);
  }

  /**
   * {@inheritdoc}
   */
  public function count(): int {
    return $this->result->count();
  }

}
