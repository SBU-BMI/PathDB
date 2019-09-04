<?php

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
  public function filterProposals(array $proposals, array $providerMapping);

  /**
   * Get the proposals for this users.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to act upon.
   *
   * @return array
   *   Relevant proposals.
   *
   * @throws \Drupal\authorization\AuthorizationSkipAuthorization
   */
  public function getProposals(UserInterface $user);

  /**
   * Sanitize proposals.
   *
   * @param array $proposals
   *   Raw proposals.
   *
   * @return array
   *   Processed proposals.
   */
  public function sanitizeProposals(array $proposals);

  /**
   * Provides sync on logon.
   *
   * @return bool
   *   Sync on logon supported.
   */
  public function isSyncOnLogonSupported();

  /**
   * Provides revocation.
   *
   * @return bool
   *   Revocation supported.
   */
  public function revocationSupported();

}
