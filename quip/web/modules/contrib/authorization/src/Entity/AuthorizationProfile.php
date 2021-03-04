<?php

declare(strict_types = 1);

namespace Drupal\authorization\Entity;

use Drupal\authorization\consumer\ConsumerInterface;
use Drupal\authorization\provider\ProviderInterface;
use Drupal\authorization\AuthorizationResponse;
use Drupal\authorization\AuthorizationSkipAuthorization;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;

/**
 * Defines the Authorization profile entity.
 *
 * @ConfigEntityType(
 *   id = "authorization_profile",
 *   label = @Translation("Authorization profile"),
 *   handlers = {
 *     "list_builder" = "Drupal\authorization\AuthorizationProfileListBuilder",
 *     "form" = {
 *       "add" = "Drupal\authorization\Form\AuthorizationProfileForm",
 *       "edit" = "Drupal\authorization\Form\AuthorizationProfileForm",
 *       "delete" = "Drupal\authorization\Form\AuthorizationProfileDeleteForm"
 *     }
 *   },
 *   config_prefix = "authorization_profile",
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "provider",
 *     "consumer",
 *     "provider",
 *     "provider_config",
 *     "provider_mappings",
 *     "consumer",
 *     "consumer_config",
 *     "consumer_mappings",
 *     "synchronization_modes",
 *     "synchronization_actions",
 *   },
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/people/authorization/{authorization_profile}",
 *     "edit-form" = "/admin/config/people/authorization/profile/{authorization_profile}/edit",
 *     "delete-form" = "/admin/config/people/authorization/profile/{authorization_profile}/delete",
 *     "collection" = "/admin/config/people/authorization/profile"
 *   }
 * )
 */
class AuthorizationProfile extends ConfigEntityBase {

  use StringTranslationTrait;

  /**
   * The Authorization profile ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Authorization profile label.
   *
   * @var string
   */
  protected $label;

  /**
   * A description of the profile.
   *
   * @var string
   */
  protected $description;

  /**
   * The id of the Authorization provider.
   *
   * @var string
   */
  protected $provider;

  /**
   * The id of the Authorization consumer.
   *
   * @var string
   */
  protected $consumer;

  /**
   * The provider plugin configuration.
   *
   * @var array
   */
  protected $provider_config = [];

  /**
   * The provider plugin mappings.
   *
   * @var array
   */
  protected $provider_mappings = [];

  /**
   * The provider plugin instance.
   *
   * @var \Drupal\authorization\provider\ProviderInterface
   */
  protected $provider_plugin;

  /**
   * The consumer plugin configuration.
   *
   * @var array
   */
  protected $consumer_config = [];

  /**
   * The consumer plugin mappings.
   *
   * @var array
   */
  protected $consumer_mappings = [];

  /**
   * The consumer plugin instance.
   *
   * @var \Drupal\authorization\consumer\ConsumerInterface
   */
  protected $consumer_plugin;

  /**
   * The provider plugin manager.
   *
   * @var \Drupal\authorization\Provider\ProviderPluginManager
   */
  protected $provider_plugin_manager;

  /**
   * The consumer plugin maanger.
   *
   * @var \Drupal\authorization\Consumer\ConsumerPluginManager
   */
  protected $consumer_plugin_manager;


  /**
   * The authorization logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public function __construct($values, $entity_type) {
    parent::__construct($values, $entity_type);
    // Cannot inject those without moving the logic to a controller class, (see
    // https://www.drupal.org/node/2913224).
    $this->provider_plugin_manager = \Drupal::service('plugin.manager.authorization.provider');
    $this->consumer_plugin_manager = \Drupal::service('plugin.manager.authorization.consumer');
    $this->logger = \Drupal::service('logger.channel.authorization');
  }

  /**
   * Get description.
   *
   * @return string
   *   Description.
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * Get the Provider ID.
   *
   * @return string
   *   Provider ID.
   */
  public function getProviderId(): ?string {
    return $this->provider;
  }

  /**
   * Get the Consumer ID.
   *
   * @return string
   *   Consumer ID.
   */
  public function getConsumerId(): ?string {
    return $this->consumer;
  }

