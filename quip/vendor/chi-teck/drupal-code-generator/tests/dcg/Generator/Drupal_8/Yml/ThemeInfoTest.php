<?php

namespace DrupalCodeGenerator\Tests\Generator\Drupal_8\Yml;

use DrupalCodeGenerator\Tests\Generator\GeneratorBaseTest;

/**
 * Test for d8:yml:theme-info command.
 */
class ThemeInfoTest extends GeneratorBaseTest {

  protected $class = 'Drupal_8\Yml\ThemeInfo';

  protected $interaction = [
    'Theme name [%default_name%]:' => 'Example',
    'Theme machine name [example]:' => 'example',
    'Base theme [classy]:' => 'garland',
    'Description [A flexible theme with a responsive, mobile-first layout.]:' => 'Example description.',
    'Package [Custom]:' => 'Custom',
  ];

  protected $fixtures = [
    'example.info.yml' => __DIR__ . '/_theme_info.yml',
  ];

}
