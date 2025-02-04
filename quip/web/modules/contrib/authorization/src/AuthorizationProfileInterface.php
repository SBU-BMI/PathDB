<?php

declare(strict_types=1);

namespace Drupal\authorization;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\authorization\Consumer\ConsumerInterface;
use Drupal\authorization\Provider\ProviderInterface;
use Drupal\user\UserInterface;

/**
 * Authorization Profile Interface.
 */
interface AuthorizationProfileInterface extends ConfigEntityInterface {

  /**
   * Get the Provider ID.
   *
   * @return string
   *   Provider ID.
   */
  public function getProviderId(): ?string;

  /**
   * Get the Consumer ID.
   *
   * @return string
   *   Consumer ID.
   */
  public function getConsumerId(): ?string;

  /**
   * Does the profile have valid providers?
   *
   * @return bool
   *   Provider valid.
   */
  public function hasValidProvider(): bool;

  /**
   * Does the consumer have valid providers?
   *
   * @return bool
   *   Consumer valid.
   */
  public function hasValidConsumer(): bool;

  /**
   * Get the active provider.
   *
   * @return \Drupal\authorization\Provider\ProviderInterface|null
   *   The active provider.
   */
  public function getProvider(): ?ProviderInterface;

  /**
   * Get the active consumer.
   *
   * @return \Drupal\authorization\Consumer\ConsumerInterface|null
   *   The active consumer.
   */
  public function getConsumer(): ?ConsumerInterface;

  /**
   * Get the configuration of the provider.
   *
   * @return array
   *   General configuration of the provider in the profile.
   */
  public function getProviderConfig(): array;

  /**
   * Get the configuration of the consumer.
   *
   * @return array
   *   General configuration of the consumer in the profile.
   */
  public function getConsumerConfig(): array;

  /**
   * Returns the currently set provider mappings.
   *
   * @return array
   *   Provider mappings.
   */
  public function getProviderMappings(): array;

  /**
   * Get the consumer mappings.
   *
   * @return array
   *   Consumer mappings.
   */
  public function getConsumerMappings(): array;

  /**
   * Set the configuration of the provider.
   *
   * Function not in use, declared by the form directly.
   *
   * @param array $provider_config
   *   Provider config to set.
   */
  public function setProviderConfig(array $provider_config): void;

  /**
   * Set the consumer configuration.
   *
   * Function not in use, declared by the form directly.
   *
   * @param array $consumer_config
   *   General configuration of the consumer in the profile.
   */
  public function setConsumerConfig(array $consumer_config): void;

  /**
   * Set the provider mappings.
   *
   * @param array $provider_mappings
   *   Provider mappings.
   */
  public function setProviderMappings(array $provider_mappings): void;

  /**
   * Set the consumer mappings.
   *
   * @param array $consumer_mappings
   *   Consumer mappings.
   */
  public function setConsumerMappings(array $consumer_mappings): void;

  /**
   * Return global tokens for output regarding this profile.
   *
   * @return array
   *   Token strings.
   */
  public function getTokens(): array;

  /**
   * Check if the profile is available.
   *
   * @return bool
   *   Profile valid.
   */
  public function checkConditions(): bool;

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
  public function grantsAndRevokes(UserInterface $user, $user_save = FALSE): AuthorizationResponse;

}
