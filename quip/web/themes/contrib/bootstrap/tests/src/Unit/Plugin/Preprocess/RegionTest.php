<?php

namespace Drupal\Tests\bootstrap\Unit\Plugin\Preprocess;

use Drupal\bootstrap\Plugin\PluginBase;
use Drupal\bootstrap\Plugin\Preprocess\Region;
use Drupal\bootstrap\Theme;
use Drupal\bootstrap\Utility\Variables;
use PHPUnit\Framework\TestCase;

/**
 * Tests the region preprocess plugin.
 *
 * @group bootstrap
 */
class RegionTest extends TestCase {

  /**
   * Tests that core-provided render-array content is not overwritten.
   *
   * Core may provide region content as a render array before #children has been
   * rendered. Bootstrap must preserve that content rather than replacing it
   * with the still-empty #children value.
   *
   * @see https://www.drupal.org/project/drupal/issues/3178202
   * @see https://www.drupal.org/project/bootstrap/issues/3628721
   */
  public function testRenderArrayContentIsPreserved(): void {
    $theme = $this->createMock(Theme::class);
    $theme->expects($this->once())
      ->method('getSetting')
      ->with('region_wells')
      ->willReturn([]);

    // Bypass PluginBase's constructor so this focused unit test does not need
    // a Drupal service container merely to provide the Theme object.
    $plugin = (new \ReflectionClass(Region::class))->newInstanceWithoutConstructor();
    $theme_property = new \ReflectionProperty(PluginBase::class, 'theme');
    $theme_property->setValue($plugin, $theme);

    $content = [
      'test_block' => [
        '#markup' => 'Region content survives.',
      ],
    ];
    $variables_array = [
      'elements' => [
        '#region' => 'content',
        '#children' => '',
      ],
      'content' => $content,
    ];
    $variables = Variables::create($variables_array);

    $plugin->preprocessVariables($variables);

    $this->assertSame('content', $variables_array['region']);
    $this->assertSame($content, $variables_array['content']);
  }

}
