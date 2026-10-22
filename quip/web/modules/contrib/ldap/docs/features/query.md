# Queries and Views

LDAP Query stores reusable directory searches (`ldap_query_entity`) for
Views and for other LDAP modules. LDAP Users depends on this module even if
you never create a Views display.

Admin: `/admin/config/people/ldap/query`. Each query has a **Test**
operation.

## Query entity

| Field | Purpose |
| ----- | ------- |
| Server | Which server configuration to bind and search. |
| Base DN | Search base. |
| Filter | LDAP filter, for example `(objectClass=person)`. |
| Attributes | Optional comma-separated attributes to return; empty means the server default. |
| Size / time limits | `0` means no extra limit from this entity. |
| Dereference | Alias handling (`LDAP_DEREF_*`). |
| Scope | `sub` (subtree, default), `base`, or `one`. |

Use **Test** after save. Filters and base DNs that work in Apache Directory
Studio should match here.

## Views

Enable Views (already a dependency). Add a view whose query plugin is the
LDAP Query plugin and select a stored query. This is for listing directory
data, not for replacing Drupal user listings.

## Cron user updates

LDAP Users can run a named query on cron to create or update Drupal users
from the result set. Point **user update cron query** at the query machine
name. See [User sync](user-sync.md).
