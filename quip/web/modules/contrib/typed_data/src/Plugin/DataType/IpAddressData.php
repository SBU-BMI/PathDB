<?php

namespace Drupal\typed_data\Plugin\DataType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\Plugin\DataType\StringData;
use Drupal\typed_data\TypedData\Type\IpAddressInterface;

/**
 * The ip_address data type.
 */
#[DataType(
  id: "ip_address",
  label: new TranslatableMarkup("IP address"),
  constraints: ["Ip" => ["version" => "all"]]
)]
class IpAddressData extends StringData implements IpAddressInterface {

  /**
   * {@inheritdoc}
   */
  public function getIpv4Address(): ?string {
    if (isset($this->value) && filter_var($this->value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
      return $this->value;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getIpv6Address(): ?string {
    if (isset($this->value) && filter_var($this->value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
      return $this->value;
    }
    return NULL;
  }

}
