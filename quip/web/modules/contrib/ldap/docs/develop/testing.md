# Testing

Automated tests are PHPUnit **Unit**, **Kernel**, and **Functional** tests
under each module’s `tests/src`. They do not require a live directory.

## Fake LDAP

Enable the hidden test module `ldap_servers_dummy`
(`ldap_servers/tests/modules/ldap_servers_dummy`). It provides:

- `FakeBridge` — stand-in for `ldap.bridge`
- `FakeLdap` / `FakeQuery` / `FakeCollection` — Symfony LDAP-shaped
  results
- `FakeEntryManager` — in-memory entries

Typical Kernel pattern (see
`ldap_authentication/tests/src/Kernel/LoginTest.php`):

1. Enable `ldap_servers_dummy` with the modules under test.
2. Create an `ldap_server` entity (encryption `none`, a base DN, `user_attr`).
3. Build a `FakeBridge`, `setServer()`, and
   `get()->setQueryResult()` with filter strings mapped to `FakeCollection`
   of `Symfony\Component\Ldap\Entry` objects.
4. `$this->container->set('ldap.bridge', $bridge)`.

Assert Drupal users, form errors, and authmap rows—not live LDAP.

## What to retest by hand

Kernel tests cannot cover every combination of mixed vs exclusive,
provision both ways, and campus schemas. After behavioral changes, log
into a real or [Hogwarts](hogwarts.md) directory and check:

- Login when the Drupal user already exists and is mapped.
- Drupal user created on first LDAP login.
- Exclusive mode refuses people who are not in the directory.
- Field sync from LDAP on login and on Drupal user save.
- LDAP entry created on Drupal registration (if provision-to-LDAP is on).
- LDAP entry updated when the Drupal user is updated.
- Both directions enabled at once without looping or clobbering.

## Case in tests

Use lowercase attribute names on fake entries (`cn`, `mail`, `uid`) to
match production configuration. Comparisons of DNs and membership should
ignore case.
