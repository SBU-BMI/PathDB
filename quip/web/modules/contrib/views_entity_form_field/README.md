CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

This module provides the ability to add form field widgets to a view to edit
multiple entities at one time.

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/views_entity_form_field

 * To submit bug reports and feature suggestions, or to track changes:
   https://www.drupal.org/project/issues/views_entity_form_field


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


INSTALLATION
------------

 * Install the Views Entity Form Field module as you would normally install a
   contributed Drupal module. Visit
   https://www.drupal.org/node/1897420 for further information.


CONFIGURATION
-------------

    1. Navigate to Administration > Extend and enable the module.
    2. Form field options will show up in a View's "Add field" list, prefixed
       with "Form field: " and then the name of the field being added.

Notes:

 * Each entity field will check the user's access to edit both the entity and
   the field. Some entities (like Commerce Product Variations) don't do proper
   access checking, so always make sure that the View page also has
   permission/access checks and/or consider writing an entity access hook.
 * There is an issue with Views that use Bulk Form Operations - BFO takes over
   the submit button and will throw validation errors if there are no BFO
   checkboxes checked. It's suggested to not use both modules on the same View
   for now.


MAINTAINERS
-----------

 * Garrett Rathbone (grathbone) - https://www.drupal.org/u/grathbone
