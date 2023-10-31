<?php

declare(strict_types = 1);

namespace Drupal\authorization;

use Drupal\user\UserInterface;

/**
 * Authorization Service interface.
 */
interface AuthorizationServiceInterface {

  /**
   * Set the user.
   *
   * We pass in the user by hand so we can act on the provisional Drupal
   * user object we have available during login and other operations.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to act upon.
   */
  public function setUser(UserInterface $user): void;

  /**
   * Get the user.
   *
   * @return \Drupal\user\UserInterface
   *   The user to act upon.
   */
  public function getUser(): UserInterface;

  /**
   * Process a specific profile.
   *
   * Saves the user account.
   *
   * @param string|int $profile_id
   *   Authorization profile to act upon.
   */
  public function setIndividualProfile($profile_id): void;

  /**
   * Fetch and process all available profiles.
   *
   * Saves the user account.
   */
  public function setAllProfiles(): void;

  /**
   * Query a specific profile.
   *
   * This does *not* save the user account. We need this to simulate granting
   * to know that in some modes we want to abort any further actions
   * (e.g. no valid proposals in exclusive mode and deny access set).
   *
   * @param string $profile_id
   *   Authorization profile to act upon.
   */
  public function queryIndividualProfile(string $profile_id): void;

  /**
   * Fetch and query all available profiles.
   *
   * This does *not* save the user account.
   *
   * @see queryIndividualProfile()
   */
  public function queryAllProfiles(): void;

  /**
   * Returns list of all authorizations, which were processed.
   *
   * @return AuthorizationResponse[]
   *   Authorizations by human-readable label.
   */
  public function getProcessedAuthorizations(): array;

  /**
   * Clear processed authorizations.
   *
   * If the service is called multiple times (e.g. for testing with query(),
   * instead of set()) this allows one to clear the list of processed
   * authorizations.
   */
  public function clearAuthorizations(): void;

}
