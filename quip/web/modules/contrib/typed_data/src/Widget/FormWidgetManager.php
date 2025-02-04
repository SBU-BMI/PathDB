<?php

namespace Drupal\typed_data\Widget;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\typed_data\Attribute\TypedDataFormWidget;

/**
 * Plugin manager for form widgets.
 */
class FormWidgetManager extends DefaultPluginManager implements FormWidgetManagerInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/TypedDataFormWidget', $namespaces, $module_handler, FormWidgetInterface::class, TypedDataFormWidget::class, 'Drupal\typed_data\Annotation\FormWidget');
    $this->alterInfo('typed_data_form_widget');
    $this->setCacheBackend($cache_backend, 'typed_data_form_widget_plugins');
  }

}
