# LDAP for Drupal

This suite connects Drupal to a directory (Active Directory, OpenLDAP, and
similar) over LDAP. Use it to authenticate users, create or update Drupal
accounts from directory data, map groups to roles, and run stored searches.

Project page: [drupal.org/project/ldap](https://www.drupal.org/project/ldap).
Related Drupal.org guide:
[Lightweight Directory Access Protocol](https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/lightweight-directory-access-protocol/servers).

With [DDEV](https://ddev.readthedocs.io/), this MkDocs site is at
`https://docs.ldap.ddev.site`.

## Recommended path

1. Enable **LDAP Servers**, add a server, and confirm it is available.
2. Enable **LDAP Users** and choose how Drupal accounts and fields sync.
3. Enable **LDAP Authentication** and log in as a directory user.
4. After login works, add **LDAP Authorization** plus an
   [Authorization](https://www.drupal.org/project/authorization) profile for
   roles.

Do not configure authorization until authentication succeeds. Contact your
directory administrators first: they have bind accounts, base DNs, and
attribute names that save a lot of trial and error.

## Modules

| Module | Role |
| ------ | ---- |
| LDAP Servers (`ldap_servers`) | Connection, bind, groups, tokens. Required by everything else. |
| LDAP Query (`ldap_query`) | Stored searches and a Views query plugin. Pulled in by LDAP Users. |
| LDAP Users (`ldap_user`) | Drupal ↔ directory account and field sync. |
| LDAP Authentication (`ldap_authentication`) | Log in against the directory. |
| LDAP Authorization (`ldap_authorization`) | Provider for the Authorization module (roles from groups). |

The hidden `ldap` project module exists so Composer and Drupal.org treat the
package as one project. Enable the submodules you need, not the hidden file.

Composer also requires
[External Authentication](https://www.drupal.org/project/externalauth) and,
for roles, [Authorization](https://www.drupal.org/project/authorization).

## Core vs this suite

Drupal core does not speak LDAP. This project uses PHP’s LDAP extension and
[Symfony LDAP](https://symfony.com/doc/current/components/ldap.html). SSO
(`ldap_sso`) is a **separate** project; this suite only exposes a login
validator service other modules can call.
