# Hooks and events

Prefer the APIs below for custom mapping, extra deny rules, and reacting to
provisioning. Signatures live in the `*.api.php` files; this page is when
to reach for each.

## Deny or allow login

`hook_ldap_authentication_allowuser_results_alter()` in
`ldap_authentication.api.php` receives the Symfony `Entry`, the Drupal
account name, and a `$hook_result` flag. Set it to `FALSE` to refuse
authentication. Do not override another module that already denied the
user.

Use this for directory flags Drupal mappings cannot express. Simple DN
allow/deny lists belong in the authentication UI.

## Extra Drupal field targets

`hook_ldap_user_attributes_alter()` in `ldap_user.api.php` adds rows to the
mapping form (custom fields, properties). Merge with existing values; do
not wipe mappings LDAP Users already configured.

`hook_ldap_user_edit_user_alter()` runs while a Drupal user is filled from
an LDAP entry. Set fields on the `$account` object (the hook does not save
for you beyond what the processor already does). Use tokens via
`ldap.token_processor` when the value comes from the entry.

## LDAP entry provisioning

`hook_ldap_entry_pre_provision_alter()` / `hook_ldap_entry_post_provision()`
in `ldap_servers.api.php` run around writes to the directory. Alter or
react to the entry arrays keyed by lowercase DN. Pre-provision is the place
to change attributes before `ldap_add` / modify; post-provision is for
side effects.

`hook_ldap_servers_user_cron()` and `hook_ldap_servers_user_cron_needed()`
run a periodic batch of LDAP-associated Drupal users. If you implement the
cron hook but have nothing to do, return `FALSE` from `_needed`.

## Events (ldap_user)

Subscribe with the event **name** constants, not only the class:

| Constant | Name | When |
| -------- | ---- | ---- |
| `LdapUserLoginEvent::EVENT_NAME` | `ldap_user_login` | LDAP-associated user authenticated. |
| `LdapNewUserCreatedEvent::EVENT_NAME` | `ldap_new_drupal_user_created` | New Drupal user created from LDAP. |
| `LdapUserUpdatedEvent::EVENT_NAME` | `ldap_drupal_user_update` | Existing Drupal user updated from LDAP. |
| `LdapUserDeletedEvent::EVENT_NAME` | `ldap_drupal_user_deleted` | Drupal user deleted. |

`LdapEntryProvisionSubscriber` and `LdapEntryDeletionSubscriber` listen to
create/update/delete and write or remove directory entries when those
triggers are enabled. Custom modules should subscribe the same way rather
than duplicating bind logic.
