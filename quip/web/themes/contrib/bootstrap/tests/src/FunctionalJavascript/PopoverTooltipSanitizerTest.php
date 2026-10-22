<?php

namespace Drupal\Tests\bootstrap\FunctionalJavascript;

use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests that the popover and tooltip behaviors keep the Bootstrap sanitizer.
 *
 * The behaviors build their options object from the element's own data-*
 * attributes and pass it to the Bootstrap plugin explicitly. Bootstrap only
 * strips the sanitizer keys (sanitize, sanitizeFn, whiteList) from attributes
 * it reads itself, not from options passed explicitly, so a data-sanitize
 * attribute would otherwise let an author disable XSS sanitizing per element.
 *
 * @group bootstrap
 */
class PopoverTooltipSanitizerTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'filter',
    'field',
    'text',
    'jquery_ui',
    'jquery_ui_draggable',
    'jquery_ui_resizable',
  ];

  /**
   * {@inheritdoc}
   *
   * Install with a dependency-free theme, then switch to Bootstrap once its
   * jquery_ui module dependencies are enabled (see setUp()).
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   *
   * The Bootstrap theme ships settings without a complete config schema.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * A node whose body carries the popover and tooltip payloads.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Enable the Bootstrap theme (its dependencies are in $modules) and make
    // it the default, so the popover/tooltip behaviors run on the front end.
    \Drupal::service('theme_installer')->install(['bootstrap']);
    $this->config('system.theme')->set('default', 'bootstrap')->save();
    drupal_flush_all_caches();

    // A text format that leaves markup untouched, standing in for any format a
    // site configures to permit Bootstrap data-* attributes.
    FilterFormat::create([
      'format' => 'bootstrap_data_attributes',
      'name' => 'Bootstrap data attributes',
    ])->save();

    $type = NodeType::create(['type' => 'page', 'name' => 'Page']);
    $type->save();
    node_add_body_field($type);

    // The data-sanitize="false" and data-html="true" attributes try to disable
    // the sanitizer; data-title/data-content hold an onerror payload that sets
    // a window flag when it executes.
    $payload = '<a id="sanitizer-tooltip" data-toggle="tooltip" data-html="true" data-sanitize="false" data-title="&lt;img src=x onerror=window.xssFired=(window.xssFired||0)+1&gt;ok">tooltip</a>'
      . ' <a id="sanitizer-popover" data-toggle="popover" data-html="true" data-sanitize="false" data-trigger="manual" title="pop" data-content="&lt;img src=y onerror=window.xssFired=(window.xssFired||0)+1&gt;ok">popover</a>';

    $this->node = Node::create([
      'type' => 'page',
      'title' => 'Sanitizer payload',
      'status' => 1,
      'body' => ['value' => $payload, 'format' => 'bootstrap_data_attributes'],
    ]);
    $this->node->save();
  }

  /**
   * Asserts data-* attributes cannot turn off the sanitizer.
   */
  public function testSanitizerCannotBeDisabledByDataAttributes(): void {
    $this->drupalGet($this->node->toUrl());
    $session = $this->getSession();

    // The Bootstrap plugin is loaded from the CDN; wait for it to be ready.
    $this->assertJsCondition("typeof jQuery !== 'undefined' && typeof jQuery.fn.tooltip === 'function' && typeof jQuery.fn.popover === 'function'");

    // Tooltip: show it and read what landed inside it.
    $session->executeScript("window.xssFired = 0; jQuery('#sanitizer-tooltip').tooltip('show');");
    $this->assertJsCondition("document.querySelector('.tooltip-inner') !== null");
    $tooltip = $session->evaluateScript("document.querySelector('.tooltip-inner').innerHTML");

    // The benign text proves the tooltip actually rendered the payload, so a
    // passing assertion below means the content was sanitized, not just empty.
    $this->assertStringContainsString('ok', $tooltip);
    $this->assertStringNotContainsString('onerror', $tooltip);

    // Popover: same check.
    $session->executeScript("jQuery('#sanitizer-popover').popover('show');");
    $this->assertJsCondition("document.querySelector('.popover-content, .popover-body') !== null");
    $popover = $session->evaluateScript("document.querySelector('.popover-content, .popover-body').innerHTML");
    $this->assertStringContainsString('ok', $popover);
    $this->assertStringNotContainsString('onerror', $popover);

    // The onerror handler must never have run in either case.
    $this->assertSame(0, $session->evaluateScript('window.xssFired || 0'));
  }

}
