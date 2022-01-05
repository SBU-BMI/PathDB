<?php

namespace Drupal\views_entity_form_field\Plugin\views\field;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\PluginDependencyTrait;
use Drupal\views\Entity\Render\EntityTranslationRenderTrait;
use Drupal\views\Plugin\DependentWithRemovalPluginInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Plugin\views\field\UncacheableFieldHandlerTrait;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a views form element for an entity field widget.
 *
 * @ViewsField("entity_form_field")
 */
class EntityFormField extends FieldPluginBase implements CacheableDependencyInterface, DependentWithRemovalPluginInterface {

  use EntityTranslationRenderTrait;
  use PluginDependencyTrait;
  use UncacheableFieldHandlerTrait;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type ID.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * The field type manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypeManager;

  /**
   * The field widget plugin manager.
   *
   * @var \Drupal\Core\Field\WidgetPluginManager
   */
  protected $fieldWidgetManager;

  /**
   * The loaded field widgets.
   *
   * @var \Drupal\Core\Field\WidgetInterface[]
   */
  protected $fieldWidgets;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new EditQuantity object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Field\WidgetPluginManager $field_widget_manager
   *   The field widget plugin manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, WidgetPluginManager $field_widget_manager, LanguageManagerInterface $language_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldWidgetManager = $field_widget_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.field.widget'),
      $container->get('language_manager')
    );
  }

  /**
   * Returns the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager service.
   */
  protected function getEntityTypeManager() {
    return $this->entityTypeManager;
  }

  /**
   * The field type plugin manager.
   *
   * This is loaded on-demand, since it's only needed during configuration.
   *
   * @return \Drupal\Core\Field\FieldTypePluginManagerInterface
   *   The field type plugin manager.
   */
  protected function getFieldTypeManager() {
    if (is_null($this->fieldTypeManager)) {
      $this->fieldTypeManager = \Drupal::service('plugin.manager.field.field_type');
    }
    return $this->fieldTypeManager;
  }

  /**
   * Get the entity type ID for this views field instance.
   *
   * @return string
   *   The entity type ID.
   */
  protected function getEntityTypeId() {
    if (is_null($this->entityTypeId)) {
      $this->entityTypeId = $this->getEntityType();
    }
    return $this->entityTypeId;
  }

  /**
   * Collects the definition of field.
   *
   * @param string $bundle
   *   The bundle to load the field definition for.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface|null
   *   The field definition. Null if not set.
   */
  protected function getBundleFieldDefinition($bundle = NULL) {
    $bundle = (!is_null($bundle)) ? $bundle : reset($this->definition['bundles']);
    $field_definitions = $this->entityFieldManager->getFieldDefinitions($this->getEntityTypeId(), $bundle);
    return (array_key_exists($this->definition['field_name'], $field_definitions)) ? $field_definitions[$this->definition['field_name']] : NULL;
  }

  /**
   * Returns an array of applicable widget options for a field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   *
   * @return string[]
   *   An array of applicable widget options.
   */
  protected function getPluginApplicableOptions(FieldDefinitionInterface $field_definition) {
    $options = $this->fieldWidgetManager->getOptions($field_definition->getType());
    $applicable_options = [];
    foreach ($options as $option => $label) {
      $plugin_class = DefaultFactory::getPluginClass($option, $this->fieldWidgetManager->getDefinition($option));
      if ($plugin_class::isApplicable($field_definition)) {
        $applicable_options[$option] = $label;
      }
    }
    return $applicable_options;
  }

  /**
   * Returns the default field widget ID for a specific field type.
   *
   * @param string $field_type
   *   The field type ID.
   *
   * @return null|string
   *   The default field widget ID. Null otherwise.
   */
  protected function getPluginDefaultOption($field_type) {
    $definition = $this->getFieldTypeManager()->getDefinition($field_type, FALSE);
    return ($definition && isset($definition['default_widget'])) ? $definition['default_widget'] : NULL;
  }

  /**
   * Gets a bundle-specific field widget instance.
   *
   * @param null|string $bundle
   *   The bundle to load the plugin for.
   *
   * @return null|\Drupal\Core\Field\WidgetInterface
   *   The field widget plugin if it is set. Null otherwise.
   */
  protected function getPluginInstance($bundle = NULL) {
    // Cache the created instance per bundle.
    $bundle = (!is_null($bundle)) ? $bundle : reset($this->definition['bundles']);
    if (!isset($this->fieldWidgets[$bundle]) && $field_definition = $this->getBundleFieldDefinition($bundle)) {
      // Compile options.
      $options = [
        'field_definition' => $field_definition,
        'form_mode' => 'views_view',
        'prepare' => FALSE,
        'configuration' => $this->options['plugin'],
      ];

      // Unset type if improperly set and set to prepare with default config.
      if (isset($options['configuration']['type']) && empty($options['configuration']['type'])) {
        unset($options['configuration']['type']);
        $options['prepare'] = TRUE;
      }

      // Load field widget.
      $this->fieldWidgets[$bundle] = $this->fieldWidgetManager->getInstance($options);
    }
    return $this->fieldWidgets[$bundle];
  }

  /**
   * {@inheritdoc}
   */
  protected function getLanguageManager() {
    return $this->languageManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getView() {
    return $this->view;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(
      $this->getEntityTranslationRenderer()->getCacheContexts(),
      ['user']
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $field_definition = $this->getBundleFieldDefinition();
    $field_storage_definition = $field_definition->getFieldStorageDefinition();

    return Cache::mergeTags(
      $field_definition instanceof CacheableDependencyInterface ? $field_definition->getCacheTags() : [],
      $field_storage_definition instanceof CacheableDependencyInterface ? $field_storage_definition->getCacheTags() : []
    );
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $this->dependencies = parent::calculateDependencies();

    // Add the module providing the configured field storage as a dependency.
    if (($field_definition = $this->getBundleFieldDefinition()) && $field_definition instanceof EntityInterface) {
      $this->dependencies['config'][] = $field_definition->getConfigDependencyName();
    }
    if (!empty($this->options['type'])) {
      // Add the module providing the formatter.
      $this->dependencies['module'][] = $this->fieldWidgetManager->getDefinition($this->options['type'])['provider'];

      // Add the formatter's dependencies.
      if (($formatter = $this->getPluginInstance()) && $formatter instanceof DependentPluginInterface) {
        $this->calculatePluginDependencies($formatter);
      }
    }

    return $this->dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    // See if this handler is responsible for any of the dependencies being
    // removed. If this is the case, indicate that this handler needs to be
    // removed from the View.
    $remove = FALSE;
    // Get all the current dependencies for this handler.
    $current_dependencies = $this->calculateDependencies();
    foreach ($current_dependencies as $group => $dependency_list) {
      // Check if any of the handler dependencies match the dependencies being
      // removed.
      foreach ($dependency_list as $config_key) {
        if (isset($dependencies[$group]) && array_key_exists($config_key, $dependencies[$group])) {
          // This handlers dependency matches a dependency being removed,
          // indicate that this handler needs to be removed.
          $remove = TRUE;
          break 2;
        }
      }
    }
    return $remove;
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['plugin']['contains']['hide_title']['default'] = TRUE;
    $options['plugin']['contains']['hide_description']['default'] = TRUE;
    $options['plugin']['contains']['type']['default'] = [];
    $options['plugin']['contains']['settings']['default'] = [];
    $options['plugin']['contains']['third_party_settings']['default'] = [];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $field_definition = $this->getBundleFieldDefinition();

    $form['plugin'] = [
      'type' => [
        '#type' => 'select',
        '#title' => $this->t('Widget type'),
        '#options' => $this->getPluginApplicableOptions($field_definition),
        '#default_value' => $this->options['plugin']['type'],
        '#attributes' => ['class' => ['field-plugin-type']],
        '#ajax' => [
          'url' => views_ui_build_form_url($form_state),
        ],
        '#submit' => [[$this, 'submitTemporaryForm']],
        '#executes_submit_callback' => TRUE,
      ],
      'hide_title' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Hide widget title'),
        '#default_value' => $this->options['plugin']['hide_title'],
      ],
      'hide_description' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Hide widget description'),
        '#default_value' => $this->options['plugin']['hide_description'],
      ],
      'settings_edit_form' => [],
    ];

    // Generate the settings form and allow other modules to alter it.
    if ($plugin = $this->getPluginInstance()) {
      $settings_form = $plugin->settingsForm($form, $form_state);

      // Adds the widget third party settings forms.
      $third_party_settings_form = [];
      foreach ($this->moduleHandler->getImplementations('field_widget_third_party_settings_form') as $module) {
        $third_party_settings_form[$module] = $this->moduleHandler->invoke($module, 'field_widget_third_party_settings_form', [
          $plugin,
          $field_definition,
          'views_view',
          $form,
          $form_state,
        ]);
      }

      if ($settings_form || $third_party_settings_form) {
        $form['plugin']['#cell_attributes'] = ['colspan' => 3];
        $form['plugin']['settings_edit_form'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('Widget settings'),
          '#attributes' => ['class' => ['field-plugin-settings-edit-form']],
          'settings' => $settings_form,
          'third_party_settings' => $third_party_settings_form,
        ];
        $form['#attributes']['class'][] = 'field-plugin-settings-editing';
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitFormCalculateOptions(array $options, array $form_state_options) {
    // When we change the formatter type we don't want to keep any of the
    // previous configured formatter settings, as there might be schema
    // conflict.
    unset($options['settings']);
    $options = $form_state_options + $options;
    if (!isset($options['settings'])) {
      $options['settings'] = [];
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);

    $options = &$form_state->getValue('options');
    $options['plugin']['settings'] = isset($options['plugin']['settings_edit_form']['settings']) ? array_intersect_key($options['plugin']['settings_edit_form']['settings'], $this->fieldWidgetManager->getDefaultSettings($options['plugin']['type'])) : [];
    $options['plugin']['third_party_settings'] = isset($options['plugin']['settings_edit_form']['third_party_settings']) ? $options['plugin']['settings_edit_form']['third_party_settings'] : [];
    unset($options['plugin']['settings_edit_form']);
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $row, $field = NULL) {
    return '<!--form-item-' . $this->options['id'] . '--' . $row->index . '-->';
  }

  /**
   * Form constructor for the views form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function viewsForm(array &$form, FormStateInterface $form_state) {
    $field_name = $this->definition['field_name'];

    // Initialize form values.
    $form['#cache']['max-age'] = 0;
    $form['#attached']['library'][] = 'views_entity_form_field/views_form';
    $form['#attributes']['class'][] = 'views-entity-form';
    $form['#process'][] = [$this, 'viewsFormProcess'];
    $form['#tree'] = TRUE;
    $form += ['#parents' => []];

    // Only add the buttons if there are results.
    if (!empty($this->getView()->result)) {
      $form[$this->options['id']]['#tree'] = TRUE;
      $form[$this->options['id']]['#entity_form_field'] = TRUE;
      foreach ($this->getView()->result as $row_index => $row) {
        // Initialize this row and column.
        $form[$this->options['id']][$row_index]['#parents'] = [$this->options['id'], $row_index];
        $form[$this->options['id']][$row_index]['#tree'] = TRUE;

        // Make sure there's an entity for this row (relationships can be null).
        if ($this->getEntity($row)) {
          // Load field definition based on current entity bundle.
          $entity = $this->getEntityTranslation($this->getEntity($row), $row);
          if ($entity->hasField($field_name) && $this->getBundleFieldDefinition($entity->bundle())->isDisplayConfigurable('form')) {
            $items = $entity->get($field_name)->filterEmptyItems();

            // Add widget to form and add field overrides.
            $form[$this->options['id']][$row_index][$field_name] = $this->getPluginInstance()->form($items, $form[$this->options['id']][$row_index], $form_state);
            $form[$this->options['id']][$row_index][$field_name]['#access'] = ($entity->access('update') && $items->access('edit'));
            $form[$this->options['id']][$row_index][$field_name]['#cache']['contexts'] = $entity->getCacheContexts();
            $form[$this->options['id']][$row_index][$field_name]['#cache']['tags'] = $entity->getCacheTags();
            $form[$this->options['id']][$row_index][$field_name]['#parents'] = [
              $this->options['id'],
              $row_index,
              $field_name,
            ];

            // Hide field widget title.
            if ($this->options['plugin']['hide_title']) {
              $form[$this->options['id']][$row_index][$field_name]['#attributes']['class'][] = 'views-entity-form-field-field-label-hidden';
            }

            // Hide field widget description.
            if ($this->options['plugin']['hide_description']) {
              $form[$this->options['id']][$row_index][$field_name]['#attributes']['class'][] = 'views-entity-form-field-field-description-hidden';
            }
          }
        }
      }
    }
  }

  /**
   * Processes the form, adding the submission handler to save the entities.
   *
   * @param array $element
   *   A nested array form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The processed form element.
   */
  public function viewsFormProcess(array $element, FormStateInterface $form_state) {
    $element['#submit'][] = [$this, 'saveEntities'];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function viewsFormValidate(array &$form, FormStateInterface $form_state) {
    $form_state->cleanValues();
    $this->buildEntities($form, $form_state, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function viewsFormSubmit(array &$form, FormStateInterface $form_state) {
    $form_state->cleanValues();
    $this->buildEntities($form, $form_state);
  }

  /**
   * Update entity objects based upon the submitted form values.
   *
   * @param array $form
   *   A nested array form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param bool $validate
   *   Validate the entity after extracting form values.
   */
  protected function buildEntities(array &$form, FormStateInterface $form_state, $validate = FALSE) {
    $field_name = $this->definition['field_name'];

    // Set this value back to it's relevant entity from each row.
    foreach ($this->getView()->result as $row_index => $row) {
      // Check to make sure that this entity has a relevant field.
      $entity = $this->getEntity($row);
      if ($entity && $entity->hasField($field_name) && $this->getBundleFieldDefinition($entity->bundle())->isDisplayConfigurable('form')) {
        // Get current entity field values.
        $items = $entity->get($field_name)->filterEmptyItems();

        // Extract values.
        $this->getPluginInstance($entity->bundle())->extractFormValues($items, $form[$this->options['id']][$row_index], $form_state);

        // Validate entity and add violations to field widget.
        if ($validate) {
          $violations = $items->validate();
          if ($violations->count() > 0) {
            $this->getPluginInstance($entity->bundle())->flagErrors($items, $violations, $form[$this->options['id']][$row_index], $form_state);
          }
        }
      }
    }
  }

  /**
   * Save the view's entities.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function saveEntities(array &$form, FormStateInterface $form_state) {
    // We only want to save the entity once per relationship.
    if (is_null($form_state->getTemporaryValue(['saved_relationships', $this->relationship]))) {
      $storage = $this->getEntityTypeManager()->getStorage($this->getEntityTypeId());

      $rows_saved = [];
      $rows_failed = [];

      foreach ($this->getView()->result as $row_index => $row) {
        $entity = $this->getEntity($row);

        if ($entity) {
          $entity = $this->getEntityTranslation($entity, $row);
          $original_entity = $this->getEntityTranslation($storage->loadUnchanged($entity->id()), $row);

          try {
            if ($this->entityShouldBeSaved($entity, $original_entity)) {
              $storage->save($entity);
              $rows_saved[$row_index] = $entity->label();
            }
          } catch (\Exception $exception) {
            $rows_failed[$row_index] = $entity->label();
          }
        }
      }

      // Let the user know how many entities were saved.
      $messenger = \Drupal::messenger();
      $entity_type_definition = $this->entityTypeManager->getDefinition($this->getEntityTypeId());
      $messenger->addStatus($this->formatPlural(count($rows_saved), '@count @singular_label saved.', '@count @plural_label saved.', [
        '@count' => count($rows_saved),
        '@singular_label' => $entity_type_definition->getSingularLabel(),
        '@plural_label' => $entity_type_definition->getPluralLabel(),
      ]));

      // Let the user know which entities couldn't be saved.
      if (count($rows_failed) > 0) {
        $messenger->addWarning($this->formatPlural(count($rows_failed), '@count @singular_label failed to save: @labels', '@count @plural_label failed to save: @labels', [
          '@count' => count($rows_failed),
          '@singular_label' => $entity_type_definition->getSingularLabel(),
          '@plural_label' => $entity_type_definition->getPluralLabel(),
          '@labels' => implode(', ', $rows_failed),
        ]));
      }

      // Track that this relationship has been saved.
      $form_state->setTemporaryValue(['saved_relationships', $this->relationship], TRUE);
    }
  }

  /**
   * Determines if an entity should be saved.
   *
   * @param EntityInterface $entity
   *   The possibly modified entity in question.
   * @param EntityInterface $original_entity
   *   The original unmodified entity.
   *
   * @return bool
   *   TRUE if the entity should be saved; FALSE otherwise.
   */
  protected function entityShouldBeSaved(EntityInterface $entity, EntityInterface $original_entity) {
    $save_entity = FALSE;

    foreach ($entity as $field_name => $new_field) {
      $original_field = $original_entity->get($field_name);
      if (!$new_field->equals($original_field)) {
        $save_entity = TRUE;
        break;
      }
    }
    return $save_entity;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing.
  }

}
