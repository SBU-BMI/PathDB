# Authorization module

## Overview

This module provides a generic solution to mapping authorizations from a source
to a target.

Modules currently using authorization are:

| Role | Module |
| ---- | ------ |
| Provider | ldap_authorization | 
| Consumer | authorization_drupal_roles (included) | 

### General concepts

An authorization follows the following general steps:

1. The provider provides a list of proposals pertaining to a user (e.g. group 
   information in an LDAP record).
2. The list of proposals is evaluated against a user-configured list of mappings
   defined in an authorization profile (i.e. the proposal is found in the list
   does not violate any other constraints). If successful, these authorizations
   are granted.
3. If configured, any previously set and no longer relevant grants are revoked.

## Limitations

- Authorization evaluates each profile in isolation.
- The *Drupal roles* submodule has only one field for grants per user. This
  means that **you cannot use multiple *Drupal roles* profiles** and have role
  revocation working correctly. In theory you could apply revocation only to the
  last profile but this is untested and unsupported.
- The *Drupal roles* submodule does not allow the mapping of the two reserved
  group names "none" and "source".

If you run into issues with these limitations, please open a feature request.

## Configuration

The recommended configuration is to create one profile per consumer/provider
combination. The form itself should guide you through the process.

### Dynamic role mapping

A common scenario in a directory context is that the list of roles to assign
is dynamic. The modules ldap_authorization and authorization_drupal_roles can
work together to provide this when configured as follows:

| Ldap Query | Regular Expression | Drupal Roles |
| ---------- | ------------------ | ------------ | 
| `/.*/` | Checked | Source (Any group) |

If you do not have *"Convert full DN to value of first attribute before mapping"*
set you would need to modify the regular expression to not have the full DN as
group name.
                   
 