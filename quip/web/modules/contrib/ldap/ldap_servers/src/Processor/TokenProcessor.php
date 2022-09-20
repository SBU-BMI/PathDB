<?php

declare(strict_types = 1);

namespace Drupal\ldap_servers\Processor;

use Drupal\Component\Utility\Unicode;
use Drupal\ldap_servers\Helper\ConversionHelper;
use Drupal\ldap_servers\LdapTransformationTraits;
use Drupal\ldap_servers\Logger\LdapDetailLog;
use Symfony\Component\Ldap\Entry;

/**
 * Helper to manage LDAP tokens and process their content.
 */
class TokenProcessor {

  use LdapTransformationTraits;

  /**
   * Detail log.
   *
   * @var \Drupal\ldap_servers\Logger\LdapDetailLog
   */
  protected $detailLog;

  /**
   * Available tokens.
   *
   * Token array suitable for t() functions of with lowercase keys as
   * exemplified below.
   * From dn attribute:
   *   [cn] = jdoe
   *   [cn:0] = jdoe
   *   [cn:last] => jdoe
   *   [cn:reverse:0] = jdoe
   *   [ou] = campus accounts
   *   [ou:0] = campus accounts
   *   [ou:last] = toledo campus
   *   [ou:reverse:0] = toledo campus
   *   [ou:reverse:1] = campus accounts
   *   [dc] = ad
   *   [dc:0] = ad
   *   [dc:1] = myuniversity
   *   [dc:2] = edu
   *   [dc:last] = edu
   *   [dc:reverse:0] = edu
   *   [dc:reverse:1] = myuniversity
   *   [dc:reverse:2] = ad
   * From other attributes:
   *   [mail] = jdoe@myuniversity.edu
   *   [mail:0] = jdoe@myuniversity.edu
   *   [mail:last] = jdoe@myuniversity.edu
   *   [samaccountname] = jdoe
   *   [samaccountname:0] = jdoe
   *   [samaccountname:last] = jdoe
   *   [guid:0;base64_encode] = apply base64_encode() function to value
   *   [guid:0;bin2hex] = apply bin2hex() function to value
   *   [guid:0;msguid] = apply convertMsguidToString() function to value.
   *
   * @var array
   */
  private $tokens = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(LdapDetailLog $ldap_detail_log) {
    $this->detailLog = $ldap_detail_log;
  }

  /**
   * Replace a single token.
   *
   * @param \Symfony\Component\Ldap\Entry $resource
   *   The resource to act upon.
   * @param string $text
   *   The text such as "[dn]", "[cn]@my.org", "[displayName] [sn]",
   *   "Drupal Provisioned".
   *
   * @return string|null
   *   Replaced string.
   *
   * @see \Drupal\ldap_user\EventSubscriber\LdapEntryProvisionSubscriber::fetchDrupalAttributeValue()
   */
  public function ldapEntryReplacementsForDrupalAccount(Entry $resource, string $text): string {
    // Reset since service can be reused in multi-user processors.
    $this->tokens = [];
    // Greedy matching of groups of [], ignore spaces and trailing data with /x.
    preg_match_all('/\[([^\[\]]*)\]/x', $text, $matches);
    if (!isset($matches[1]) || empty($matches[1])) {
      // If no tokens exist in text, return text itself.
      return $text;
    }

    $this->tokenizeLdapEntry($resource, $matches[1]);

    foreach ($matches[0] as $target) {
      if (isset($this->tokens[$target])) {
        $text = str_replace($target, $this->tokens[$target], $text);
      }
    }

    // Strip out any un-replaced tokens.
    return preg_replace('/\[.*\]/', '', $text);
  }

