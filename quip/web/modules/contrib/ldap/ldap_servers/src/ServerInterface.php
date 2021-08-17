<?php

declare(strict_types = 1);

namespace Drupal\ldap_servers;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Ldap\Entry;

/**
 * Server configuration entity interface.
 */
interface ServerInterface extends ConfigEntityInterface {

  /**
   * Returns the formatted label of the bind method.
   *
   * @return string
   *   The formatted text for the current bind.
   */
  public function getFormattedBind(): TranslatableMarkup;

  /**
   * Fetch base DN.
   *
   * @return array
   *   All base DN.
   */
  public function getBaseDn(): array;

  /**
   * Returns the username from the LDAP entry.
   *
   * @param \Symfony\Component\Ldap\Entry $ldap_entry
   *   The LDAP entry.
   *
   * @return string
   *   The user name.
   */
  public function deriveUsernameFromLdapResponse(Entry $ldap_entry): string;

  /**
   * Returns the user's email from the LDAP entry.
   *
   * @param \Symfony\Component\Ldap\Entry $ldap_entry
   *   The LDAP entry.
   *
   * @return string
   *   The user's mail value.
   */
  public function deriveEmailFromLdapResponse(Entry $ldap_entry): string;

  /**
   * Fetches the persistent UID from the LDAP entry.
   *
   * @param \Symfony\Component\Ldap\Entry $ldapEntry
   *   The LDAP entry.
   *
   * @return string|false
   *   The user's PUID or permanent user id (within ldap), converted from
   *   binary, if applicable.
   */
  public function derivePuidFromLdapResponse(Entry $ldapEntry);

  /**
   * Get account name attribute.
   *
   * @return string
   *   Attribute.
   */
  public function getAccountNameAttribute(): ?string;

  /**
   * Account name attribute set.
   *
   * @return bool
   *   Has attribute.
   */
  public function hasAccountNameAttribute(): bool;

  /**
   * Get server address.
   *
   * @return string
   *   Value.
   */
  public function getServerAddress(): string;

  /**
   * Get bind method.
   *
   * @return string
   *   Value.
   */
  public function getBindMethod(): string;

  /**
   * Get bind DN.
   *
   * @return string
   *   Value.
   */
  public function getBindDn(): ?string;

  /**
   * Get bind password.
   *
   * @return string
   *   Value.
   */
  public function getBindPassword(): ?string;

  /**
   * Get attribute of the user's LDAP entry DN which contains the group.
   *
   * @return string
   *   Value.
   */
  public function getDerivedGroupFromDnAttribute(): ?string;

  /**
   * Groups are derived from user's LDAP entry DN.
   *
   * @return bool
   *   Value.
   */
  public function isGroupDerivedFromDn(): bool;

  /**
   * Get user attribute held in "LDAP Group Entry Attribute Holding...".
   *
   * @return string
   *   Value.
   */
  public function getUserAttributeFromGroupMembershipEntryAttribute(): ?string;

  /**
   * Get LDAP group entry attribute holding user's DN, CN, etc.
   *
   * @return string
   *   Value.
   */
  public function getGroupMembershipAttribute(): ?string;

  /**
   * Are groups nested?
   *
   * @return bool
   *   Value.
   */
  public function isGrouppNested(): bool;

  /**
   * Get the name group object class.
   *
   * @return string
   *   Value.
   */
  public function getGroupObjectClass(): ?string;

  /**
   * Get writable Group DN for group testing.
   *
   * @return string
   *   Value.
   */
  public function getGroupTestGroupDnWriteable(): ?string;

  /**
   * Get group DN for group testing.
   *
   * @return string
   *   Value.
   */
  public function getGroupTestGroupDn(): ?string;

  /**
   * Get group usage.
   *
   * @return bool
   *   Value.
   */
  public function isGroupUnused(): bool;

  /**
   * Attribute in user entry contains groups.
   *
   * @return bool
   *   Value.
   */
  public function isGroupUserMembershipAttributeInUse(): bool;

  /**
   * Get attribute in user entry containing groups.
   *
   * @return string
   *   Value.
   */
  public function getGroupUserMembershipAttribute(): ?string;

  /**
   * Get mail.
   *
   * @return string
   *   Value.
   */
  public function getMailAttribute(): ?string;

  /**
   * Get mail template.
   *
   * @return string
   *   Value.
   */
  public function getMailTemplate(): ?string;

  /**
   * Get picture attribute.
   *
   * @return string
   *   Value.
   */
  public function getPictureAttribute(): ?string;

  /**
   * Get port.
   *
   * @return int
   *   Value.
   */
  public function getPort(): int;

  /**
   * Get status.
   *
   * @return bool
   *   Value.
   */
  public function isActive(): bool;

  /**
   * Get Drupal user DN for testing.
   *
   * @return string
   *   Value.
   */
  public function getTestingDrupalUserDn(): ?string;

  /**
   * Get Drupal username for testing.
   *
   * @return string
   *   Value.
   */
  public function getTestingDrupalUsername(): ?string;

  /**
   * Get timeout.
   *
   * @return int
   *   Value.
   */
  public function getTimeout(): int;

  /**
   * Is the PUID attribute binary?
   *
   * @return bool
   *   Value.
   */
  public function isUniquePersistentAttributeBinary(): bool;

  /**
   * Get the PUID attribute.
   *
   * @return string
   *   Value.
   */
  public function getUniquePersistentAttribute(): ?string;

  /**
   * Get authentication name attribute.
   *
   * @return string
   *   Value.
   */
  public function getAuthenticationNameAttribute(): ?string;

  /**
   * Get User DN expression.
   *
   * @return string
   *   Value.
   */
  public function getUserDnExpression(): ?string;

  /**
   * Get Weight.
   *
   * @return int
   *   Value.
   */
  public function getWeight(): int;

}
