# CONTENTS OF THIS FILE

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


## INTRODUCTION

Hide Revision Field provides a configurable field formatter for the revision log
field for revisionable entities. This allows you to create revisions but reduces
noise for your content editors/site owners. All revisionable content entity
types are supported including module added.

 * For a full description of the module, visit the project page:
   <https://drupal.org/project/hide_revision_field>

 * To submit bug reports and feature suggestions, or to track changes:
   <https://drupal.org/project/issues/hide_revision_field>


## REQUIREMENTS

Drupal core user module.


## INSTALLATION

Install as you would normally install a contributed Drupal module. Visit:
<https://www.drupal.org/node/1897420> for further information.


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


## MAINTAINERS

 * Andrei Ivnitskii - <https://www.drupal.org/u/ivnish>
