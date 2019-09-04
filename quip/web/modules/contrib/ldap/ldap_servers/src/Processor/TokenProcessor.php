<?php

namespace Drupal\ldap_servers\Processor;

use Drupal\Core\File\FileSystem;
use Drupal\Component\Utility\Unicode;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers\Helper\ConversionHelper;
use Drupal\ldap_servers\Helper\CredentialsStorage;
use Drupal\ldap_servers\Logger\LdapDetailLog;
use Drupal\user\UserInterface;

/**
 * Helper to manage LDAP tokens and process their content.
 */
class TokenProcessor {

  const PREFIX = '[';
  const SUFFIX = ']';
  const DELIMITER = ':';
  const MODIFIER_DELIMITER = ';';

  protected $detailLog;
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  public function __construct(LdapDetailLog $ldap_detail_log, FileSystem $file_system) {
    $this->detailLog = $ldap_detail_log;
    $this->fileSystem = $file_system;
  }

  /**
   * Replace a token.
   *
   * @param array|UserInterface $resource
   *   The resource to act upon.
   * @param string $text
   *   The text such as "[dn]", "[cn]@my.org", "[displayName] [sn]",
   *   "Drupal Provisioned".
   * @param string $resource_type
   *   What kind of type to replace.
   *
   * @return string
   *   The text with tokens replaced or NULL if replacement not available.
   */
  public function tokenReplace($resource, $text, $resource_type = 'ldap_entry') {
    // Desired tokens are of form "cn","mail", etc.
    $desired_tokens = ConversionHelper::findTokensNeededForTemplate($text);

    if (empty($desired_tokens)) {
      // If no tokens exist in text, return text itself.  It is literal value.
      return $text;
    }

    $tokens = [];
    switch ($resource_type) {
      case 'ldap_entry':
        $tokens = $this->tokenizeLdapEntry($resource, $desired_tokens, self::PREFIX, self::SUFFIX);
        break;

      case 'user_account':
        $tokens = $this->tokenizeUserAccount($resource, $desired_tokens, self::PREFIX, self::SUFFIX);
        break;
    }

    // Add lowercase tokens to avoid case sensitivity.
    foreach ($tokens as $attribute => $value) {
      $tokens[mb_strtolower($attribute)] = $value;
    }

    // Array of attributes (sn, givenname, etc)
    $attributes = array_keys($tokens);
    // Array of attribute values (Lincoln, Abe, etc)
    $values = array_values($tokens);
    // TODO: This comparison is likely not ideal:
    // The sub-functions redundantly lowercase replacements in addition to the
    // source formatting. Otherwise comparison would fail here in
    // case-insensitive requests. Ideally, a reimplementation would resolve this
    // redundant and inconsistent approach with a clearer API.
    $result = str_replace($attributes, $values, $text);

    // Strip out any unreplace tokens.
    $result = preg_replace('/^\[.*\]$/', '', $result);
    // Return NULL if $result is empty, else $result.
    if ($result == '') {
      return NULL;
    }
    else {
      return $result;
    }
  }

  /**
   * Turn an LDAP entry into a token array suitable for the t() function.
   *
   * @param array $ldap_entry
   *   The LDAP entry.
   * @param array $token_keys
   *   Either an array of key names such as ['cn', 'dn'] or an empty
   *   array for all items.
   * @param string $pre
   *   Prefix token prefix such as !,%,[.
   * @param string $post
   *   Suffix token suffix such as ].
   *
   * @return array
   *   Token array suitable for t() functions of with lowercase keys as
   *   exemplified below. The LDAP entry should be in form of single entry
   *   returned from ldap_search() function. For example:
   *   'dn' => 'cn=jdoe,ou=campus accounts,dc=ad,dc=myuniversity,dc=edu',
   *   'mail' => array( 0 => 'jdoe@myuniversity.edu', 'count' => 1),
   *   'sAMAccountName' => array( 0 => 'jdoe', 'count' => 1),
   *
   *   Should return tokens such as:
   *   From dn attribute:
   *     [cn] = jdoe
   *     [cn:0] = jdoe
   *     [cn:last] => jdoe
   *     [cn:reverse:0] = jdoe
   *     [ou] = campus accounts
   *     [ou:0] = campus accounts
   *     [ou:last] = toledo campus
   *     [ou:reverse:0] = toledo campus
   *     [ou:reverse:1] = campus accounts
   *     [dc] = ad
   *     [dc:0] = ad
   *     [dc:1] = myuniversity
   *     [dc:2] = edu
   *     [dc:last] = edu
   *     [dc:reverse:0] = edu
   *     [dc:reverse:1] = myuniversity
   *     [dc:reverse:2] = ad
   *   From other attributes:
   *     [mail] = jdoe@myuniversity.edu
   *     [mail:0] = jdoe@myuniversity.edu
   *     [mail:last] = jdoe@myuniversity.edu
   *     [samaccountname] = jdoe
   *     [samaccountname:0] = jdoe
   *     [samaccountname:last] = jdoe
   *     [guid:0;base64_encode] = apply base64_encode() function to value
   *     [guid:0;bin2hex] = apply bin2hex() function to value
   *     [guid:0;msguid] = apply convertMsguidToString() function to value
   */
  public function tokenizeLdapEntry(array $ldap_entry, array $token_keys, $pre = self::PREFIX, $post = self::SUFFIX) {
    if (!is_array($ldap_entry)) {
      $this->detailLog->log(
        'Skipped tokenization of LDAP entry because no LDAP entry provided when called from %calling_function.', [
          '%calling_function' => function_exists('debug_backtrace') ? debug_backtrace()[1]['function'] : 'undefined',
        ]
      );
      // Empty array.
      return [];
    }
    list($ldap_entry, $tokens) = $this->compileLdapTokenEntries($ldap_entry, $token_keys, $pre, $post);

    // Include the dn. It will not be handled correctly by previous loops.
    $tokens[$pre . 'dn' . $post] = $ldap_entry['dn'];
    return $tokens;
  }

