CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

This module allows headers to be added, updated or removed through configuration
with a particular focus on security and performance headers.

 * For a full description of the module, visit the project page:
   https://drupal.org/project/http_response_headers

 * To submit bug reports and feature suggestions, or to track changes:
   https://drupal.org/project/issues/http_response_headers


REQUIREMENTS
------------

No special requirements.


INSTALLATION
------------

Install as you would normally install a contributed Drupal module. See:
https://www.drupal.org/docs/8/extending-drupal/installing-contributed-modules
for further information.


CONFIGURATION
-------------

Configure the HTTP Response Headers settings in Administration » Configuration »
Development » Response header configuration

Please install the module and configure it here
`/admin/config/system/response-headers`.

This module provide the following default HTTP header configurations:
 * Access-Control-Allow-Origin
 * Content-Security-Policy
 * Public-Key-Pins
 * Referrer-Policy
 * Strict-Transport-Security
 * X-Content-Type-Options
 * X-Frame-Options
 * X-Generator
 * X-Powered-By
 * X-Xss-Protection

Adding a new response header
Home » Administration » Configuration » System » Response header configuration
/admin/config/system/response-headers/add


MAINTAINERS
-----------

Current maintainers:
 * Minnur Yunusov (minnur) - https://www.drupal.org/u/minnur
 * Vijaya Chandran Mani (vijaycs85) - https://www.drupal.org/u/vijaycs85
