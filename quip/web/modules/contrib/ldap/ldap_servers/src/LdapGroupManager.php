<?php

declare(strict_types = 1);

namespace Drupal\ldap_servers;

use Drupal\ldap_servers\Helper\ConversionHelper;
use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\LdapException;
use function in_array;

/**
 * LDAP Group Manager.
 */
class LdapGroupManager extends LdapBaseManager {

  use LdapTransformationTraits;

  /**
   * Recursion limit.
   *
   * @var int
   */
  protected const LDAP_QUERY_RECURSION_LIMIT = 10;

  /**
   * Check if group memberships from group entry are configured.
   *
   * @return bool
   *   Whether group memberships from group entry are configured.
   */
  public function groupGroupEntryMembershipsConfigured(): bool {
    return $this->server->get('grp_memb_attr_match_user_attr') &&
      $this->server->get('grp_memb_attr');
  }

  /**
   * Search within the nested groups for further filters.
   *
   * @param array $all_group_dns
   *   Currently set groups.
   * @param array $or_filters
   *   Filters before diving deeper.
   * @param int $level
   *   Last relevant nesting level.
   *
   * @return array
   *   Nested group filters.
   */
  private function getNestedGroupDnFilters(array $all_group_dns, array $or_filters, int $level): array {
    // Example 1: (|(cn=group1)(cn=group2))
    // Example 2: (|(dn=cn=group1,ou=blah...)(dn=cn=group2,ou=blah...))
    $or_filter = sprintf('(|(%s))', implode(')(', $or_filters));
    $query_for_parent_groups = sprintf('(&(objectClass=%s)%s)', $this->server->get('grp_object_cat'), $or_filter);

    // Need to search on all base DN one at a time.
    foreach ($this->server->getBaseDn() as $base_dn) {
      // No attributes, just dns needed.
      try {
        $ldap_result = $this->ldap->query($base_dn, $query_for_parent_groups, ['filter' => []])->execute();
      }
      catch (LdapException $e) {
        $this->logger->critical('LDAP search error with %message', [
          '%message' => $e->getMessage(),
        ]);
        continue;
      }
      if ($level < self::LDAP_QUERY_RECURSION_LIMIT && $ldap_result->count() > 0) {
        $tested_group_ids = [];
        $this->groupMembershipsFromEntryRecursive($ldap_result, $all_group_dns, $tested_group_ids, $level + 1, self::LDAP_QUERY_RECURSION_LIMIT);
      }
    }
    return $all_group_dns;
  }

  /**
   * Add a group entry.
   *
   * Functionality is not in use, only called by server test form.
   *
   * @param string $group_dn
   *   The group DN as an LDAP DN.
   * @param array $attributes
   *   Attributes in key value form
   *    $attributes = array(
   *      "attribute1" = "value",
   *      "attribute2" = array("value1", "value2"),
   *      )
   *
   * @return bool
   *   Operation result.
   */
  public function groupAddGroup(string $group_dn, array $attributes = []): bool {
    if (!$this->checkAvailability() || $this->checkDnExists($group_dn)) {
      return FALSE;
    }

    $attributes = array_change_key_case($attributes, CASE_LOWER);
    if (empty($attributes['objectclass'])) {
      $objectClass = $this->server->get('grp_object_cat');
    }
    else {
      $objectClass = $attributes['objectclass'];
    }
    $attributes['objectclass'] = $objectClass;

    $context = [
      'action' => 'add',
      'corresponding_drupal_data' => [$group_dn => $attributes],
      'corresponding_drupal_data_type' => 'group',
    ];
    $ldap_entries = [$group_dn => $attributes];
    $this->moduleHandler->alter('ldap_entry_pre_provision', $ldap_entries, $this, $context);
    $attributes = $ldap_entries[$group_dn];

    $entry = new Entry($group_dn, $attributes);
    try {
      $this->ldap->getEntryManager()->add($entry);
    }
    catch (LdapException $e) {
      $this->logger->error('LDAP server %id exception: %ldap_error', [
        '%id' => $this->server->id(),
        '%ldap_error' => $e->getMessage(),
      ]
      );
      return FALSE;
    }

    $this->moduleHandler->invokeAll('ldap_entry_post_provision', [
      $ldap_entries,
      $this,
      $context,
    ]
    );
    return TRUE;
  }