  /**
   * Tokenize a user account.
   *
   * @param \Drupal\user\UserInterface $account
   *   The Drupal user account.
   * @param array $token_keys
   *   Keys for tokens:
   *     'all' signifies return
   *     all token/value pairs available; otherwise array lists
   *     token keys (e.g. property.name ...NOT [property.name])
   * @param string $pre
   *   Prefix of token.
   * @param string $post
   *   Suffix of token.
   *
   * @return array
   *   Should return token/value pairs in array such as 'status' => 1,
   *   'uid' => 17.
   */
  public function tokenizeUserAccount(UserInterface $account, array $token_keys = [], $pre = self::PREFIX, $post = self::SUFFIX) {
    if (empty($token_keys)) {
      $token_keys = $this->discoverUserAttributes($account);
    }

    $tokens = [];
    foreach ($token_keys as $token_key) {
      $tokens = array_merge($tokens, $this->fetchSingleToken($account, $pre, $post, $token_key));
    }
    return $tokens;
  }

  /**
   * Discover user attributes from user.
   *
   * @param \Drupal\user\UserInterface $account
   *   User account.
   *
   * @return array
   *   User attributes.
   */
  private function discoverUserAttributes(UserInterface $account) {
    $token_keys = [];
    // Add lowercase keyed entries to LDAP array.
    $userData = $account->toArray();
    foreach ($userData as $propertyName => $propertyData) {
      if (isset($propertyData[0], $propertyData[0]['value']) && is_scalar($propertyData[0]['value'])) {
        if (substr($propertyName, 0, strlen('field')) === 'field') {
          $token_keys[] = 'field.' . mb_strtolower($propertyName);
        }
        else {
          $token_keys[] = 'property.' . mb_strtolower($propertyName);
        }
      }
    }
    $token_keys[] = 'password.random';
    $token_keys[] = 'password.user-random';
    $token_keys[] = 'password.user-only';
    return $token_keys;
  }

  /**
   * Fetch a single token.
   *
   * @param \Drupal\user\UserInterface $account
   *   LDAP entry.
   * @param string $pre
   *   Preamble.
   * @param string $post
   *   Postamble.
   * @param string $token
   *   Token key.
   *
   * @return array
   *   Tokens.
   */
  private function fetchSingleToken(UserInterface $account, $pre, $post, $token) {
    // Trailing period to allow for empty value.
    list($attribute_type, $attribute_name, $attribute_conversion) = explode('.', $token . '.');
    $value = FALSE;
    $tokens = [];

    switch ($attribute_type) {
      case 'field':
      case 'property':
        $value = $this->fetchRegularFieldToken($account, $attribute_name);
        break;

      case 'password':
        $value = $this->fetchPasswordToken($attribute_name);
        if (empty($value)) {
          // Do not evaluate empty passwords, to avoid overwriting them.
          return [NULL, NULL];
        }
        break;
    }

    if ($attribute_conversion == 'to-md5') {
      $value = md5($value);
    }
    elseif ($attribute_conversion == 'to-lowercase') {
      $value = mb_strtolower($value);
    }

    $tokens[$pre . $token . $post] = $value;
    // We are redundantly setting the lowercase value here for consistency with
    // parent function.
    if ($token != mb_strtolower($token)) {
      $tokens[$pre . mb_strtolower($token) . $post] = $value;
    }

    return $tokens;
  }

