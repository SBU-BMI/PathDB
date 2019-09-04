<?php

namespace Drupal\ldap_servers\Helper;

use Drupal\ldap_servers\Processor\TokenProcessor;

/**
 * Conversion helper to escape values correctly for LDAP filters.
 */
class ConversionHelper {

  /**
   * Escapes the given values so that they can be safely used in LDAP filters.
   *
   * Follow RFC 2254 so that control characters with an ACII code < 32 as well
   * as the characters with special meaning in LDAP filters "*", "(", ")", and
   * "\" (the backslash) are converted into the representation of a backslash
   * followed by two hex digits representing the hexadecimal value of the
   * character.
   *
   * @param array|string $values
   *   Array of values to escape.
   *
   * @static
   *
   * @return array
   *   Array of values, but escaped.
   */
  public static function escapeFilterValue($values) {
    // Parameter validation.
    $input_is_scalar = is_scalar($values);
    if ($input_is_scalar) {
      $values = [$values];
    }

    foreach ($values as $key => $val) {
      // Might be a Drupal field.
      if (isset($val->value)) {
        $isField = TRUE;
        $val = $val->getValue();
      }
      else {
        $isField = FALSE;
      }
      // Escaping of filter meta characters.
      $val = str_replace('\\', '\5c', $val);
      $val = str_replace('*', '\2a', $val);
      $val = str_replace('(', '\28', $val);
      $val = str_replace(')', '\29', $val);

      // ASCII < 32 escaping.
      $val = self::asc2hex32($val);

      if (NULL === $val) {
        // Apply escaped "null" if string is empty.
        $val = '\0';
      }
      if ($isField) {
        $values[$key]->setValue($val);
      }
      else {
        $values[$key] = $val;
      }
    }

    if (($input_is_scalar)) {
      return $values[0];
    }
    else {
      return $values;
    }
  }

  /**
   * Undoes the conversion done by {@link escape_filter_value()}.
   *
   * Converts any sequences of a backslash followed by two hex digits into the
   * corresponding character.
   *
   * @param mixed $values
   *   Array of values to escape.
   *
   * @static
   *
   * @return array
   *   Unescaped values.
   */
  public static function unescapeFilterValue($values) {
    // Parameter validation.
    $inputIsScalar = is_scalar($values);
    if (!is_array($values)) {
      $values = [$values];
    }

    foreach ($values as $key => $value) {
      // Translate hex code into ascii.
      $values[$key] = self::hex2asc($value);
    }

    if (($inputIsScalar)) {
      return $values[0];
    }
    else {
      return $values;
    }
  }

  /**
   * Escapes a DN value according to RFC 2253.
   *
   * Escapes the given VALUES according to RFC 2253 so that they can be safely
   * used in LDAP DNs. The characters ",", "+", """, "\", "<", ">", ";", "#",
   * "=" with a special meaning in RFC 2252 are preceded by a backslash. Control
   * characters with an ASCII code < 32 are represented as \hexpair. Finally all
   * leading and trailing spaces are converted to sequences of \20.
   *
   * @param array|string $values
   *   An array containing the DN values that should be escaped.
   *
   * @static
   *
   * @return array
   *   The array $values, but escaped.
   */
  public static function escapeDnValue($values) {
    // Parameter validation.
    $inputIsScalar = is_scalar($values);
    if ($inputIsScalar) {
      $values = [$values];
    }

    foreach ($values as $key => $val) {
      // Escaping of filter meta characters.
      $val = str_replace('\\', '\\\\', $val);
      $val = str_replace(',', '\,', $val);
      $val = str_replace('+', '\+', $val);
      $val = str_replace('"', '\"', $val);
      $val = str_replace('<', '\<', $val);
      $val = str_replace('>', '\>', $val);
      $val = str_replace(';', '\;', $val);
      $val = str_replace('#', '\#', $val);
      $val = str_replace('=', '\=', $val);

      // ASCII < 32 escaping.
      $val = self::asc2hex32($val);

      // Convert all leading and trailing spaces to sequences of \20.
      if (preg_match('/^(\s*)(.+?)(\s*)$/', $val, $matches)) {
        $val = $matches[2];
        for ($i = 0; $i < strlen($matches[1]); $i++) {
          $val = '\20' . $val;
        }
        for ($i = 0; $i < strlen($matches[3]); $i++) {
          $val = $val . '\20';
        }
      }

      if (NULL === $val) {
        // Apply escaped "null" if string is empty.
        $val = '\0';
      }
      $values[$key] = $val;
    }

    if (($inputIsScalar)) {
      return $values[0];
    }
    else {
      return $values;
    }
  }

