<?php

declare(strict_types = 1);

namespace Drupal\ldap_servers;

use Drupal\ldap_servers\Entity\Server;
use Symfony\Component\Ldap\LdapInterface;

/**
 * Ldap Bridge to symfony/ldap.
 */
interface LdapBridgeInterface {

  /**
   * Set Server by ID.
   *
   * @param string $sid
   *   Server machine name.
   */
  public function setServerById(string $sid): void;

  /**
   * Set Server.
   *
   * @param \Drupal\ldap_servers\Entity\Server $server
   *   Server object.
   */
  public function setServer(Server $server): void;

  /**
   * Bind (authenticate) against an active LDAP database.
   *
   * @return bool
   *   Binding successful.
   */
  public function bind(): bool;

  /**
   * Get LDAP service.
   *
   * @return \Symfony\Component\Ldap\LdapInterface
   *   LDAP.
   */
  public function get(): LdapInterface;

}