  /**
   * Fetch the password token.
   *
   * @param string $attribute_name
   *   Field variant.
   *
   * @return string
   *   Password.
   */
  private function fetchPasswordToken($attribute_name) {
    $value = '';
    switch ($attribute_name) {

      case 'user':
      case 'user-only':
        $value = CredentialsStorage::getPassword();
        break;

      case 'user-random':
        $pwd = CredentialsStorage::getPassword();
        $value = ($pwd) ? $pwd : user_password();
        break;

      case 'random':
        $value = user_password();
        break;

    }
    return $value;
  }

  /**
   * Fetch regular field token.
   *
   * @param \Drupal\user\UserInterface $account
   *   User.
   * @param string $attribute_name
   *   Field name.
   *
   * @return string
   *   Field data.
   */
  private function fetchRegularFieldToken(UserInterface $account, $attribute_name) {
    $value = '';
    if (is_scalar($account->get($attribute_name)->value)) {
      $value = $account->get($attribute_name)->value;
    }
    elseif (!empty($account->get($attribute_name)->getValue())) {
      $file_reference = $account->get($attribute_name)->getValue();
      if (isset($file_reference[0]['target_id'])) {
        $file = file_load($file_reference[0]['target_id']);
        if ($file) {
          $value = file_get_contents($this->fileSystem->realpath($file->getFileUri()));
        }
      }
    }
    return $value;
  }

  /**
   * Compile LDAP token entries.
   *
   * @param array $ldap_entry
   *   LDAP entry.
   * @param array $token_keys
   *   Token keys.
   * @param string $pre
   *   Preamble.
   * @param string $post
   *   Postamble.
   *
   * @return array
   *   Tokens.
   */
  private function compileLdapTokenEntries(array $ldap_entry, array $token_keys, $pre, $post) {
    $tokens = [];
    // Add lowercase keyed entries to LDAP array.
    foreach ($ldap_entry as $key => $values) {
      $ldap_entry[mb_strtolower($key)] = $values;
    }

    $tokens = array_merge($tokens, $this->ldapTokenizationProcessDnParts($ldap_entry, $pre, $post));

    if (empty($token_keys)) {
      // Get all attributes.
      $token_keys = array_keys($ldap_entry);
      $token_keys = array_filter($token_keys, "is_string");
      foreach ($token_keys as $attribute_name) {
        $tokens = array_merge($tokens, $this->processSingleLdapEntryToken($ldap_entry, $pre, $post, $attribute_name));
      }
    }
    else {
      foreach ($token_keys as $full_token_key) {
        $tokens = array_merge($tokens, $this->processSingleLdapTokenKey($ldap_entry, $pre, $post, $full_token_key));
      }
    }
    return [$ldap_entry, $tokens];
  }

  /**
   * Tokenization of DN parts.
   *
   * @param array $ldap_entry
   *   LDAP entry.
   * @param string $pre
   *   Preamble.
   * @param string $post
   *   Postamble.
   *
   * @return array
   *   Tokens.
   */
  private function ldapTokenizationProcessDnParts(array $ldap_entry, $pre, $post) {
    $tokens = [];
    // 1. tokenize dn
    // escapes attribute values, need to be unescaped later.
    $dn_parts = Server::ldapExplodeDn($ldap_entry['dn'], 0);
    unset($dn_parts['count']);
    $parts_count = [];
    $parts_last_value = [];
    foreach ($dn_parts as $pair) {
      list($attribute_name, $attribute_value) = explode('=', $pair);
      $attribute_value = ConversionHelper::unescapeDnValue($attribute_value);
      if (!Unicode::validateUtf8($attribute_value)) {
        $this->detailLog->log('Skipped tokenization of attribute %attr_name because the value is not valid UTF-8 string.', [
          '%attr_name' => $attribute_name,
        ]);
        continue;
      }

      if (!isset($parts_count[$attribute_name])) {
        // First and general entry.
        $tokens[$pre . mb_strtolower($attribute_name) . $post] = $attribute_value;
        $parts_count[$attribute_name] = 0;
      }
      $tokens[$pre . mb_strtolower($attribute_name) . self::DELIMITER . (int) $parts_count[$attribute_name] . $post] = $attribute_value;

      $parts_last_value[$attribute_name] = $attribute_value;
      $parts_count[$attribute_name]++;
    }

    // Add DN parts in reverse order to reflect the hierarchy for CN, OU, DC.
    foreach ($parts_count as $attribute_name => $count) {
      $part = mb_strtolower($attribute_name);
      for ($i = 0; $i < $count; $i++) {
        $reversePosition = $count - $i - 1;
        $tokens[$pre . $part . self::DELIMITER . 'reverse' . self::DELIMITER . $reversePosition . $post] = $tokens[$pre . $part . self::DELIMITER . $i . $post];
      }
    }

    foreach ($parts_count as $attribute_name => $count) {
      $tokens[$pre . mb_strtolower($attribute_name) . self::DELIMITER . 'last' . $post] = $parts_last_value[$attribute_name];
    }
    return $tokens;
  }

