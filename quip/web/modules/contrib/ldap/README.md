The current state of the LDAP module is still in-flux while the port to Drupal 8
is ongoing. The majority of the core functionality is available and usable but
caution should be taken for more complex scenarios such as provisioning to LDAP.

Please see INSTALL.md for specific information on setting up the Drupal LDAP
suite.

For more information review the following resources:

* [Project page](https://www.drupal.org/project/ldap)
* [Issue for Drupal 8 port](https://www.drupal.org/node/2259385)


## Module overview

| Module | Description |
| ------ | ----------- |
| ldap_authentication | This module provides a overall authentication functionality closely tied to ldap_user and ties in with several other modules, such as ldap_sso. |
| ldap_authorization | The module to grant roles to users based on directory criteria, relies on the externalauth module. |
| ldap_feeds (Unported) | Feeds integration to automatically sync users. |
| ldap_query | A module to allow you to execute custom queries, which can be display in Views or used in custom solutions. |
| ldap_servers | The base module for communicating with a directory. |
| ldap_sso | Provides Kerberos/NTLM single-sign-on. Note that this module is now a [separate project on drupal.org](https://www.drupal.org/project/ldap_sso). |
| ldap_user | A base module with low-level user functionality as well as mechanisms to sync user data. |

A common scenario for logging in users via LDAP, assigning groups to them and
syncing user fields thus consists of ldap_authentication, ldap_authorization,
ldap_servers, ldap_user.

## Additional information

If you are not yet familiar with how LDAP operates or how directory services
work in general, the following links can be helpful resources.

However, we recommend in any case that you contact your organization's directory
maintainer, since their help can often save you a significant amount of time in
debugging.

## Extending this module and custom development

If your use-case isn't quite covered by this module, you might require some
custom development. Most of these customizations should be able to be done by
hooks, see for example ldap_user.api.php or ldap_servers.api.php for ways
to integrate with provisioning users, or adjusting mappings on more complex
data structures.

If your site uses a custom login form, the LDAP module will likely always return
that credentials are incorrect, have a look at ldap_user.module for what you
need or help us in making that integration independent of the specific form.

## General LDAP resources

* Documentation from the PHP project on its
[LDAP implemtation](https://secure.php.net/manual/en/book.ldap.php)
* Microsoft's Active Directory
[documentation overview](http://msdn.microsoft.com/en-us/library/aa705886(VS.85).aspx)
* Moodle's
[LDAP module documentation](http://docs.moodle.org/20/en/LDAP_authentication) is
detailed and provides insight into LDAP in a PHP environment.
* [Apache Directory Studio](http://directory.apache.org/studio/)
LDAP Browser and Directory Client.
* [Novell Edirectory](http://www.novell.com/documentation/edir873/index.html?page=/documentation/edir873/edir873/data/h0000007.html)

### Example documentations from public universities

* [Northwestern University](http://www.it.northwestern.edu/bin/docs/CentralAuthenticationServicesThroughLDAP.pdf)
* [University of Washington](https://itconnect.uw.edu/wares/msinf/authn/ldap/)
* [UIOWA](https://wiki.uiowa.edu/display/ICTSit/Drupal+LDAP+Integration+Against+Active+Directory)
