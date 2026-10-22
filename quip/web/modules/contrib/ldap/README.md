
Please see [docs/](docs/index.md) (MkDocs) for installation, configuration,
and developer notes. With DDEV, the site is `https://docs.ldap.ddev.site`.

For more information:

* [Project page](https://www.drupal.org/project/ldap)

## Module overview

| Module | Description |
| ------ | ----------- |
| ldap_authentication | Authenticate against the directory. Depends on ldap_user and ldap_servers. |
| ldap_authorization | Authorization provider: map directory groups to Drupal roles. Requires the Authorization module. |
| ldap_query | Stored LDAP queries for Views and other LDAP modules. |
| ldap_servers | Directory connection, bind, groups, and tokens. |
| ldap_user | Drupal ↔ LDAP account and field sync. |

A common stack for login, roles, and field sync is ldap_authentication,
ldap_authorization, ldap_servers, and ldap_user.

## Extending this module

If your use case is not covered, start with the hooks in
`ldap_user.api.php`, `ldap_servers.api.php`, and
`ldap_authentication.api.php`. See [docs/develop/hooks.md](docs/develop/hooks.md).

Custom login forms often never reach LDAP validation; use Drupal’s login
form or the same validator path as ldap_authentication.

## General LDAP resources

* [PHP LDAP](https://www.php.net/manual/en/book.ldap.php)
* [Microsoft Active Directory](https://learn.microsoft.com/en-us/windows/win32/ad/active-directory-domain-services)
* [Apache Directory Studio](https://directory.apache.org/studio/)
