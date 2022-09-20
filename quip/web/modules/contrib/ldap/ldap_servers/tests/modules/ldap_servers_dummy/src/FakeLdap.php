<?php

declare(strict_types = 1);

namespace Drupal\ldap_servers_dummy;

use Symfony\Component\Ldap\Adapter\EntryManagerInterface;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\LdapInterface;

/**
 * Fake server to simulate querying with symfony/ldap.
 *
 * Can be used in tests, requires overloading the LdapBridge class.
 */
class FakeLdap implements LdapInterface {

  /**
   * Let binding fail.
   *
   * @var bool
   */
  protected $bindException = FALSE;

  /**
   * List of query responses keyed by query.
   *
   * @var array
   */
  protected $queryResult;

  /**
   * Escape response.
   *
   * @var string
   */
  protected $escapeResponse;

  /**
   * Entry Manager.
   *
   * @var \Symfony\Component\Ldap\Adapter\EntryManagerInterface
   */
  protected $entryManagerResponse;

  /**
   * {@inheritdoc}
   */
  public function bind(string $dn = NULL, string $password = NULL): void {
    if ($this->bindException) {
      throw new ConnectionException('Failed connection');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query($dn, $query, array $options = []) {
    $response = new FakeQuery();
    $response->setResult($this->queryResult[$query] ?? new FakeCollection([]));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntryManager(): EntryManagerInterface {
    return $this->entryManagerResponse;
  }

  /**
   * {@inheritdoc}
   */
  public function escape($subject, $ignore = '', $flags = 0): string {
    return $this->escapeResponse;
  }

  /**
   * Set bind exception.
   *
   * @param bool $bindException
   *   Bind exception.
   */
  public function setBindException(bool $bindException): void {
    $this->bindException = $bindException;
  }

  /**
   * Set the query result.
   *
   * @param array $queryResult
   *   Query result.
   */
  public function setQueryResult(array $queryResult): void {
    $this->queryResult = $queryResult;
  }

  /**
   * Set the escape response.
   *
   * @param string $escapeResponse
   *   Response.
   */
  public function setEscapeResponse(string $escapeResponse): void {
    $this->escapeResponse = $escapeResponse;
  }

  /**
   * Set the entry manager response.
   *
   * @param \Symfony\Component\Ldap\Adapter\EntryManagerInterface $entryManagerResponse
   *   Entry Manager.
   */
  public function setEntryManagerResponse(EntryManagerInterface $entryManagerResponse): void {
    $this->entryManagerResponse = $entryManagerResponse;
  }

}