  /**
   * Remove a group entry.
   *
   * Functionality is not in use, only called by server test form.
   *
   * @param string $group_dn
   *   Group DN as LDAP dn.
   * @param bool $only_if_group_empty
   *   TRUE = group should not be removed if not empty
   *   FALSE = groups should be deleted regardless of members.
   *
   * @return bool
   *   Removal result.
   *
   * @todo When actually in use split into two to remove boolean modifier.
   */
  public function groupRemoveGroup(string $group_dn, bool $only_if_group_empty = TRUE): bool {
    if (!$this->checkAvailability()) {
      return FALSE;
    }

    if ($only_if_group_empty) {
      $members = $this->groupAllMembers($group_dn);
      if (!empty($members)) {
        return FALSE;
      }
    }
    return $this->deleteLdapEntry($group_dn);

  }

  /**
   * Add a member to a group.
   *
   * Functionality only called by server test form.
   *
   * @param string $group_dn
   *   LDAP group DN.
   * @param string $user
   *   LDAP user DN.
   *
   * @return bool
   *   Operation successful.
   *
   * @FIXME symfony/ldap refactoring needed.
   */
  public function groupAddMember(string $group_dn, string $user): bool {
    if (!$this->checkAvailability()) {
      return FALSE;
    }

    $result = FALSE;
    if ($this->groupGroupEntryMembershipsConfigured()) {
      $entry = new Entry($group_dn);
      $manager = $this->ldap->getEntryManager();
      try {
        $manager->addAttributeValues($entry, $this->server->get('grp_memb_attr'), [$user]);
        $result = TRUE;
      }
      catch (LdapException $e) {
        $this->logger->error('LDAP server error updating %dn on @sid exception: %ldap_error', [
          '%dn' => $group_dn,
          '@sid' => $this->server->id(),
          '%ldap_error' => $e->getMessage(),
        ]
        );
      }
    }

    return $result;
  }

  /**
   * Remove a member from a group.
   *
   * Functionality only called by server test form.
   *
   * @param string $group_dn
   *   LDAP DN group.
   * @param string $member
   *   LDAP DN member.
   *
   * @return bool
   *   Operation successful.
   */
  public function groupRemoveMember(string $group_dn, string $member): bool {
    $result = FALSE;

    if ($this->checkAvailability() && $this->groupGroupEntryMembershipsConfigured()) {
      $entry = new Entry($group_dn);
      $manager = $this->ldap->getEntryManager();
      try {
        $manager->removeAttributeValues($entry, $this->server->get('grp_memb_attr'), [$member]);
        $result = TRUE;
      }
      catch (LdapException $e) {
        $this->logger->error('LDAP server error updating %dn on @sid exception: %ldap_error', [
          '%dn' => $group_dn,
          '@sid' => $this->server->id(),
          '%ldap_error' => $e->getMessage(),
        ]
        );
      }
    }
    return $result;
  }

  /**
   * Get all members of a group.
   *
   * Currently not in use.
   *
   * @param string $group_dn
   *   Group DN as LDAP DN.
   *
   * @return array
   *   Array of group members (could be users or
   *   groups).
   *
   * @todo Split return functionality or throw an error.
   */
  public function groupAllMembers(string $group_dn): array {
    $members = [];
    if (!$this->checkAvailability() || !$this->groupGroupEntryMembershipsConfigured()) {
      return $members;
    }

    $attributes = [$this->server->get('grp_memb_attr'), 'cn', 'objectclass'];
    $group_entry = $this->checkDnExistsIncludeData($group_dn, $attributes);
    if (!$group_entry) {
      return $members;
    }

    // If attributes weren't returned, don't give false empty group.
    if (
      empty($group_entry->getAttribute('cn', FALSE)) ||
      empty($group_entry->getAttribute($this->server->get('grp_memb_attr'), FALSE))
    ) {
      // If no attribute returned, no members.
      return $members;
    }

    $members = $group_entry->getAttribute($this->server->get('grp_memb_attr'), FALSE);

    $this->groupMembersRecursive([$group_entry], $members, [], 0, self::LDAP_QUERY_RECURSION_LIMIT);

    // Remove the DN of the source group.
    $source_dn_key = array_search($group_dn, $members, TRUE);
    if ($source_dn_key !== FALSE) {
      unset($members[$source_dn_key]);
    }

    return $members;
  }

