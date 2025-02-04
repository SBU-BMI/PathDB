<?php

declare(strict_types=1);

namespace Drupal\authorization\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\authorization\Consumer\ConsumerPluginManager;
use Drupal\authorization\Provider\ProviderPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Authorization profile form.
 *
 * @package Drupal\authorization\Form
 */
final class AuthorizationProfileEditForm extends AuthorizationProfileForm {

  /**
   * Constructs a AuthorizationProfileForm.
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
    ConsumerPluginManager $consumer_plugin_manager,
  ) {
    $this->storage = $entity_type_manager->getStorage('authorization_profile');
    $this->providerPluginManager = $provider_plugin_manager;
    $this->consumerPluginManager = $consumer_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.authorization.provider'),
      $container->get('plugin.manager.authorization.consumer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'authorization_profile_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    $form['#attached']['library'][] = 'authorization/profile';
    $form['#prefix'] = "<div id='authorization-profile-form'>";
    $this->buildEntityForm($form, $form_state, FALSE);
    $form['configuration'] = [
      '#type' => 'vertical_tabs',
      '#weight' => 10,
      '#default_tab' => 'conditions',
    ];
    $this->buildProviderConfigForm($form, $form_state);
    $this->buildConsumerConfigForm($form, $form_state);
    $this->buildConditionsForm($form, $form_state);
    $this->buildMappingForm($form, $form_state);

    $form['#suffix'] = "</div>";

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityForm(array &$form, FormStateInterface $form_state, $is_new): void {
    parent::buildEntityForm($form, $form_state, $is_new);
    unset($form['provider']);
    unset($form['consumer']);
  }

  /**
   * Builds the provider-specific configuration form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function buildProviderConfigForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\authorization\AuthorizationProfileInterface $authorization_profile */
    $authorization_profile = $this->getEntity();

    $form['provider_config'] = [
      '#type' => 'details',
      '#attributes' => [
        'id' => 'authorization-provider-config-form',
      ],
      '#tree' => TRUE,
      '#weight' => 0,
      '#group' => 'configuration',
    ];

