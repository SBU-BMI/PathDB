# External Authentication

The External Authentication module provides a generic service for logging in and
registering users that are authenticated against an external site or service,
and for storing the authentication details. It is the Drupal 8+ equivalent of
`user_external_login_register()` and related functions, as well as the authmap
table in Drupal 6 and 7 core.

- For a full description of the module, visit the
  [project page](https://www.drupal.org/project/externalauth).

- To submit bug reports and feature suggestions, or to track changes, visit the
  [issue queue](https://www.drupal.org/project/issues/externalauth).


## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
- [Configuration](#configuration)
- [Links and views support](#links-and-views-support)
- [Upgrading](#upgrading)
- [Maintainers](#maintainers)


## Requirements

This module requires no modules outside of Drupal core.

The list of authentication names described under
[Links and views support](#links-and-views-support) requires the core Views
module. Its view is provided as optional configuration, so it is only installed
when Views is enabled.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Usage

Install this module if it is required as a dependency for an external
authentication Drupal module. Module authors that provide external
authentication methods can use this helper service to provide a consistent API
for storing and retrieving external authentication data.


## Configuration

The module has no settings form of its own. It provides two permissions, which
can be assigned at Administration > People > Permissions:

- **View external authentication data** - grants access to the "External
  authentication links" listing, available as the "External" tab on
  Administration > People (`/admin/people/authmap`).
- **Delete external authentication data** - allows deleting individual links
  between a user account and an external authentication name.

Modules that build on External Authentication may add their own settings; see
their documentation for details.


## Links and views support

`/admin/people/authmap` shows a list of all "Authentication names" that are
registered by external authentication methods for each user login.

The list facilitates deleting links to 'wrong' / outdated authentication names.
There is explicitly no add/edit facility for these links; they should only be
added through logins (or, depending on the method, prepopulated by a system
administrator to allow a specific set of users to use login).

The list does not show the "provider" (i.e. the method) by default, because in
most situations, this is an internal name that is always the same and not useful
to administrators. If multiple authentication methods are active in your site,
the list could start showing duplicate entries, and you may need to distinguish
between the different methods by changing configuration, e.g. by:

- editing the view at `/admin/structure/views/view/authmap`, and adding the
  "provider" field.
- denying access to the main view (`/admin/people/authmap`) somehow, and only
  accessing lists specific to a provider (e.g.
  `/admin/people/authmap/samlauth`).
- editing the view at `/admin/structure/views/view/authmap`, and changing the
  "Contextual filter" to be "Display a summary". This shows the URLs for all
  appropriate provider-specific lists.

Authentication names can be added to any other views in your site that include
user information. These can then also start showing duplicate entries when a
user logs in through multiple authentication methods, which will need similar
adjustments as suggested above.


## Migrating authmap entries

This module ships no migrations. To import authmap entries from another site,
define the migration in a custom module, with a destination plugin that calls
`\Drupal\externalauth\AuthmapInterface::save()`.

The `authmap` migrate destination plugin is deprecated in externalauth:2.0.14
and is removed from externalauth:2.2.0.


## Maintainers

- Sven Decabooter - [svendecabooter](https://www.drupal.org/u/svendecabooter)

See the [project page](https://www.drupal.org/project/externalauth) for the
current list of maintainers.