  /**
   * Get direct members of a group.
   *
   * Currently not in use.
   *
   * @param string $group_dn
   *   Group DN as LDAP DN.
   *
   * @return bool|array
   *   FALSE on error, otherwise array of group members (could be users or
   *   groups).
   *
   * @todo Split return functionality or throw an error.
   */
  public function groupMembers(string $group_dn) {
    if (!$this->checkAvailability()) {
      return FALSE;
    }

    if (!$this->groupGroupEntryMembershipsConfigured()) {
      return FALSE;
    }

    $attributes = [$this->server->get('grp_memb_attr'), 'cn', 'objectclass'];
    $group_entry = $this->checkDnExistsIncludeData($group_dn, $attributes);
    if (!$group_entry) {
      return FALSE;
    }

    // If attributes weren't returned, don't give false, give empty group.
    if (!$group_entry->hasAttribute('cn', FALSE)) {
      return FALSE;
    }
    if (!$group_entry->hasAttribute($this->server->get('grp_memb_attr'), FALSE)) {
      // If no attribute returned, no members.
      return [];
    }

    return $group_entry->getAttribute($this->server->get('grp_memb_attr'), FALSE);
  }

  /**
   * Is a user a member of group?
   *
   * @param string $group_dn
   *   Group DN in mixed case.
   * @param string $username
   *   A Drupal username.
   *
   * @return bool
   *   Whether the user belongs to the group.
   */
  public function groupIsMember(string $group_dn, string $username): bool {
    if ($this->checkAvailability()) {
      $group_dns = $this->groupMembershipsFromUser($username);
      if (!empty($group_dns)) {
        // While list of group dns is going to be in correct mixed case,
        // $group_dn may not since it may be derived from user entered values so
        // make sure in_array() is case insensitive.
        $lower_cased_group_dns = array_keys(array_change_key_case(array_flip($group_dns), CASE_LOWER));
        if (in_array(mb_strtolower($group_dn), $lower_cased_group_dns, TRUE)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Recurse through all child groups and add members.
   *
   * @param \Symfony\Component\Ldap\Entry[] $entries
   *   Entries of LDAP group entries that are starting point. Should include at
   *   least 1 entry and must include 'objectclass'.
   * @param array $all_member_dns
   *   All member DN as an array of all groups the user is a member of. Mixed
   *   case values.
   * @param array $tested_group_dns
   *   Tested group IDs as an array array of tested group dn, cn, uid, etc.
   *   Mixed case values. Whether these value are dn, cn, uid, etc depends on
   *   what attribute members, uniquemember, memberUid contains whatever
   *   attribute is in $this->$tested_group_ids to avoid redundant recursion.
   * @param int $level
   *   Current level of recursion.
   * @param int $max_levels
   *   Maximum number of recursion levels allowed.
   * @param array|null $object_classes
   *   You can set the object class evaluated for recursion here, otherwise
   *   derived from group configuration.
   */
  public function groupMembersRecursive(
    array $entries,
    array &$all_member_dns,
    array $tested_group_dns,
    int $level,
    int $max_levels,
    ?array $object_classes = NULL
  ): void {
    if (!$this->checkAvailability()) {
      return;
    }

    if (!$this->groupGroupEntryMembershipsConfigured()) {
      return;
    }

    foreach ($entries as $entry) {
      // Add entry itself if of the correct type to $all_member_dns.
      $lowercased_object_class = array_map('strtolower', array_values($entry->getAttribute('objectClass', FALSE)));
      $object_is_group = in_array($this->server->get('grp_object_cat'), $lowercased_object_class, TRUE);
      $object_class_match = !$object_classes || count(array_intersect(array_values($entry->getAttribute('objectClass', FALSE)), $object_classes)) > 0;
      if ($object_class_match && !in_array($entry->getDn(), $all_member_dns, TRUE)) {
        $all_member_dns[] = $entry->getDn();
      }

      // If its a group, keep recurse the group for descendants.
      if ($object_is_group && $level < $max_levels) {
        if ($this->server->get('grp_memb_attr_match_user_attr') === 'dn') {
          $group_id = $entry->getDn();
        }
        else {
          $group_id = $entry->getAttribute($this->server->get('grp_memb_attr_match_user_attr'), FALSE)[0];
        }
        // 3. skip any groups that have already been tested.
        if (!in_array($group_id, $tested_group_dns, TRUE)) {
          $tested_group_dns[] = $group_id;
          $member_ids = $entry->getAttribute($this->server->get('grp_memb_attr'), FALSE);

          if (count($member_ids)) {
            // Example 1: (|(cn=group1)(cn=group2))
            // Example 2: (|(dn=cn=group1,ou=blah...)(dn=cn=group2,ou=blah...))
            $query_for_child_members = sprintf('(|(%s))', implode(')(', $member_ids));
            // Add or on object classes, otherwise get all object classes.
            if ($object_classes && count($object_classes)) {
              $object_classes_ors = [
                sprintf('(objectClass=%s)', $this->server->get('grp_object_cat')),
              ];
              foreach ($object_classes as $object_class) {
                $object_classes_ors[] = sprintf('(objectClass=%s)', $object_class);
              }
              $query_for_child_members = sprintf('&(|%s)(%s)', implode('', $object_classes_ors), $query_for_child_members);
            }

            $child_member_entries = $this->searchAllBaseDns(
              $query_for_child_members,
              [
                'objectClass',
                $this->server->get('grp_memb_attr'),
                $this->server->get('grp_memb_attr_match_user_attr'),
              ]
            );
            if (!empty($child_member_entries)) {
              $this->groupMembersRecursive(
                $child_member_entries,
                $all_member_dns,
                $tested_group_dns,
                $level + 1,
                $max_levels,
                $object_classes
              );
            }
          }
        }
      }
    }
  }

  /**
   * Get list of all groups that a user is a member of.
   *
   * If nesting is configured, the list will include all parent groups. For
   * example, if the user is a member of the "programmer" group and the
   * "programmer" group is a member of the "it" group, the user is a member of
   * both the "programmer" and the "it" group. If $nested is FALSE, the list
   * will only include groups which are directly assigned to the user.
   *
   * @param string $username
   *   A Drupal user entity.
   *
   * @return array
   *   Array of group dns in mixed case or FALSE on error.
   */
  public function groupMembershipsFromUser(string $username): array {
    $group_dns = [];
    if (!$this->checkAvailability()) {
      return $group_dns;
    }

    $user_ldap_entry = $this->matchUsernameToExistingLdapEntry($username);
    if (!$user_ldap_entry || $this->server->get('grp_unused')) {
      return $group_dns;
    }

    // Preferred method.
    if ($this->server->isGroupUserMembershipAttributeInUse() && $this->server->getGroupUserMembershipAttribute()) {
      $group_dns = $this->groupUserMembershipsFromUserAttr($user_ldap_entry);
    }
    elseif ($this->groupGroupEntryMembershipsConfigured()) {
      $group_dns = $this->groupUserMembershipsFromEntry($user_ldap_entry);
    }
    return $group_dns;
  }

  /**
   * Get list of groups that a user is a member of using the memberOf attribute.
   *
   * @param \Symfony\Component\Ldap\Entry $ldap_entry
   *   A Drupal user entity, an LDAP entry array of a user  or a username.
   *
   * @return array
   *   Array of group dns in mixed case or FALSE on error.
   *
   * @see groupMembershipsFromUser()
   */
  public function groupUserMembershipsFromUserAttr(Entry $ldap_entry): array {

    if (!$this->checkAvailability() || !$this->server->isGroupUserMembershipAttributeInUse()) {
      return [];
    }

    $group_attribute = $this->server->getGroupUserMembershipAttribute();
    if (!$ldap_entry->hasAttribute($group_attribute, FALSE)) {
      return [];
    }

    $level = 0;
    $all_group_dns = [];
    $members_group_dns = $ldap_entry->getAttribute($group_attribute, FALSE);
    $orFilters = [];
    foreach ($members_group_dns as $member_group_dn) {
      $all_group_dns[] = $member_group_dn;
      if ($this->server->get('grp_nested')) {
        if ($this->server->get('grp_memb_attr_match_user_attr') === 'dn') {
          $member_value = $member_group_dn;
        }
        else {
          $member_value = $this->getFirstRdnValueFromDn($member_group_dn);
        }
        $orFilters[] = $this->server->get('grp_memb_attr') . '=' . $this->ldapEscapeDn($member_value);
      }
    }

    if ($this->server->get('grp_nested') && count($orFilters)) {
      $all_group_dns = $this->getNestedGroupDnFilters($all_group_dns, $orFilters, $level);
    }

    return $all_group_dns;
  }

  /**
   * Get list of all groups that a user is a member of by querying groups.
   *
   * @param \Symfony\Component\Ldap\Entry $ldap_entry
   *   LDAP entry.
   *
   * @return array
   *   Array of group dns in mixed case.
   *
   * @see groupMembershipsFromUser()
   */
  public function groupUserMembershipsFromEntry(Entry $ldap_entry): array {
    // MIXED CASE VALUES.
    $all_group_dns = [];

    if (!$this->checkAvailability() || !$this->groupGroupEntryMembershipsConfigured()) {
      return $all_group_dns;
    }

    // Array of dns already tested to avoid excess queries MIXED CASE VALUES.
    $tested_group_ids = [];
    $level = 0;

    if ($this->server->get('grp_memb_attr_match_user_attr') === 'dn') {
      $member_value = $ldap_entry->getDn();
    }
    else {
      $member_value = $ldap_entry->getAttribute($this->server->get('grp_memb_attr_match_user_attr'), FALSE)[0];
    }

    // Need to search on all basedns one at a time.
    foreach ($this->server->getBaseDn() as $baseDn) {
      // Only need dn, so empty array forces return of no attributes.
      // @todo See if this syntax is correct.
      // It should return a valid DN with n attributes.
      try {
        $group_query = sprintf('(&(objectClass=%s)(%s=%s))', $this->server->get('grp_object_cat'), $this->server->get('grp_memb_attr'), $member_value);
        $ldap_result = $this->ldap->query($baseDn, $group_query, ['filter' => []])->execute();
      }
      catch (LdapException $e) {
        $this->logger->critical('LDAP search error with %message', [
          '%message' => $e->getMessage(),
        ]);
        continue;
      }

      if ($ldap_result->count() > 0) {
        $maxLevels = $this->server->get('grp_nested') ? self::LDAP_QUERY_RECURSION_LIMIT : 0;
        $this->groupMembershipsFromEntryRecursive($ldap_result, $all_group_dns, $tested_group_ids, $level, $maxLevels);
      }
    }
    return $all_group_dns;
  }

  /**
   * Recurse through all groups, adding parent groups to $all_group_dns array.
   *
   * @param \Symfony\Component\Ldap\Adapter\CollectionInterface|Entry[] $current_group_entries
   *   Entries of LDAP groups, which are that are starting point. Should include
   *   at least one entry.
   * @param array $all_group_dns
   *   An array of all groups the user is a member of in mixed-case.
   * @param array $tested_group_ids
   *   An array of tested group DN, CN, UID, etc. in mixed-case. Whether these
   *   value are DN, CN, UID, etc. depends on what attribute members,
   *   uniquemember, or memberUid contains whatever attribute in
   *   $this->$tested_group_ids to avoid redundant recursion.
   * @param int $level
   *   Levels of recursion.
   * @param int $max_levels
   *   Maximum levels of recursion allowed.
   *
   * @return bool
   *   False for error or misconfiguration, otherwise TRUE. Results are passed
   *   by reference.
   *
   * @todo See if we can do this with groupAllMembers().
   */
  private function groupMembershipsFromEntryRecursive(
    CollectionInterface $current_group_entries,
    array &$all_group_dns,
    array &$tested_group_ids,
    int $level,
    int $max_levels
  ): bool {

    if (!$this->groupGroupEntryMembershipsConfigured() || $current_group_entries->count() === 0) {
      return FALSE;
    }

    $or_filters = [];
    /** @var \Symfony\Component\Ldap\Entry $group_entry */
    foreach ($current_group_entries->toArray() as $group_entry) {
      if ($this->server->get('grp_memb_attr_match_user_attr') === 'dn') {
        $member_id = $group_entry->getDn();
      }
      // Maybe cn, uid, etc is held.
      else {
        $member_id = $this->getFirstRdnValueFromDn($group_entry->getDn());
      }

      if ($member_id && !in_array($member_id, $tested_group_ids, TRUE)) {
        $tested_group_ids[] = $member_id;
        $all_group_dns[] = $group_entry->getDn();
        // Add $group_id (dn, cn, uid) to query.
        $or_filters[] = $this->server->get('grp_memb_attr') . '=' . $this->ldapEscapeDn($member_id);
      }
    }

    if (!empty($or_filters)) {
      // Example 1: (|(cn=group1)(cn=group2))
      // Example 2: (|(dn=cn=group1,ou=blah...)(dn=cn=group2,ou=blah...))
      $or = sprintf('(|(%s))', implode(')(', $or_filters));
      $query_for_parent_groups = sprintf('(&(objectClass=%s)%s)', $this->server->get('grp_object_cat'), $or);

      // Need to search on all base DNs one at a time.
      foreach ($this->server->getBaseDn() as $base_dn) {
        // No attributes, just dns needed.
        try {
          $ldap_result = $this->ldap->query($base_dn, $query_for_parent_groups, ['filter' => []])->execute();
        }
        catch (LdapException $e) {
          $this->logger->critical('LDAP search error with %message', [
            '%message' => $e->getMessage(),
          ]);
          continue;
        }

        if ($level < $max_levels && $ldap_result->count() > 0) {
          $this->groupMembershipsFromEntryRecursive(
            $ldap_result,
            $all_group_dns,
            $tested_group_ids,
            $level + 1,
            $max_levels
          );
        }
      }
    }
    return TRUE;
  }

  /**
   * Get "groups" from derived from DN.
   *
   * Has limited usefulness.
   *
   * @param string $username
   *   A username.
   *
   * @return array
   *   Array of group strings.
   */
  public function groupUserMembershipsFromDn(string $username): array {
    $memberships = [];
    if (
      $this->checkAvailability() &&
      $this->server->isGroupDerivedFromDn() &&
      $this->server->getDerivedGroupFromDnAttribute()
    ) {
      $ldap_entry = $this->matchUsernameToExistingLdapEntry($username);
      if ($ldap_entry) {
        $memberships = $this->getAllRdnValuesFromDn(
          $ldap_entry->getDn(),
          $this->server->getDerivedGroupFromDnAttribute()
        );
      }
    }
    return $memberships;
  }

  /**
   * Return the first RDN Value from DN.
   *
   * Given a DN (such as cn=jdoe,ou=people) and an RDN (such as cn),
   * determine that RND value (such as jdoe).
   *
   * @param string $dn
   *   Input DN.
   *
   * @return string
   *   Value of RDN.
   */
  private function getFirstRdnValueFromDn(string $dn): string {
    $value = '';
    if (!empty($dn)) {
      $parts = self::splitDnWithValues($dn);
      if ($parts && $parts['count'] > 0) {
        $value = $parts[0];
        // Possibly unnecessary.
        $value = ConversionHelper::unescapeDnValue(trim($value));
      }
    }

    return $value;
  }

  /**
   * Returns all RDN values from DN.
   *
   * Given a DN (such as cn=jdoe,ou=people) and an rdn (such as cn),
   * determine that RDN value (such as jdoe).
   *
   * @param string $dn
   *   Input DN.
   * @param string $rdn
   *   RDN Value to find.
   *
   * @return array
   *   All values of RDN.
   */
  public function getAllRdnValuesFromDn(string $dn, string $rdn): array {
    // Escapes attribute values, need to be unescaped later.
    $pairs = self::splitDnWithAttributes($dn);
    array_shift($pairs);
    $rdn = mb_strtolower($rdn);
    $rdn_values = [];
    foreach ($pairs as $p) {
      $pair = explode('=', $p);
      if ($pair !== FALSE && mb_strtolower(trim($pair[0])) === $rdn) {
        $rdn_values[] = ConversionHelper::unescapeDnValue(trim($pair[1]));
        break;
      }
    }
    return $rdn_values;
  }

}