    if (!$authorization_profile->hasValidProvider()) {
      return;
    }
    $provider = $authorization_profile->getProvider();
    $provider_form = $provider->buildConfigurationForm([], $form_state);
    if (empty($provider_form)) {
      return;
    }
    // Modify the provider plugin configuration container element.
    $form['provider_config']['#title'] = $this->t('Provider %plugin', ['%plugin' => $provider->label()]);
    $form['provider_config']['#description'] = $provider->getDescription();
    $form['provider_config']['#open'] = TRUE;
    // Attach the provider plugin configuration form.
    $form['provider_config'] += $provider_form;

  }

  /**
   * Builds the consumer-specific configuration form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function buildConsumerConfigForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\authorization\AuthorizationProfileInterface $authorization_profile */
    $authorization_profile = $this->getEntity();

    $form['consumer_config'] = [
      '#type' => 'details',
      '#attributes' => [
        'id' => 'authorization-consumer-config-form',
      ],
      '#tree' => TRUE,
      '#weight' => 50,
      '#group' => 'configuration',
    ];

    if (!$authorization_profile->hasValidConsumer()) {
      return;
    }

    $consumer = $authorization_profile->getConsumer();
    $consumer_form = $consumer->buildConfigurationForm([], $form_state);
    if (empty($consumer_form)) {
      return;
    }

    $form['consumer_config']['#title'] = $this->t('Consumer %plugin', ['%plugin' => $consumer->label()]);
    $form['consumer_config']['#description'] = $consumer->getDescription();
    $form['consumer_config']['#open'] = TRUE;
    // Attach the consumer plugin configuration form.
    $form['consumer_config'] += $consumer_form;
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
  protected function buildConditionsForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\authorization\AuthorizationProfileInterface $authorization_profile */
    $authorization_profile = $this->getEntity();

    $profile_is_invalid = !$authorization_profile->hasValidProvider() || !$authorization_profile->hasValidConsumer();
    if ($profile_is_invalid) {
      return;
    }

    $this->getProvider($authorization_profile);
    $this->getConsumer($authorization_profile);

    $tokens = [];

    $tokens += $authorization_profile->getProvider()->getTokens();
    $tokens += $authorization_profile->getConsumer()->getTokens();

    $form['conditions'] = [
      '#type' => 'details',
      '#title' => $this->t('Conditions'),
      '#open' => TRUE,
      '#weight' => -50,
      '#group' => 'configuration',
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
   * Get the provider.
   *
   * @param \Drupal\authorization\AuthorizationProfileInterface $authorization_profile
   *   The authorization profile.
   */
  protected function getProvider($authorization_profile): void {
    $get_provider = !property_exists($this, 'provider') || !$this->provider;
    if ($get_provider) {
      $this->provider = $authorization_profile->getProvider();
    }
  }

  /**
   * Get the consumer.
   *
   * @param \Drupal\authorization\AuthorizationProfileInterface $authorization_profile
   *   The authorization profile.
   */
  protected function getConsumer($authorization_profile): void {
    $get_consumer = !property_exists($this, 'consumer') || !$this->consumer;
    if ($get_consumer) {
      $this->consumer = $authorization_profile->getConsumer();
    }
  }

  /**
   * Build the mapping form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function buildMappingForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\authorization\AuthorizationProfileInterface $authorization_profile */
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
        '#weight' => 0,
        '#title' => $this->t('Configure mapping from @provider_name to @consumer_name', $tokens),
        '#header' => [
          $provider->label(),
          $consumer->label(),
          $this->t('Delete'),
        ],
        '#prefix' => '<div id="authorization-mappings-wrapper" class="authorization-mappings">',
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
    /** @var \Drupal\authorization\AuthorizationProfileInterface $authorization_profile */
    $authorization_profile = $this->getEntity();

    if ($authorization_profile->hasValidProvider()) {
      $provider_form_state = new SubFormState($form_state, ['provider_config']);
      $authorization_profile->getProvider()
        ->validateConfigurationForm($form['provider_config'], $provider_form_state);
    }

    if ($authorization_profile->hasValidConsumer()) {
      $consumer_form_state = new SubFormState($form_state, ['consumer_config']);
      $authorization_profile->getConsumer()
        ->validateConfigurationForm($form['consumer_config'], $consumer_form_state);
    }

    if ($form_state->getValue('mappings')) {
      $mappings_form_state = new SubFormState($form_state, ['mappings']);
      $authorization_profile->getConsumer()
        ->validateRowForm($form['mappings'], $mappings_form_state);
      $authorization_profile->getProvider()
        ->validateRowForm($form['mappings'], $mappings_form_state);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    /** @var \Drupal\authorization\AuthorizationProfileInterface */
    $authorization_profile = $this->getEntity();

    // Check before loading the provider plugin so we don't throw an exception.
    if ($authorization_profile->hasValidProvider()) {
      $provider_form_state = new SubFormState($form_state, ['provider_config']);
      $authorization_profile->getProvider()
        ->submitConfigurationForm($form['provider_config'], $provider_form_state);
    }
    // Check before loading the consumer plugin so we don't throw an exception.
    if ($authorization_profile->hasValidConsumer()) {
      $consumer_form_state = new SubFormState($form_state, ['consumer_config']);
      $authorization_profile->getConsumer()
        ->submitConfigurationForm($form['consumer_config'], $consumer_form_state);
    }

    if ($form['mappings']) {
      $mappings_form_state = new SubFormState($form_state, ['mappings']);
      $authorization_profile->getConsumer()
        ->submitRowForm($form['mappings'], $mappings_form_state);
      $authorization_profile->getProvider()
        ->submitRowForm($form['mappings'], $mappings_form_state);

      $values = $form_state->getValues();

      $provider_mappings = $this->extractArrayByName($values['mappings'], 'provider_mappings');
      $consumer_mappings = $this->extractArrayByName($values['mappings'], 'consumer_mappings');

      foreach ($values['mappings'] as $key => $value) {
        if (empty($value) || $value['delete'] == 1) {
          unset($provider_mappings[$key]);
          unset($consumer_mappings[$key]);
        }
      }
      $set_mappings = $provider_mappings && $consumer_mappings;
      if ($set_mappings) {
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
