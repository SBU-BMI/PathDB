# Hide Revision Field

## ABOUT
Hide Revision Field provides a configurable field formatter for the revision log
field for revisionable entities. This allows you to create revisions but reduces
noise for your content editors/site owners. All revisionable content entity
types are supported including module added.

The widget has 4 settings for each instance:

  - `Default`: Provide a default log message.
  - `Show` (TRUE): Whether to show the field. If enabled the two other options
  will override this.
  - `Permission Based` (FALSE): Show if the user has the
  `access revision field` permission.
  - `Allow user specific configuration` (TRUE): Enables users to customize this
  field's visibility via their profile page if they have the
  `administer revision field personalization` permission.

## REQUIREMENTS
Drupal 8 is required, Drupal 8.6.x or higher is suggested.

## VERSIONS
* 8.x-2.x (Supported): (This version) Use for all new projects. There is a
 partial upgrade path from 8.x-1.x; user customization will have to be reset.

* 8.x-1.x (Deprecated): Use for Drupal 8.3 and under if necessary.

## INSTALLATION
Install as you would normally install a contributed Drupal module. See the
[install
docs](https://drupal.org/documentation/install/modules-themes/modules-8)
if required in the Drupal documentation for further information.

### UPGRADING FROM 8.x-1.x to 8.x-2.x
**Note:** Perform update separately from core updates (especially 8.5->8.6).

  * Update code:

    * If using Composer adjust your version constraint to update to `^2.1` or
     similar.

    * Otherwise, delete the module folder and extract the new version there.

  * Run `update.php` or `drush updb` (Drush) or `drupal update:execute` (Drupal
    Console).

  * If you had changed any user specific settings, they will need to be reset (
    See [Configuration](#configuration)).


## CONFIGURATION
* The primary configuration can be accessed for each supported entity bundle on
  the form display edit page for the entity type. For example for the Node type
  Article that would be at `/admin/structure/types/manage/article/form-display`.

  *Note:* the `Fields UI` module must be installed for that page to exist; if it
  isn't installed you can use the Drupal 8 CIM workflow to edit it like any
  other field in the `core.entity_form_display.ENTITY_TYPE.ENTITY_BUNDLE.yml`

* The module provides 2 new permissions (`access revision field` and `administer
  revision field personalization`). These can be configured with the standard
  Drupal permissions manager.

* The module also allows user specific settings - which can be disabled/enabled
  at the field display settings level. If enabled, that can be configured for
  each user on their user profile form (ie `/user/1/edit`).

## FAQ
Any questions? Ask away on the issue queue or contact the maintainer
[Nick Wilde](https://drupal.org/u/nickwilde).
