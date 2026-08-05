# File Replace

The file replace module is a small utility providing site administrators with
the possibility to replace files, keeping the file uri intact. This is useful
in cases where a file is linked or used directly but needs to be updated
occasionally.

For a full description of the module, visit the
[Project Page](https://www.drupal.org/project/file_replace).

Submit bug reports and feature suggestions, or track changes in the
[Issue Queue](https://www.drupal.org/project/issues/file_replace).


## Table of contents

- Requirements
- Installation
- Configuration
- Maintainers


## Requirements

This module requires no modules outside of Drupal core.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

The module currently only provides a 'Replace' page for files. You will need
to manually add links to the file overview page. Simply adding a global text
field with a link like the following would work fine:
```
admin/content/files/replace/{{ fid }}
```


## Maintainers

- Björn Brala - [bbrala](https://www.drupal.org/u/bbrala)
- [casey](https://www.drupal.org/u/casey)
- Timo Huisman [timohuisman](https://www.drupal.org/u/timohuisman)
