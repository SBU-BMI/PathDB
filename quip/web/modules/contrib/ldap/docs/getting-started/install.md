# Installation

## Prerequisites

- Drupal **9.3 through 11** (`core_version_requirement` on the modules).
- The **PHP LDAP extension** (`ext-ldap`) on the web runtime.
- Access details from your directory team: host, port, encryption, bind
  method, and the unique user attribute (for example `sAMAccountName` on
  Active Directory, often `uid` or `cn` on OpenLDAP).

Install the [PHP LDAP extension](https://www.php.net/manual/en/book.ldap.php)
using your platform’s documentation (DDEV, Debian packages, and so on). Do
not copy generic PHP compile steps into this site.

Composer dependencies include `symfony/ldap`, `drupal/externalauth`, and
`drupal/authorization` (needed when you enable LDAP Authorization).

## Enable modules

1. Enable **LDAP Servers**.
2. Add a server under **Configuration → People → LDAP → Servers**
   (`/admin/config/people/ldap/server`) until the list reports it available.
   See [Configure a server](servers.md).
3. Enable **LDAP Users** (this enables **LDAP Query** as well). Configure
   provision servers and mappings under
   `/admin/config/people/ldap/user`.
4. Enable **LDAP Authentication**. Configure mixed vs exclusive mode under
   `/admin/config/people/ldap/authentication`.
5. Confirm a real directory user can log in, then enable **Authorization**
   and **LDAP Authorization**.

The LDAP admin hub is `/admin/config/people/ldap`. The permission is
**Administer LDAP** (`administer ldap`).

## Keep bind passwords out of exported config

Put a dummy password on the server entity and override it in `settings.php`
so credentials are not in the database or config export:

```php
$config['ldap_servers.server.YOUR_SERVER']['bindpw'] = 'actual-password';
$config['ldap_servers.server.YOUR_SERVER']['binddn'] = 'actual-distinguished-name';
```

`YOUR_SERVER` is the server entity machine name. You can include a file
outside the web root that sets these overrides. The server edit form shows
the same snippets and flags detected overrides.
