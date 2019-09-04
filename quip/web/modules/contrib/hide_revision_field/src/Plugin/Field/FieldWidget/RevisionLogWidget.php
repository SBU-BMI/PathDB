<?php

namespace Drupal\hide_revision_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextareaWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxy;
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
class RevisionLogWidget extends StringTextareaWidget implements ContainerFactoryPluginInterface {

  protected $user;

  /**
   * Create the widget instance.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The symfony container.
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The the plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return \Drupal\Core\Plugin\ContainerFactoryPluginInterface|\Drupal\hide_revision_field\Plugin\Field\FieldWidget\RevisionLogWidget
   *   The widget.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('current_user')
    );
  }

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
   * @param \Drupal\Core\Session\AccountProxy $user
   *   The current user.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, AccountProxy $user) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->user = $user;
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
      '#title' => t('Show'),
      '#default_value' => $settings['show'],
      '#description' => $this->t('Show field by default.'),
    ];
    $element['allow_user_settings'] = [
      '#type' => 'checkbox',
      '#title' => t('Allow user specific configuration.'),
      '#default_value' => $settings['allow_user_settings'],
      '#description' => $this->t('Allow users to configure their own default value/display of the revision log field on their profile pages.'),
    ];
    $element['permission_based'] = [
      '#type' => 'checkbox',
      '#title' => t('Display Based on Permissions'),
      '#default_value' => $settings['permission_based'],
      '#description' => $this->t('Show field if user has permission "%perm: Customize revision logs".', [
        '%perm' => $this->fieldDefinition->getTargetEntityTypeId(),
      ]),
    ];
    $element['default'] = [
      '#type' => 'textfield',
      '#title' => t('Default'),
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
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $form_state->getFormObject()->getEntity();
      $user_settings = unserialize(User::load($this->user->id())->get('revision_log_settings')->value);
      if (isset($user_settings[$entity->getEntityType()->id()][$entity->bundle()])) {
        $show = $user_settings[$entity->getEntityType()->id()][$entity->bundle()];
      }
    }

    if (!$show) {
      $element['value']['#type'] = 'hidden';
    }
    return $element;
  }

}
