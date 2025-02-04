<?php

namespace Drupal\typed_data\TypedData\Type;

use Drupal\Core\TypedData\Type\StringInterface;

/**
 * Interface for ip_address data.
 *
 * @ingroup typed_data
 */
interface IpAddressInterface extends StringInterface {

  /**
   * Returns a string containing the IPv4 address in "dotted quad" format.
   *
   * @return string|null
   *   The IPv4 address or NULL.
   */
  public function getIpv4Address(): ?string;

  /**
   * Returns a string containing the IPv6 address in "colon hexadecimal" format.
   *
   * @return string|null
   *   The IPv6 address or NULL.
   */
  public function getIpv6Address(): ?string;

}