  /**
   * Turn an LDAP entry into a token array suitable for the t() function.
   *
   * @param \Symfony\Component\Ldap\Entry $ldap_entry
   *   The LDAP entry.
   * @param array $required_tokens
   *   Tokens requested.
   */
  private function tokenizeLdapEntry(Entry $ldap_entry, array $required_tokens): void {
    if (empty($ldap_entry->getAttributes())) {
      $this->detailLog->log(
        'Skipped tokenization of LDAP entry because no LDAP entry provided when called from %calling_function.', [
          '%calling_function' => function_exists('debug_backtrace') ? debug_backtrace()[1]['function'] : 'undefined',
        ]
      );
      return;
    }

    $this->processDnParts($ldap_entry->getDn());
    $this->tokens['[dn]'] = $ldap_entry->getDn();

    foreach ($required_tokens as $required_token) {
      $this->processLdapTokenKey($ldap_entry, $required_token);
    }
  }

  /**
   * Deconstruct DN parts.
   *
   * @param string $dn
   *   DN.
   */
  private function processDnParts(string $dn): void {
    // 1. Tokenize dn
    // Escapes attribute values, need to be unescaped later.
    $dn_parts = self::splitDnWithAttributes($dn);
    unset($dn_parts['count']);
    $parts_count = [];
    $parts_last_value = [];
    foreach ($dn_parts as $pair) {
      [$name, $value] = explode('=', $pair);
      $value = ConversionHelper::unescapeDnValue($value);
      if (!Unicode::validateUtf8($value)) {
        $this->detailLog->log('Skipped tokenization of attribute %attr_name because the value is not valid UTF-8 string.', [
          '%attr_name' => $name,
        ]);
        continue;
      }
      if (!isset($parts_count[$name])) {
        // First and general entry.
        $this->tokens[sprintf('[%s]', mb_strtolower($name))] = $value;
        $parts_count[$name] = 0;
      }
      $this->tokens[sprintf('[%s:%s]', mb_strtolower($name), $parts_count[$name])] = $value;

      $parts_last_value[$name] = $value;
      $parts_count[$name]++;
    }

    // Add DN parts in reverse order to reflect the hierarchy for CN, OU, DC.
    foreach ($parts_count as $name => $count) {
      $part = mb_strtolower($name);
      for ($i = 0; $i < $count; $i++) {
        $reverse_position = $count - $i - 1;
        $this->tokens[sprintf('[%s:reverse:%s]', $part, $reverse_position)] = $this->tokens[sprintf('[%s:%s]', $part, $i)];
      }
    }

    foreach ($parts_count as $name => $count) {
      $this->tokens[sprintf('[%s:last]', mb_strtolower($name))] = $parts_last_value[$name];
    }
  }

  /**
   * Get Tokens.
   *
   * Convenience helper for ServerTestForm.
   *
   * @return array
   *   Tokens.
   */
  public function getTokens(): array {
    return $this->tokens;
  }

  /**
   * Process a single LDAP Token key.
   *
   * @param \Symfony\Component\Ldap\Entry $entry
   *   Entry.
   * @param string $required_token
   *   What was given as replacement pattern. For example 'dn', 'mail:0',
   *   'mail:last', or 'guid:0;tobase64'.
   */
  private function processLdapTokenKey(Entry $entry, string $required_token): void {
    // Trailing period to allow for empty value.
    [$token_key, $conversion] = explode(';', $required_token . ';');

    $parts = explode(':', $token_key);

    if ($parts === FALSE) {
      return;
    }

    $requested_name = $parts[0];
    $requested_index = $parts[1] ?? 0;

    if (mb_strtolower($requested_name) === 'dn') {
      return;
    }

    $values = $entry->getAttribute($requested_name, FALSE);

    if ($values === NULL || count($values) === 0) {
      // No data.
      return;
    }

    if ($requested_index === 'last') {
      $i = count($values) > 0 ? count($values) - 1 : 0;
      $value = $values[(int) $i];
    }
    else {
      $value = $values[$requested_index] ?? '';
    }

    $value = (string) $value;
    $value = ConversionHelper::convertAttribute($value, $conversion);
    $this->tokens[sprintf('[%s]', $required_token)] = $value;
  }

}
