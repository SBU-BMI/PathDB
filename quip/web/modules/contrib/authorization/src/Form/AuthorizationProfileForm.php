<?php

declare(strict_types = 1);

namespace Drupal\authorization\Form;

use Drupal\authorization\Entity\AuthorizationProfile;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\authorization\Provider\ProviderPluginManager;
use Drupal\authorization\Consumer\ConsumerPluginManager;

/**
 * Authorization profile form.
 *
 * @package Drupal\authorization\Form
 */
class AuthorizationProfileForm extends EntityForm {

  /**
   * The provider plugin manager.
   *
   * @var \Drupal\authorization\Provider\ProviderPluginManager
   */
  protected $providerPluginManager;

  /**
   * The consumer plugin maanger.
   *
   * @var \Drupal\authorization\Consumer\ConsumerPluginManager
   */
  protected $consumerPluginManager;

  /**
   * The provider in use.
   *
   * @var string
   */
  protected $provider;

  /**
   * The consumer in use.
   *
   * @var string
   */
  protected $consumer;

  /**
   * Storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * Constructs a AuthorizationProfileForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\authorization\Provider\ProviderPluginManager $provider_plugin_manager
   *   The Provider plugin manager.
   * @param \Drupal\authorization\Consumer\ConsumerPluginManager $consumer_plugin_manager
   *   The Consumer plugin manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ProviderPluginManager $provider_plugin_manager,
    ConsumerPluginManager $consumer_plugin_manager
  ) {
    $this->storage = $entity_type_manager->getStorage('authorization_profile');
    $this->providerPluginManager = $provider_plugin_manager;
    $this->consumerPluginManager = $consumer_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.authorization.provider'),
      $container->get('plugin.manager.authorization.consumer')
    );
  }

  /**
   * Get the authorization profile.
   *
   * @return \Drupal\authorization\Entity\AuthorizationProfile
   *   Authorization profile.
   */
  public function getEntity(): AuthorizationProfile {
    return parent::getEntity();
  }

  /**
   * Retrieves the Provider plugin manager.
   *
   * @return \Drupal\authorization\Provider\ProviderPluginManager
   *   The Provider plugin manager.
   */
  protected function getProviderPluginManager(): ProviderPluginManager {
    return $this->providerPluginManager;
  }

  /**
   * Retrieves the Consumer plugin manager.
   *
   * @return \Drupal\authorization\Consumer\ConsumerPluginManager
   *   The Consumer plugin manager.
   */
  protected function getConsumerPluginManager(): ConsumerPluginManager {
    return $this->consumerPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    $this->buildEntityForm($form, $form_state);
    // Skip adding the plugin config forms if we cleared the server form due to
    // an error.
    if ($form) {
      $this->buildProviderConfigForm($form, $form_state);
      $this->buildConsumerConfigForm($form, $form_state);
      $this->buildConditionsForm($form, $form_state);
      $this->buildMappingForm($form, $form_state);
      $form['#prefix'] = "<div id='authorization-profile-form'>";
      $form['#suffix'] = "</div>";
    }

    return $form;
  }

