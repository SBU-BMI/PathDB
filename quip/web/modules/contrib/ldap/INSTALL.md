# Connecting Drupal to a directory service via LDAP

Full instructions: [docs/getting-started/install.md](docs/getting-started/install.md)
(MkDocs Home: [docs/index.md](docs/index.md)).

Keep service-account credentials out of exported config with a dummy
password on the server entity and an override in `settings.php`:

```php
$config['ldap_servers.server.YOUR_SERVER']['bindpw'] = 'actual-password';
```

SSO is not part of this project. Use a separate module such as ldap_sso
together with `ldap_authentication.login_validator_sso`.
