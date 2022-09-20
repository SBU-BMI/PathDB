<?php

declare(strict_types = 1);


namespace Drupal\ldap_servers_dummy;

use Symfony\Component\Ldap\Adapter\QueryInterface;

/**
 * Simulate the query response.
 */
class FakeQuery implements QueryInterface {

  /**
   * Result.
   *
   * @var mixed
   */
  protected $result;

  /**
   * Executes a query and returns the list of Ldap entries.
   *
   * @return \Symfony\Component\Ldap\Adapter\CollectionInterface|\Symfony\Component\Ldap\Entry[]
   *   Record.
   *
   * @throws \Symfony\Component\Ldap\Exception\NotBoundException
   * @throws \Symfony\Component\Ldap\Exception\LdapException
   */
  public function execute() {
    return $this->result;
  }

  /**
   * Set result.
   *
   * @param mixed $result
   *   Result.
   */
  public function setResult($result): void {
    $this->result = $result;
  }

}
