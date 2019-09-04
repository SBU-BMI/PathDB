# Overview of the LDAP suite

The LDAP suite of modules is modular to allow you to pick and choose the 
elements your use-case requires. The current structure is not necessarily ideal
but rather keeps with the existing framework to avoid additional migration work.

The architecture in Drupal 8 differs significantly from Drupal 7 and will need 
to evolve further to become better testable. The currently present (non-working)
integration tests relied on a highly complex configuration and setup based on
SimpleTest. The goal of the current branch is to improve test coverage wherever
possible through unit tests and this testing architecture is being phased out
step by step.

## Setting up a development environment

To quickly get up and running without using a production system to query against
you can make use of Docker. 

An example configuration is provided in the docs directory based on the Harry 
Potter schools. That script - based on a script by
[Laudanum](https://github.com/Laudanum) - populates a Docker instance with users
and groups. A matching server template for LDAP is provided as well.

Note that in group configuration you could use businessCategory to derive user 
groups from attributes but this is disabled so that group DNs are queried.

Working with LDAP and the various elements of OpenLDAP, such as slapd, are
not easy to work with. See also some examples on the
[track hacks](http://trac-hacks.org/wiki/LdapPluginTests) page.

### User binding

If you want to bind with user credentials, you only need to modify the 
grants.ldif to allow for it. Here is an example which simply allows anyone:

```
11,12c11,13
<   by dn="cn=admin,dc=hogwarts,dc=edu" write
<   by * read
\ No newline at end of file
---
>   by anonymous auth
>   by dn="cn=admin,dc=hogwarts,dc=edu" write
>   by * read
```

## Various LDAP Project Notes

### Case Sensitivity and Character Escaping in LDAP Modules

The class MassageAttributes should be used for dealing with case sensitivity
and character escaping consistently. See the functions for further information.

A filter might be built as follows:

```php
$massage = new MassageAttributes;
$username = $massage->queryLdapAttributeValue($username);
$objectclass = mb_strtolower($item);
$filter = "(&(cn=$username)(objectClass=$objectclass))";
```

See ConversionHelper for working with fields directly.

### Common variables used in ldap_* and their structures

The structure of $ldap_user and $ldap_entry are different!

#### $ldap_user
@see LdapServer::matchUsernameToExistingLdapEntry() return

#### $ldap_entry and $ldap_*_entry.
@see LdapServer::ldap_search() return array

####  $user_attr_key
key of form <attr_type>.<attr_name>[:<instance>] such as field.lname, 
property.mail, field.aliases:2
