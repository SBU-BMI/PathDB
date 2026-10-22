# Configure a server

LDAP Servers stores **connection configurations**. Other modules bind and
search through those entities. One physical directory can have several
configurations (different bind users, base DNs, or purposes).

Admin: **Configuration → People → LDAP → Servers**
(`/admin/config/people/ldap/server`). After you create a server, use
**Test** on that entity.

Start with a **service account** bind. It is the usual production setup and
is required to provision entries back into LDAP.

## Connection

| Setting | Notes |
| ------- | ----- |
| Address | Hostname only. Do not prefix `ldap://` or `ldaps://`. |
| Port | Typically `389` (LDAP / STARTTLS) or `636` (LDAPS). |
| Encryption | `none`, `ssl` (ldaps://), or `tls` (STARTTLS). |
| Timeout | Seconds to wait for the network and query. |

Linux LDAP libraries often fail with **self-signed certificates**. Prefer
certificates the OS trust store accepts.

## Bind methods

| Method | When to use |
| ------ | ----------- |
| `service_account` | Default. Bind DN + password for searches and provisioning. |
| `user` | Bind as the logging-in user. DN must match `cn=[username],[base dn]`. Only useful during login. |
| `anon_user` | Anonymous search to find the user DN, then bind as that user. |
| `anon` | No credentials. Rarely works on encrypted connections. |

Service account credentials should be a dedicated directory account with
the least privilege you can grant.

## Users and identifiers

Set these from your directory schema, not from guesswork:

- **Authentication name attribute** (`user_attr`): value people type at
  login (`cn`, `uid`, `sAMAccountName`, …). The form stores these names in
  lowercase.
- **Account name attribute**: Drupal username if it differs from the
  authentication name.
- **Email attribute** and optional **email template**.
- **Persistent unique ID** (`unique_persistent_attr`): used to recognize
  the same person across renames and for orphan detection. Mark it binary
  when the directory stores a binary GUID.

Base DNs are a list: people, groups, or other branches you search.

## Groups

Configure group object class, membership attributes, nesting, and optional
groups derived from the user DN when you will use
[Authorization](../features/authorization.md) or group-based logic. You can
mark groups unused if this site never maps directory groups.

## After save

The server list should show the configuration as available. If it does not,
the usual causes are bind DN/password, port, encryption, or certificates.
Use the server **Test** form with the testing username and DN you stored on
the entity.

Example OpenLDAP values for the in-repo sample directory are in
[Hogwarts](../develop/hogwarts.md).
