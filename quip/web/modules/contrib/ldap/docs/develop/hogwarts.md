# Hogwarts sample directory

`docs/hogwarts/` is a small OpenLDAP dataset (Harry Potter names) for local
development. It is not a production pattern.

## Start the directory

You need Docker and the `ldap-utils` client tools (`ldapadd`, `ldapsearch`,
`ldapmodify`). From `docs/hogwarts/`:

```bash
./docker.sh
```

When prompted, choose a bind method (`service_account` is the default),
`user`, or `anon_user`. The script starts
[osixia/openldap](https://github.com/osixia/docker-openldap) as
`hogwarts_ldap.<method>`, publishes **389 inside the container to host
port 9389**, loads `hogwarts.people.ldif`, then applies
`grants.<method>.ldif`.

Domain: `hogwarts.edu` (`dc=hogwarts,dc=edu`). Admin bind used to load data:
`cn=admin,dc=hogwarts,dc=edu` / `admin`.

For **service account**, a search for Hermione looks like:

```bash
ldapsearch -x -h 127.0.0.1 -p 9389 -b dc=hogwarts,dc=edu \
  -D cn=admin,dc=hogwarts,dc=edu -w admin '(cn=hgranger)' dn
```

Stop other Hogwarts containers before switching bind method; the script
does that for the known names.

To allow bind with user credentials, the `user` grants LDIF is the starting
point—not a wide-open anonymous write ACL.

## Drupal example config

YAML under `docs/hogwarts/drupal_configuration/` matches the current
config schema. Import or copy values into the UI; they are examples, not
a full site export.

| File | Use |
| ---- | --- |
| `ldap_servers.server.hogwarts.yml` | Server `hogwarts`, `127.0.0.1:9389`, `encryption: none`, service-account bind, people and groups base DNs, `user_attr: cn`. |
| `ldap_authentication.settings.yml` | Mixed mode, server `hogwarts`. |
| `ldap_user.settings.yml` | Provision both ways, sample mail/cn mappings, cron query `example_query`. |
| `ldap_query.ldap_query_entity.example_query.yml` | People search. |
| `authorization.authorization_profile.ldap_roles.yml` | LDAP provider → Drupal roles. |

Point **address** at `host.docker.internal` or the DDEV host gateway if
PHP runs in a container and OpenLDAP on the host—`localhost` inside DDEV
is not the host’s port 9389.

See [Configure a server](../getting-started/servers.md) and
[Testing](testing.md) for how this data is also mocked in Kernel tests.
