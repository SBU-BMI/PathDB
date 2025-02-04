<?php

declare(strict_types=1);

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
  #[\ReturnTypeWillChange]
  public function toArray(): array {
    return (array) $this->result;
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
  public function getIterator() {
    return $this->result->getIterator();
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
  public function offsetExists($offset): bool {
    return $this->result->offsetExists($offset);
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
  public function offsetGet($offset) {
    return $this->result->offsetGet($offset);
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
  public function offsetSet($offset, $value): void {
    $this->result->offsetSet($offset, $value);
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
  public function offsetUnset($offset): void {
    $this->result->offsetUnset($offset);
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
  public function count(): int {
    return $this->result->count();
  }

}