  /**
   * Undoes the conversion done by escape_dn_value().
   *
   * Any escape sequence starting with a baskslash - hexpair or special
   * character - will be transformed back to the corresponding character.
   *
   * @param mixed $values
   *   Array of DN Values.
   *
   * @return array
   *   Same as $values, but unescaped
   */
  public static function unescapeDnValue($values) {
    $inputIsScalar = is_scalar($values);

    // Parameter validation.
    if (!is_array($values)) {
      $values = [$values];
    }

    foreach ($values as $key => $val) {
      // Strip slashes from special chars.
      $val = str_replace('\\\\', '\\', $val);
      $val = str_replace('\,', ',', $val);
      $val = str_replace('\+', '+', $val);
      $val = str_replace('\"', '"', $val);
      $val = str_replace('\<', '<', $val);
      $val = str_replace('\>', '>', $val);
      $val = str_replace('\;', ';', $val);
      $val = str_replace('\#', '#', $val);
      $val = str_replace('\=', '=', $val);

      // Translate hex code into ascii.
      $values[$key] = self::hex2asc($val);
    }

    if (($inputIsScalar)) {
      return $values[0];
    }
    else {
      return $values;
    }
  }

  /**
   * Converts all Hex expressions ("\HEX") to their original ASCII characters.
   *
   * @param string $string
   *   String to convert.
   *
   * @return string
   *   Converted string.
   */
  public static function hex2asc($string) {
    $string = preg_replace_callback(
      "/\\\([0-9A-Fa-f]{2})/",
      function (array $matches) {
        return chr(hexdec($matches[0]));
      },
      $string
    );
    return $string;
  }

  /**
   * Converts all ASCII chars < 32 to "\HEX".
   *
   * @param string $string
   *   String to convert.
   *
   * @return string
   *   Converted string.
   */
  public static function asc2hex32($string) {
    for ($i = 0; $i < strlen($string); $i++) {
      $char = substr($string, $i, 1);
      if (ord($char) < 32) {
        $hex = dechex(ord($char));
        if (strlen($hex) == 1) {
          $hex = '0' . $hex;
        }
        $string = str_replace($char, '\\' . $hex, $string);
      }
    }
    return $string;
  }

  /**
   * Extract token attributes.
   *
   * @param array $attribute_maps
   *   Array of attribute maps passed by reference. For example:
   *   [[<attr_name>, <ordinal>, <data_type>]].
   * @param string $text
   *   Text with tokens in it.
   *
   * @TODO: Do not pass attribute_maps by reference, merge it into an array if
   * really necessary.
   */
  public static function extractTokenAttributes(array &$attribute_maps, $text) {
    $tokens = self::findTokensNeededForTemplate($text);
    foreach ($tokens as $token) {
      $token = str_replace([
        TokenProcessor::PREFIX,
        TokenProcessor::SUFFIX,
      ], ['', ''], $token);
      $parts = explode(TokenProcessor::DELIMITER, $token);
      $ordinal = (isset($parts[1]) && $parts[1]) ? $parts[1] : 0;
      $attr_name = $parts[0];

      $parts2 = explode(TokenProcessor::MODIFIER_DELIMITER, $attr_name);
      if (count($parts2) > 1) {
        $attr_name = $parts2[0];
        $conversion = $parts2[1];
      }
      else {
        $conversion = NULL;
      }
      $attribute_maps[$attr_name] = self::setAttributeMap(@$attribute_maps[$attr_name], $conversion, [$ordinal => NULL]);
    }
  }

