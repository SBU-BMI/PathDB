<?php

namespace Drupal\hide_revision_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextareaWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'hide_revision_field_log_widget' widget.
 *
 * @FieldWidget(
 *   id = "hide_revision_field_log_widget",
 *   label = @Translation("Revision Log Widget"),
 *   field_types = {
 *     "string_long"
 *   }
 * )
 */
class RevisionLogWidget extends StringTextareaWidget {

  /**
   * Current user.
   */
  protected AccountProxyInterface $user;

  /**
   * Constructs a RevisionLogWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Session\AccountProxyInterface $user
   *   The current user.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, AccountProxyInterface $user) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->user = $user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'show' => TRUE,
      'default' => '',
      'permission_based' => FALSE,
      'allow_user_settings' => TRUE,
      'hide_revision' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $settings = $this->getSettings();
    $element['show'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show'),
      '#default_value' => $settings['show'],
      '#description' => $this->t('Show field by default.'),
    ];
    $element['allow_user_settings'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow user specific configuration.'),
      '#default_value' => $settings['allow_user_settings'],
      '#description' => $this->t('Allow users to configure their own default value/display of the revision log field on their profile pages.'),
    ];
    $element['permission_based'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display Based on Permissions'),
      '#default_value' => $settings['permission_based'],
      '#description' => $this->t('Show field if user has permission "%perm: Customize revision logs".', [
        '%perm' => $this->fieldDefinition->getTargetEntityTypeId(),
      ]),
    ];
    $element['hide_revision'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide whole revision tab'),
      '#default_value' => $settings['hide_revision'],
      '#description' => $this->t('Hide the whole revision tab, otherwise only the revision log message field is hidden'),
      '#states' => [
        'visible' => [
          [':input[name*="settings][show"]' => ['unchecked' => TRUE]],
        ],
      ],
    ];
    $element['default'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default'),
      '#default_value' => $settings['default'],
      '#description' => $this->t('Default value for revision log.'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $settings = $this->getSettings();

    if ($settings['show']) {
      $summary[] = $this->t('Shown by default');
    }
    else {
      $summary[] = $this->t('Hidden by default');
    }
    if ($settings['hide_revision'] && !$settings['show']) {
      $summary[] = $this->t('Hide whole revision tab');
    }
    if ($settings['default']) {
      $summary[] = $this->t('Default value: %default', [
        '%default' => $settings['default'],
      ]);
    }
    if ($settings['allow_user_settings']) {
      $summary[] = $this->t('Users allowed to customize their default');
    }
    if ($settings['permission_based']) {
      $summary[] = $this->t('Show if user has permission');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $settings = $this->getSettings();
    if ($settings['default']) {
      $element['value']['#default_value'] = $settings['default'];
    }

    $show = $settings['show'];

    if ($settings['permission_based']) {
      if ($this->user->hasPermission('access revision field')) {
        $show = TRUE;
      }
      else {
        $show = FALSE;
      }
    }

    // Check for user level personalization.
    if ($settings['allow_user_settings'] && $this->user->hasPermission('administer revision field personalization')) {
      $form_object = $form_state->getFormObject();
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */

      $entity = NULL;
      // Get entity from an inline entity form or a standard ContentEntityForm.
      if (!empty($form['#type']) && $form['#type'] === 'inline_entity_form' && !empty($form['#entity'])) {
        $entity = $form['#entity'];
      }
      elseif (!empty($form['#type']) && $form['#type'] === 'container') {
        $complete_form = $form_state->getCompleteForm();
        if (!empty($complete_form['widget']['inline_entity_form']['#entity'])) {
          $entity = $complete_form['widget']['inline_entity_form']['#entity'];
        }
      }
      elseif (method_exists($form_object, 'getEntity')) {
        $entity = $form_object->getEntity();
      }
      else {
        $entity = $items->getEntity();
      }

      if ($entity !== NULL) {
        if (empty($form_state->get('langcode'))) {
          $form_state->set('langcode', $entity->language()->getId());
        }
        $user = User::load($this->user->id());
        if ($user) {
          $user_settings = [];
          $user_settings_raw = $user->get('revision_log_settings')->value;
          if ($user_settings_raw) {
            $user_settings = unserialize($user_settings_raw, ['allowed_classes' => FALSE]);
          }
          if (isset($user_settings[$entity->getEntityType()->id()][$entity->bundle()])) {
            $show = $user_settings[$entity->getEntityType()->id()][$entity->bundle()];
          }
        }
      }
    }

    if (!$show) {
      $element['value']['#type'] = 'hidden';
      $element['value']['#hide_revision'] = $settings['hide_revision'];
    }
    return $element;
  }

}
