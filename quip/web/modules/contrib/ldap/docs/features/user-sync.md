# User sync

LDAP Users decides **when** Drupal accounts exist and **which** properties
and fields move between Drupal and the directory. Configure at
`/admin/config/people/ldap/user`, plus the Drupal-mapping and LDAP-mapping
tabs.

You pick:

- A server that **provisions Drupal accounts from LDAP**.
- An optional server that **provisions LDAP entries from Drupal** (needs a
  service-account bind with write access).

## Triggers

Toward Drupal (`drupalAcctProvisionTriggers`):

- `drupal_on_login` — create or update the Drupal user at authentication.
- `drupal_on_update_create` — sync when the Drupal user is saved.
- `drupal_on_manual_creation` — associate when an administrator creates an
  account.

Toward LDAP (`ldapEntryProvisionTriggers`):

- `ldap_on_login` / `ldap_on_update_create` — write directory attributes.
- `ldap_on_delete` — delete the LDAP entry when the Drupal user is deleted.

Account-creation policy (`acctCreation`) either follows LDAP Users
(`ldap_behavior`) or Drupal’s user-module settings for LDAP users.

Conflict options cover an existing Drupal account with the same name and
manual account association (`conflict_reject`, `conflict_associate`,
`conflict_show_option`, `conflict_no_ldap_associate`).

## Mappings

Mappings live in `ldap_user.settings:ldapUserSyncMappings` under `drupal`
and `ldap`. Each row binds an LDAP token (see [Tokens](tokens.md)) to a
Drupal property, field, or user-entered token, a direction, and provision
events such as `create_drupal_user` and `sync_to_ldap_entry`.

Use the mapping UIs rather than editing YAML unless you are exporting
config. Binary attributes can be marked **convert**.

## Orphans

On cron, LDAP Users can look for Drupal accounts whose persistent unique ID
no longer exists in the directory. Configure behavior (email report, and
related options), batch size, interval, and whether disabled users are
included. This needs a **PUID** attribute on the server entity.

## Cron updates from a query

Set **user update cron query** to a stored [LDAP Query](query.md) entity to
create or refresh Drupal users from that search on an interval
(`userUpdateCronInterval`). Optional **update only** skips creating new
Drupal users.

## Custom login forms

LDAP Authentication integrates with Drupal’s standard login form. A custom
login form often never reaches that validator, so credentials look wrong
even when the server test succeeds. Prefer the core login form, or
integrate with the same validation path the module uses.