  /**
   * Function to convert microsoft style guids to strings.
   *
   * @param string $value
   *   Value to convert.
   *
   * @return string
   *   Converted value.
   */
  public static function convertMsguidToString($value) {
    $hex_string = bin2hex($value);
    // (MS?) GUID are displayed with first three GUID parts as "big endian"
    // Doing this so String value matches what other LDAP tool displays for AD.
    $value = strtoupper(substr($hex_string, 6, 2) . substr($hex_string, 4, 2) .
      substr($hex_string, 2, 2) . substr($hex_string, 0, 2) . '-' .
      substr($hex_string, 10, 2) . substr($hex_string, 8, 2) . '-' .
      substr($hex_string, 14, 2) . substr($hex_string, 12, 2) . '-' .
      substr($hex_string, 16, 4) . '-' . substr($hex_string, 20, 12));

    return $value;
  }

  /**
   * General binary conversion function for GUID.
   *
   * Tries to determine which approach based on length of string.
   *
   * @param string $value
   *   GUID.
   *
   * @return string
   *   Encoded string.
   */
  public static function binaryConversionToString($value) {
    if (strlen($value) == 16) {
      $value = self::convertMsguidToString($value);
    }
    else {
      $value = bin2hex($value);
    }
    return $value;
  }

  /**
   * Converts an attribute by their format.
   *
   * @param string $value
   *   Value to be converted.
   * @param string $conversion
   *   Conversion type such as base64_encode, bin2hex, msguid, md5.
   *
   * @return string
   *   Converted string.
   */
  public static function convertAttribute($value, $conversion = NULL) {

    switch ($conversion) {
      case 'base64_encode':
        $value = base64_encode($value);
        break;

      case 'bin2hex':
        $value = bin2hex($value);
        break;

      case 'msguid':
        $value = ConversionHelper::convertMsguidToString($value);
        break;

      case 'binary':
        $value = ConversionHelper::binaryConversionToString($value);
        break;

      case 'md5':
        $value = '{md5}' . base64_encode(pack('H*', md5($value)));
        break;
    }
    return $value;
  }

  /**
   * Set an attribute map.
   *
   * @param array $attribute
   *   For a given attribute in the form ['values' => [], 'data_type' => NULL]
   *   as outlined in ldap_user/README.developers.txt.
   * @param string $conversion
   *   As type of conversion to do @see ldap_servers_convert_attribute(),
   *   e.g. base64_encode, bin2hex, msguid, md5.
   * @param array $values
   *   In form [<ordinal> => <value> | NULL], where NULL indicates value is
   *   needed for provisioning or other operations.
   *
   * @return array
   *   Converted values. If nothing is passed in, create empty array in the
   *   proper structure ['values' => [0 => 'john', 1 => 'johnny']].
   */
  public static function setAttributeMap(array $attribute = NULL, $conversion = NULL, array $values = NULL) {

    $attribute = (is_array($attribute)) ? $attribute : [];
    $attribute['conversion'] = $conversion;
    if (!$values && (!isset($attribute['values']) || !is_array($attribute['values']))) {
      $attribute['values'] = [0 => NULL];
    }
    // Merge into array overwriting ordinals.
    elseif (is_array($values)) {
      foreach ($values as $ordinal => $value) {
        if ($conversion) {
          $value = self::convertAttribute($value, $conversion);
        }
        $attribute['values'][(int) $ordinal] = $value;
      }
    }
    return $attribute;
  }

  /**
   * Find the tokens needed for the template.
   *
   * @param string $template
   *   In the form of [cn]@myuniversity.edu.
   *
   * @return array
   *   Array of all tokens in the template such as array('cn').
   */
  public static function findTokensNeededForTemplate($template) {
    preg_match_all('/
    \[             # [ - pattern start
    ([^\[\]]*)  # match $type not containing whitespace : [ or ]
    \]             # ] - pattern end
    /x', $template, $matches);

    return @$matches[1];

  }

}
