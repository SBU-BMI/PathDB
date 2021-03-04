<?php

declare(strict_types = 1);

namespace Drupal\authorization\Provider;

use Drupal\authorization\Plugin\ConfigurableAuthorizationPluginInterface;
use Drupal\user\UserInterface;

/**
 * Defines an interface for Authorization provider plugins.
 */
interface ProviderInterface extends ConfigurableAuthorizationPluginInterface {

  /**
   * Provider-side filtering.
   *
   * @param array $proposals
   *   Available proposals.
   * @param array $providerMapping
   *   What the proposal should be filtered against in the provider.
   *
   * @return array
   *   Filtered proposals.
   */
  public function filterProposals(array $proposals, array $providerMapping): array;

  /**
   * Get the proposals for this users.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to act upon.
   *
   * @return array
   *   Relevant proposals.
   */
  public function getProposals(UserInterface $user): array;

  /**
   * Sanitize proposals.
   *
   * @param array $proposals
   *   Raw proposals.
   *
   * @return array
   *   Processed proposals.
   */
  public function sanitizeProposals(array $proposals): array;

  /**
   * Provides sync on logon.
   *
   * @return bool
   *   Sync on logon supported.
   */
  public function isSyncOnLogonSupported(): bool;

  /**
   * Provides revocation.
   *
   * @return bool
   *   Revocation supported.
   */
  public function revocationSupported(): bool;

}
