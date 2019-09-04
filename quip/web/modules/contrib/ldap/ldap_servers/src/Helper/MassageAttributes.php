<?php

namespace Drupal\ldap_servers\Helper;

/**
 * This class helps you in preparing attributes and values for usage in Drupal.
 */
class MassageAttributes {

  /**
   * Escape filter values and attribute values when querying ldap.
   *
   * @param string|array $value
   *   Value to escape.
   *
   * @return array
   *   Escaped value.
   */
  public function queryLdapAttributeValue($value) {
    if (!empty($value)) {
      $value = ConversionHelper::escapeFilterValue($value);
    }
    return $value;
  }

  /**
   * Prepare text for storing LDAP attribute values.
   *
   * Use unescaped, mixed case attribute values when storing attribute values
   * in arrays (as keys or values), databases, or object properties.
   *
   * @param string|array $value
   *   Value to store.
   *
   * @return array
   *   Escaped string.
   */
  public function storeLdapAttributeValue($value) {
    if (!empty($value)) {
      $value = ConversionHelper::escapeDnValue($value);
    }
    return $value;
  }

}