  /**
   * Process a single ldap_entry token.
   *
   * @param array $ldap_entry
   *   LDAP entry.
   * @param string $pre
   *   Preamble.
   * @param string $post
   *   Postamble.
   * @param string $attribute_name
   *   Actual data.
   *
   * @return array
   *   Tokens.
   */
  private function processSingleLdapEntryToken(array $ldap_entry, $pre, $post, $attribute_name) {
    $tokens = [];

    $attribute_value = $ldap_entry[$attribute_name];
    if (is_array($attribute_value) && is_scalar($attribute_value[0]) && $attribute_value['count'] == 1) {
      // Only one entry, example output: ['cn', 'cn:0', 'cn:last'].
      $tokens[$pre . mb_strtolower($attribute_name) . $post] = $attribute_value[0];
      $tokens[$pre . mb_strtolower($attribute_name) . self::DELIMITER . '0' . $post] = $attribute_value[0];
      $tokens[$pre . mb_strtolower($attribute_name) . self::DELIMITER . 'last' . $post] = $attribute_value[0];
    }
    elseif (is_array($attribute_value) && $attribute_value['count'] > 1) {
      // Multiple entries, example: ['cn:last', 'cn:0', 'cn:1'].
      $tokens[$pre . mb_strtolower($attribute_name) . self::DELIMITER . 'last' . $post] = $attribute_value[$attribute_value['count'] - 1];
      for ($i = 0; $i < $attribute_value['count']; $i++) {
        $tokens[$pre . mb_strtolower($attribute_name) . self::DELIMITER . $i . $post] = $attribute_value[$i];
      }
    }
    elseif (is_scalar($attribute_value)) {
      // Only one entry (as string), example output: ['cn', 'cn:0', 'cn:last'].
      $tokens[$pre . mb_strtolower($attribute_name) . $post] = $attribute_value;
      $tokens[$pre . mb_strtolower($attribute_name) . self::DELIMITER . '0' . $post] = $attribute_value;
      $tokens[$pre . mb_strtolower($attribute_name) . self::DELIMITER . 'last' . $post] = $attribute_value;
    }
    return $tokens;
  }

  /**
   * Process a single LDAP Token key.
   *
   * @param array $ldap_entry
   *   LDAP entry.
   * @param string $pre
   *   Preamble.
   * @param string $post
   *   Postamble.
   * @param string $full_token_key
   *   Actual data.
   *
   * @return array
   *   Tokens.
   */
  private function processSingleLdapTokenKey(array $ldap_entry, $pre, $post, $full_token_key) {
    $tokens = [];
    // A token key is for example 'dn', 'mail:0', 'mail:last', or
    // 'guid:0;tobase64'.
    $value = NULL;

    // Trailing period to allow for empty value.
    list($token_key, $conversion) = explode(';', $full_token_key . ';');

    $parts = explode(self::DELIMITER, $token_key);
    $attribute_name = mb_strtolower($parts[0]);
    $ordinal_key = isset($parts[1]) ? $parts[1] : 0;
    $i = NULL;

    // Don't use empty() since a 0, "", etc value may be a desired value.
    if ($attribute_name == 'dn' || !isset($ldap_entry[$attribute_name])) {
      return [];
    }
    else {
      $count = $ldap_entry[$attribute_name]['count'];
      if ($ordinal_key === 'last') {
        $i = ($count > 0) ? $count - 1 : 0;
        $value = $ldap_entry[$attribute_name][$i];
      }
      elseif (is_numeric($ordinal_key) || $ordinal_key == '0') {
        $value = $ldap_entry[$attribute_name][$ordinal_key];
      }
      else {
        // don't add token if case not covered.
        return [];
      }
    }
    $value = ConversionHelper::convertAttribute($value, $conversion);

    $tokens[$pre . $full_token_key . $post] = $value;
    // We are redundantly setting the lowercase value here for consistency with
    // parent function.
    if ($full_token_key != mb_strtolower($full_token_key)) {
      $tokens[$pre . mb_strtolower($full_token_key) . $post] = $value;
    }
    return $tokens;
  }

}
