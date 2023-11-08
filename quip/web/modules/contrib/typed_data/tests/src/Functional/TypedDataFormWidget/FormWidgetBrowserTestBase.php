<?php

namespace Drupal\Tests\typed_data\Functional\TypedDataFormWidget;

use Drupal\Core\TypedData\TypedDataTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\typed_data\Traits\BrowserTestHelpersTrait;
use Drupal\typed_data\Widget\FormWidgetManagerTrait;

/**
 * Base class that all TypedDataFormWidget tests should extend from.
 *
 * @group typed_data
 */
abstract class FormWidgetBrowserTestBase extends BrowserTestBase {
  use BrowserTestHelpersTrait;
  use FormWidgetManagerTrait;
  use TypedDataTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'typed_data',
    'typed_data_widget_test',
  ];

  /**
   * The form widget object being tested.
   *
   * @var \Drupal\typed_data\Widget\FormWidgetInterface
   */
  protected $widget;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->createUser([], NULL, TRUE));
  }

  /**
   * Create and store a widget of the required type.
   */
  protected function createWidget($widgetId): void {
    $this->widget = $this->getFormWidgetManager()->createInstance($widgetId);
  }

  /**
   * @covers ::isApplicable
   */
  public function testIsApplicable(): void {
    // Force any tests that extend this class to implement their own version
    // of this function and give a helpful explanation if it is missed.
    $this->fail('There is no implementation of the mandatory test function: ' . $this->getName());
  }

  /**
   * @covers ::form
   * @covers ::extractFormValues
   */
  public function testFormEditing(): void {
    $this->fail('There is no implementation of the mandatory test function: ' . $this->getName());
  }

  /**
   * @covers ::form
   * @covers ::flagViolations
   */
  public function testValidation(): void {
    $this->fail('There is no implementation of the mandatory test function: ' . $this->getName());
  }

}
