# Authorization

LDAP Authorization does **not** have its own role UI. It is a **provider
plugin** (`ldap_provider`) for the
[Authorization](https://www.drupal.org/project/authorization) module.

Enable **Authorization** and **LDAP Authorization** only after LDAP login
works. Create an authorization **profile**, choose the LDAP provider and
the Drupal roles consumer, then map directory groups to roles.

## Provider settings

- **Server**: which LDAP server configuration supplies group data.
- **Only LDAP-authenticated users**: skip people who did not log in via
  LDAP.
- **Use first attribute as group id**: map using the first RDN of the
  group DN (for example `cn=students` instead of the full DN).

Group membership uses the group settings on the [server](../getting-started/servers.md)
entity (`member`, `memberOf`, nested groups, DN-derived groups, and so
on). Wrong group object class or membership attribute is the usual reason
nobody receives roles.

## Sync on logon

Typical profile settings:

- Synchronization on **user logon**.
- **Revoke** roles this profile previously granted when the user is no
  longer in the mapped group.
- **Regrant** mapped roles when the user is still in the group.

Map each LDAP group DN (or regex) to a Drupal role. Empty mapping rows do
nothing.

You can also deny login when the user would receive no authorizations; that
checkbox lives on [Authentication](authentication.md), not on the profile.
