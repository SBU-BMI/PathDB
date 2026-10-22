# Debugging

Work through these in order before opening an issue.

1. **Recent log messages** (`/admin/reports/dblog`) for `ldap_servers`,
   `ldap_authentication`, and `ldap_user`.
2. **Detailed watchdog** at **Configuration → People → LDAP → Debugging**
   (`/admin/config/people/ldap/debug`). That sets
   `ldap_servers.settings:watchdog_detail` and writes extra detail through
   the `ldap.detail_log` service. Turn it off again on production.
3. **Debug report** at `/admin/config/people/ldap/debug/report`.
4. **Server test** on the LDAP server entity with a known-good username.
5. Prove the same bind and search work in another LDAP client, such as
   [Apache Directory Studio](https://directory.apache.org/studio/). If the
   client fails, Drupal cannot succeed.

Isolate one user, one server configuration, and one action (bind, login, or
sync). Mixed authentication, exclusive mode, field mappings, and
authorization all change the outcome; change one variable at a time.

## Common failures

- Wrong bind method, DN, or password (including a config override that does
  not match the environment).
- Encryption or certificate problems, especially self-signed certs.
- Authentication name attribute that does not match what users type.
- Custom login forms that skip Drupal’s user login validation. LDAP
  authentication hooks the standard login form; a custom form often reports
  invalid credentials even when the directory is fine.

## Support requests

If you file an issue on
[the LDAP queue](https://www.drupal.org/node/add/project-issue/ldap),
include Drupal and PHP versions, encryption and bind method, whether mixed
or exclusive authentication is on, and a description that another LDAP
client can reproduce. Maintainers cannot recreate every campus directory.