  /**
   * Does the profile have valid providers?
   *
   * @return bool
   *   Provider valid.
   */
  public function hasValidProvider(): bool {
    if ($this->provider_plugin_manager->getDefinition($this->getProviderId(), FALSE)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Does the consumer have valid providers?
   *
   * @return bool
   *   Consumer valid.
   */
  public function hasValidConsumer(): bool {
    if ($this->consumer_plugin_manager->getDefinition($this->getconsumerId(), FALSE)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Get the active provider.
   *
   * @return \Drupal\authorization\provider\ProviderInterface|null
   *   The active provider.
   */
  public function getProvider(): ?ProviderInterface {
    if (!$this->provider_plugin || $this->getProviderId() !== $this->provider_plugin->getPluginId()) {
      $this->loadProviderPlugin();
    }
    return $this->provider_plugin;
  }

  /**
   * Get the active consumer.
   *
   * @return \Drupal\authorization\consumer\ConsumerInterface|null
   *   The active consumer.
   */
  public function getConsumer(): ?ConsumerInterface {
    if (!$this->consumer_plugin || $this->getConsumerId() !== $this->consumer_plugin->getPluginId()) {
      $this->loadConsumerPlugin();
    }
    return $this->consumer_plugin;
  }

  /**
   * Load the provider plugin.
   */
  private function loadProviderPlugin(): void {
    $config = $this->getProviderConfig();
    $config['profile'] = $this;
    try {
      $this->provider_plugin = $this->provider_plugin_manager->createInstance($this->getProviderId(), $config);
    }
    catch (\Exception $e) {
      $this->logger->critical('The provider with ID "@provider" could not be retrieved for profile %profile.', [
        '@provider' => $this->getProviderId(),
        '%profile' => $this->label(),
      ]
      );
    }
  }

  /**
   * Load the consumer plugin.
   */
  private function loadConsumerPlugin(): void {
    $config = $this->getConsumerConfig();
    $config['profile'] = $this;
    try {
      $this->consumer_plugin = $this->consumer_plugin_manager->createInstance($this->getConsumerId(), $config);
    }
    catch (\Exception $e) {
      $this->logger->critical('The consumer with ID "@consumer" could not be retrieved for profile %profile.', [
        '@provider' => $this->getProviderId(),
        '%profile' => $this->label(),
      ]
      );
    }
  }

  /**
   * Get the configuration of the provider.
   *
   * @return array
   *   General configuration of the provider in the profile.
   */
  public function getProviderConfig(): array {
    return $this->provider_config;
  }

  /**
   * Get the configuration of the consumer.
   *
   * @return array
   *   General configuration of the consumer in the profile.
   */
  public function getConsumerConfig(): array {
    return $this->consumer_config;
  }

  /**
   * Returns the currently set provider mappings.
   *
   * @return array
   *   Provider mappings.
   */
  public function getProviderMappings(): array {
    return $this->provider_mappings;
  }

  /**
   * Get the consumer mappings.
   *
   * @return array
   *   Consumer mappings.
   */
  public function getConsumerMappings(): array {
    return $this->consumer_mappings;
  }

  /**
   * Set the configuration of the provider.
   *
   * Function not in use, declared by the form directly.
   *
   * @param array $provider_config
   *   Provider config to set.
   */
  public function setProviderConfig(array $provider_config): void {
    $this->provider_config = $provider_config;
  }

  /**
   * Set the consumer configuration.
   *
   * Function not in use, declared by the form directly.
   *
   * @param array $consumer_config
   *   General configuration of the consumer in the profile.
   */
  public function setConsumerConfig(array $consumer_config): void {
    $this->consumer_config = $consumer_config;
  }

  /**
   * Set the provider mappings.
   *
   * @param array $provider_mappings
   *   Provider mappings.
   */
  public function setProviderMappings(array $provider_mappings): void {
    $this->provider_mappings = $provider_mappings;
  }

  /**
   * Set the consumer mappings.
   *
   * @param array $consumer_mappings
   *   Consumer mappings.
   */
  public function setConsumerMappings(array $consumer_mappings): void {
    $this->consumer_mappings = $consumer_mappings;
  }

  /**
   * Return global tokens for output regarding this profile.
   *
   * @return array
   *   Token strings.
   */
  public function getTokens(): array {
    $tokens = [];
    $tokens['@profile_name'] = $this->label;
    return $tokens;
  }

  /**
   * Check if the profile is available.
   *
   * @return bool
   *   Profile valid.
   */
  public function checkConditions(): bool {

    if (!$this->get('status')) {
      return FALSE;
    }

    if (!$this->getProvider()) {
      return FALSE;
    }

    if (!$this->getConsumer()) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Perform grant and revokes.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to work on.
   * @param bool $user_save
   *   Whether to directly save the user. Note that the object itself, passed
   *   by reference, can still be save outside of this scope by later code.
   *
   * @return \Drupal\authorization\AuthorizationResponse
   *   Responses.
   */
  public function grantsAndRevokes(UserInterface $user, $user_save = FALSE): AuthorizationResponse {

    $provider = $this->getProvider();
    $consumer = $this->getConsumer();

    try {
      $proposals = $provider->getProposals($user);
    }
    catch (AuthorizationSkipAuthorization $e) {
      return new AuthorizationResponse((string) $this->t('@name (skipped)', ['@name' => $this->label]), TRUE, []);
    }

    $proposals = $provider->sanitizeProposals($proposals);

    $applied_grants = [];
    // @todo This could be made more elegant with methods on this class checking
    // for support on this and not checking here the array key directly.
    $create_consumers = $this->get('synchronization_actions')['create_consumers'] ?? FALSE;
    $revoke_provision = $this->get('synchronization_actions')['revoke_provider_provisioned'] ?? FALSE;
    foreach ($this->getProviderMappings() as $provider_key => $provider_mapping) {
      $provider_proposals = $provider->filterProposals($proposals, $provider_mapping);
      $filtered_proposals = $consumer->filterProposals($provider_proposals, $this->getConsumerMappings()[$provider_key]);

      if (!empty($filtered_proposals)) {
        foreach ($filtered_proposals as $filtered_proposal) {
          if ($create_consumers) {
            $consumer->createConsumerTarget($filtered_proposal);
          }
          $consumer->grantSingleAuthorization($user, $filtered_proposal);
          $applied_grants[$filtered_proposal] = $filtered_proposal;
        }
      }
    }

    if ($revoke_provision) {
      $consumer->revokeGrants($user, $applied_grants);
    }

    if ($user_save === TRUE) {
      $user->save();
    }

    return new AuthorizationResponse($this->label, FALSE, $applied_grants);
  }

}