  /**
   * Builds the form for the basic server properties.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function buildEntityForm(array &$form, FormStateInterface $form_state): void {
    $authorization_profile = $this->getEntity();

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Profile name'),
      '#maxlength' => 255,
      '#default_value' => $authorization_profile->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $authorization_profile->id(),
      '#machine_name' => [
        'exists' => '\Drupal\authorization\Entity\AuthorizationProfile::load',
      ],
      '#disabled' => !$authorization_profile->isNew(),
    ];

    /* You will need additional form elements for your custom properties. */
    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $authorization_profile->get('status'),
    ];

    $provider_options = $this->getProviderOptions();
    if ($provider_options) {
      if (count($provider_options) === 1) {
        $authorization_profile->set('provider', key($provider_options));
      }

      $form['provider'] = [
        '#type' => 'radios',
        '#title' => $this->t('Provider'),
        '#options' => $provider_options,
        '#default_value' => $authorization_profile->getProviderId(),
        '#required' => TRUE,
        '#ajax' => [
          'callback' => [get_class($this), 'buildAjaxProviderConfigForm'],
          'wrapper' => 'authorization-profile-form',
          'method' => 'replace',
          'effect' => 'fade',
        ],
      ];
    }
    else {
      $this->messenger()->addError($this->t('There are no provider plugins available. You will need to install and enable something like the LDAP Authorization Provider module that ships with LDAP.'));
      $form['#access'] = FALSE;
      $form['#markup'] = $this->t('Authorization profile cannot be created.');
      $form['#cache'] = [
        'tags' => [],
        'contexts' => [],
        'max-age' => 0,
      ];
    }

    $consumer_options = $this->getConsumerOptions();
    if ($consumer_options) {
      if (count($consumer_options) == 1) {
        $authorization_profile->set('consumer', key($consumer_options));
      }

      $form['consumer'] = [
        '#type' => 'radios',
        '#title' => $this->t('Consumer'),
        '#options' => $consumer_options,
        '#default_value' => $authorization_profile->getConsumerId(),
        '#required' => TRUE,
        '#ajax' => [
          'callback' => [get_class($this), 'buildAjaxConsumerConfigForm'],
          'wrapper' => 'authorization-profile-form',
          'method' => 'replace',
          'effect' => 'fade',
        ],
      ];
    }
    else {
      $this->messenger()->addError($this->t('There are no consumer plugins available. You can enable the Authorization Drupal Roles submodule to provide integration with core user roles or write your own using that as a template.'));
      $form['#access'] = FALSE;
      $form['#markup'] = $this->t('Authorization profile cannot be created.');
      $form['#cache'] = [
        'tags' => [],
        'contexts' => [],
        'max-age' => 0,
      ];
    }
  }

  /**
   * Returns all available Provider plugins, as an options list.
   *
   * @return string[]
   *   An associative array mapping Provider plugin IDs to their (HTML-escaped)
   *   labels.
   */
  protected function getProviderOptions(): array {
    $options = [];
    foreach ($this->getProviderPluginManager()->getDefinitions() as $plugin_id => $plugin_definition) {
      $options[$plugin_id] = Html::escape($plugin_definition['label']);
    }
    return $options;
  }

  /**
   * Builds the provider-specific configuration form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  private function buildProviderConfigForm(array &$form, FormStateInterface $form_state): void {
    $authorization_profile = $this->getEntity();

    $form['provider_config'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'authorization-provider-config-form',
      ],
      '#tree' => TRUE,
    ];

    if ($authorization_profile->hasValidProvider()) {
      $provider = $authorization_profile->getProvider();
      if (($provider_form = $provider->buildConfigurationForm([], $form_state))) {
        // If the provider plugin changed, notify the user.
        if (!empty($form_state->getValues()['provider']) && count($this->getProviderOptions()) > 1) {
          $this->messenger()->addWarning($this->t('You changed the provider, please review its configuration.'));
        }

        // Modify the provider plugin configuration container element.
        $form['provider_config']['#type'] = 'details';
        $form['provider_config']['#title'] = $this->t('Configure %plugin provider', ['%plugin' => $provider->label()]);
        $form['provider_config']['#description'] = $provider->getDescription();
        $form['provider_config']['#open'] = TRUE;
        // Attach the provider plugin configuration form.
        $form['provider_config'] += $provider_form;
      }
    }
    // Only notify the user of a missing provider plugin if we're editing an
    // existing server.
    elseif (!$authorization_profile->isNew()) {
      $this->messenger()->addError($this->t('The provider plugin is missing or invalid.'));
    }
  }

  /**
   * Builds the consumer-specific configuration form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  private function buildConsumerConfigForm(array &$form, FormStateInterface $form_state): void {
    $authorization_profile = $this->getEntity();

    $form['consumer_config'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'authorization-consumer-config-form',
      ],
      '#tree' => TRUE,
    ];

    if ($authorization_profile->hasValidConsumer()) {
      $consumer = $authorization_profile->getConsumer();
      if (($consumer_form = $consumer->buildConfigurationForm([], $form_state))) {
        // If the consumer plugin changed, notify the user.
        if (!empty($form_state->getValues()['consumer']) && count($this->getConsumerOptions()) > 1) {
          $this->messenger()->addWarning($this->t('You changed the consumer, please review its configuration.'));
        }

        // Modify the consumer plugin configuration container element.
        $form['consumer_config']['#type'] = 'details';
        $form['consumer_config']['#title'] = $this->t('Configure %plugin consumer', ['%plugin' => $consumer->label()]);
        $form['consumer_config']['#description'] = $consumer->getDescription();
        $form['consumer_config']['#open'] = TRUE;
        // Attach the consumer plugin configuration form.
        $form['consumer_config'] += $consumer_form;
      }
    }
    // Only notify the user of a missing consumer plugin if we're editing an
    // existing server.
    elseif (!$authorization_profile->isNew()) {
      $this->messenger()->addError($this->t('The consumer plugin is missing or invalid.'));
    }
  }

  /**
   * Returns all available Consumer plugins, as an options list.
   *
   * @return string[]
   *   An associative array mapping Consumer plugin IDs to their (HTML-escaped)
   *   labels.
   */
  protected function getConsumerOptions(): array {
    $options = [];
    foreach ($this->getConsumerPluginManager()->getDefinitions() as $plugin_id => $plugin_definition) {
      $options[$plugin_id] = Html::escape($plugin_definition['label']);
    }
    return $options;
  }

  /**
   * Handles switching the selected provider plugin.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Returns the form.
   */
  public static function buildAjaxProviderConfigForm(array $form, FormStateInterface $form_state): array {
    // The work is already done in form(), where we rebuild the entity according
    // to the current form values and then create the provider configuration
    // form based on that. So we just need to return the relevant part of the
    // form here.
    return $form;
  }

  /**
   * Handles switching the selected provider plugin.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Returns the provider mappings.
   */
  public static function buildAjaxProviderRowForm(array $form, FormStateInterface $form_state): array {
    return $form['provider_mappings'];
  }

  /**
   * Handles switching the selected consumer plugin.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Returns the form.
   */
  public static function buildAjaxConsumerConfigForm(array $form, FormStateInterface $form_state): array {
    // The work is already done in form(), where we rebuild the entity according
    // to the current form values and then create the consumer configuration
    // form based on that. So we just need to return the relevant part of the
    // form here.
    return $form;
  }

  /**
   * Handles switching the selected consumer plugin.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Returns the consumer mappings in the form.
   */
  public static function buildAjaxConsumerRowForm(array $form, FormStateInterface $form_state): array {
    return $form['consumer_mappings'];
  }

  /**
   * Build the conditions form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  private function buildConditionsForm(array &$form, FormStateInterface $form_state): void {
    $authorization_profile = $this->getEntity();

    if (!$authorization_profile->hasValidProvider() || !$authorization_profile->hasValidConsumer()) {
      return;
    }
    if (!property_exists($this, 'provider') || !$this->provider) {
      $this->provider = $authorization_profile->getProvider();
    }
    if (!property_exists($this, 'consumer') || !$this->consumer) {
      $this->consumer = $authorization_profile->getConsumer();
    }

    $tokens = [];

    $tokens += $authorization_profile->getProvider()->getTokens();
    $tokens += $authorization_profile->getConsumer()->getTokens();

    $form['conditions'] = [
      '#type' => 'details',
      '#title' => $this->t('Configure conditions'),
      '#open' => TRUE,
    ];

    $synchronization_modes = [];
    if ($this->provider->isSyncOnLogonSupported()) {
      $synchronization_modes['user_logon'] = $this->t('When a user logs on via <em>@provider_name</em>.', $tokens);
    }

    $form['conditions']['synchronization_modes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('When should <em>@consumer_name</em> be granted/revoked from a user?', $tokens),
      '#options' => $synchronization_modes,
      '#default_value' => $authorization_profile->get('synchronization_modes') ? $authorization_profile->get('synchronization_modes') : [],
      '#description' => '',
    ];

    $synchronization_actions = [];

    if ($this->provider->revocationSupported()) {
      $synchronization_actions['revoke_provider_provisioned'] = $this->t('Revoke <em>@consumer_name</em> grants previously granted by <em>@provider_name</em> in this profile.', $tokens);
    }

    if ($this->consumer->consumerTargetCreationAllowed()) {
      $synchronization_actions['create_consumers'] = $this->t('Create <em>@consumer_name</em> targets if they do not exist.', $tokens);
    }

    $form['conditions']['synchronization_actions'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('What actions would you like performed when <em>@consumer_name</em> are granted/revoked from a user?', $tokens),
      '#options' => $synchronization_actions,
      '#default_value' => $authorization_profile->get('synchronization_actions') ? $authorization_profile->get('synchronization_actions') : [],
    ];
  }

  /**
   * Build the mapping form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  private function buildMappingForm(array &$form, FormStateInterface $form_state): void {
    $authorization_profile = $this->getEntity();

    if (($authorization_profile->hasValidProvider() || $form_state->getValue('provider')) &&
      ($authorization_profile->hasValidConsumer()  || $form_state->getValue('consumer'))) {

      $provider = $authorization_profile->getProvider();
      $consumer = $authorization_profile->getConsumer();

      $tokens = [];
      $tokens += $provider->getTokens();
      $tokens += $consumer->getTokens();

      $form['mappings'] = [
        '#type' => 'table',
        '#responsive' => TRUE,
        '#weight' => 100,
        '#title' => $this->t('Configure mapping from @provider_name to @consumer_name', $tokens),
        '#header' => [
          $provider->label(),
          $consumer->label(),
          $this->t('Delete'),
        ],
        '#prefix' => '<div id="authorization-mappings-wrapper">',
        '#suffix' => '</div>',
      ];

      $mappings_fields = $form_state->get('mappings_fields');
      if (empty($mappings_fields)) {
        $count_current_mappings = max(count($authorization_profile->getProviderMappings()), count($authorization_profile->getConsumerMappings()));
        $mappings_fields = ($count_current_mappings > 0) ? $count_current_mappings - 1 : 1;
        $form_state->set('mappings_fields', $mappings_fields);
      }

      for ($row_key = 0; $row_key <= $mappings_fields; $row_key++) {
        $form['mappings'][$row_key]['provider_mappings'] = $provider->buildRowForm($form, $form_state, $row_key);
        $form['mappings'][$row_key]['consumer_mappings'] = $consumer->buildRowForm($form, $form_state, $row_key);
        $form['mappings'][$row_key]['delete'] = [
          '#type' => 'checkbox',
          '#default_value' => 0,
        ];
      }

      $form['mappings'][]['mappings_add_another'] = [
        '#type' => 'submit',
        '#value' => $this->t('Add Another'),
        '#submit' => ['::mappingsAddAnother'],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => '::mappingsAjaxCallback',
          'wrapper' => 'authorization-mappings-wrapper',
        ],
        '#weight' => 103,
        '#wrapper_attributes' => ['colspan' => 3],
      ];

      $form['mappings_provider_help'] = [
        '#type' => 'markup',
        '#markup' => $provider->buildRowDescription($form, $form_state),
        '#weight' => 101,
      ];

      $form['mappings_consumer_help'] = [
        '#type' => 'markup',
        '#markup' => $consumer->buildRowDescription($form, $form_state),
        '#weight' => 102,
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $authorization_profile = $this->getEntity();

    // Only when the profile is new. Afterward we can't change provider.
    if ($authorization_profile->getProviderId() !== $form_state->getValues()['provider']) {
      $input = $form_state->getUserInput();
      $input['provider_config'] = [];
      $form_state->set('input', $input);
    }
    elseif ($form['provider_config']['#type'] === 'details' && $authorization_profile->hasValidProvider()) {
      $provider_form_state = new SubFormState($form_state, ['provider_config']);
      $authorization_profile->getProvider()->validateConfigurationForm($form['provider_config'], $provider_form_state);
    }

    // Only when the profile is new. Afterward we can't change consumer.
    if ($authorization_profile->getConsumerId() !== $form_state->getValues()['consumer']) {
      $input = $form_state->getUserInput();
      $input['consumer_config'] = [];
      $form_state->set('input', $input);
    }
    elseif ($form['consumer_config']['#type'] === 'details' && $authorization_profile->hasValidConsumer()) {
      $consumer_form_state = new SubFormState($form_state, ['consumer_config']);
      $authorization_profile->getConsumer()->validateConfigurationForm($form['consumer_config'], $consumer_form_state);
    }

    if ($form_state->getValue('mappings')) {
      $mappings_form_state = new SubFormState($form_state, ['mappings']);
      $authorization_profile->getConsumer()->validateRowForm($form['mappings'], $mappings_form_state);
      $authorization_profile->getProvider()->validateRowForm($form['mappings'], $mappings_form_state);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $authorization_profile = $this->getEntity();

    // Check before loading the provider plugin so we don't throw an exception.
    if ($form['provider_config']['#type'] === 'details' && $authorization_profile->hasValidProvider()) {
      $provider_form_state = new SubFormState($form_state, ['provider_config']);
      $authorization_profile->getProvider()->submitConfigurationForm($form['provider_config'], $provider_form_state);
    }
    // Check before loading the consumer plugin so we don't throw an exception.
    if ($form['consumer_config']['#type'] === 'details' && $authorization_profile->hasValidConsumer()) {
      $consumer_form_state = new SubFormState($form_state, ['consumer_config']);
      $authorization_profile->getConsumer()->submitConfigurationForm($form['consumer_config'], $consumer_form_state);
    }

    if ($form['mappings']) {
      $mappings_form_state = new SubFormState($form_state, ['mappings']);
      $authorization_profile->getConsumer()->submitRowForm($form['mappings'], $mappings_form_state);
      $authorization_profile->getProvider()->submitRowForm($form['mappings'], $mappings_form_state);

      $values = $form_state->getValues();

      $provider_mappings = $this->extractArrayByName($values['mappings'], 'provider_mappings');
      $consumer_mappings = $this->extractArrayByName($values['mappings'], 'consumer_mappings');

      foreach ($values['mappings'] as $key => $value) {
        if (empty($value) || $value['delete'] == 1) {
          unset($provider_mappings[$key]);
          unset($consumer_mappings[$key]);
        }
      }

      if ($provider_mappings && $consumer_mappings) {
        $authorization_profile->setProviderMappings(array_values($provider_mappings));
        $authorization_profile->setConsumerMappings(array_values($consumer_mappings));
      }
    }

    return $authorization_profile;
  }

  /**
   * Transform the array keyed by row to a separate array for each consumer.
   *
   * @param array $data
   *   Source data from form.
   * @param string $name
   *   Which provisioner to filter by.
   *
   * @return array
   *   Transformed array.
   */
  private function extractArrayByName(array $data, $name): array {
    $mapping = [];
    foreach ($data as $value) {
      if (isset($value[$name])) {
        $mapping[] = $value[$name];
      }
    }
    return $mapping;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): void {
    $authorization_profile = $this->entity;
    $status = $authorization_profile->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Created the %label Authorization profile.', [
          '%label' => $authorization_profile->label(),
        ]));
        break;

      default:
        $this->messenger()->addStatus($this->t('Saved the %label Authorization profile.', [
          '%label' => $authorization_profile->label(),
        ]));
    }
    $form_state->setRedirectUrl($authorization_profile->toUrl('collection'));
  }

  /**
   * Ajax Callback for the form.
   *
   * @param array $form
   *   The form being passed in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element we are changing via ajax
   */
  public function mappingsAjaxCallback(array &$form, FormStateInterface $form_state): array {
    return $form['mappings'];
  }

  /**
   * Functionality for our ajax callback.
   *
   * @param array $form
   *   The form being passed in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state, passed by reference so we can modify.
   */
  public function mappingsAddAnother(array &$form, FormStateInterface $form_state): void {
    $form_state->set('mappings_fields', ($form_state->get('mappings_fields') + 1));
    $form_state->setRebuild();
  }

}
