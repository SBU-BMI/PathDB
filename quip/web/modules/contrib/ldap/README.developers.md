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

## Testing LDAP behavior

Since problems often occur with the interpretation of a directory server's
output it's important that we test against expected results and not just
test our functions in isolation. 

Whenever you are trying to debug a complex dance between the Drupal integration
modules and a directory, consider mocking the LDAP connector with the Fake
classes provided by ldap_servers. For example: 
\Drupal\Tests\ldap_authentication\LoginTest

## Case-handling

LDAP is a case-aware but not case-sensitive protocol, which means that what
we get back in Symfony\Component\Ldap\Entry objects, or LDAP data in general,
may contain differences in case. For example the property "memberOf".

We need to keep the following in mind when making changes to these modules:
* Comparisons against LDAP data must ignore case. Examples: 
  * A query for ldap authorization specified as "memberof=..." in
the configuration must also catch data returned as "memberOf=...".
  * Token processing on records returned by LDAP must do the same.
* Data sent to LDAP can ignore case-formatting (we do not need to normalize it).

Note that attributes returned from LDAP via the LdapBaseManager are lowercased
through `::sanitizeUserDataResponse` so we need to
`get('businesscategory')` not `get('businessCategory')`.

## Manual retesting

When changing behavior of this module it's not always easy to anticipate the
impact due to the multiple possible configurations and setups. The tests
are often only able to look at functionality in isolation, not the interaction
of different (mis-)configurations. When in doubt, try to manually retest the
core cases, such as:

- User login with existing user (user already synced from LDAP)
- User creation upon login (user present in LDAP)
- Denial of registration in exclusive mode when user does not in LDAP
- Drupal user sync data from LDAP upon login
- Drupal user sync data from LDAP upon Drupal user save
- LDAP user creation on user registration
- LDAP user update on Drupal user update
- Combined configuration of sync from LDAP when used in conjunction with
  sync to LDAP.

## Misc

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
