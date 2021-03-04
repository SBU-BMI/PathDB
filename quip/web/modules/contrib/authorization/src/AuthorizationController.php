<?php

declare(strict_types = 1);

namespace Drupal\authorization;

use Drupal\authorization\Entity\AuthorizationProfile;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * Authorization controller.
 */
class AuthorizationController {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;


  /**
   * The user to act upon.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * The human-readable list of profiles processed.
   *
   * Used for optional output in the global authorizations settings.
   *
   * @var array
   */
  protected $processedAuthorizations = [];

  /**
   * Constructs a new AuthorizationController object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerInterface $logger_channel_authorization
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_channel_authorization;
  }

  /**
   * Set the user.
   *
   * We pass in the user by hand so we can act on the provisional Drupal
   * user object we have available during login and other operations.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to act upon.
   */
  public function setUser(UserInterface $user) {
    $this->user = $user;
  }

  /**
   * Process a specific profile.
   *
   * Saves the user account.
   *
   * @param string|int $profile_id
   *   Authorization profile to act upon.
   */
  public function setIndividualProfile($profile_id): void {
    /** @var \Drupal\authorization\Entity\AuthorizationProfile $profile */
    $profile = $this->entityTypeManager->getStorage('authorization_profile')->load($profile_id);
    if ($profile) {
      $this->processAuthorizations($profile, TRUE);
    }
    else {
      $this->logger->error('Profile @profile could not be loaded.', ['@profile' => $profile_id]);
    }
  }

  /**
   * Fetch and process all available profiles.
   *
   * Saves the user account.
   */
  public function setAllProfiles(): void {
    $queryResults = $this->entityTypeManager
      ->getStorage('authorization_profile')
      ->getQuery()
      ->execute();
    foreach ($queryResults as $key => $value) {
      $this->setIndividualProfile($key);
    }
  }

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
  public function queryIndividualProfile(string $profile_id): void {
    /** @var \Drupal\authorization\Entity\AuthorizationProfile $profile */
    $profile = $this->entityTypeManager->getStorage('authorization_profile')->load($profile_id);
    if ($profile) {
      $this->processAuthorizations($profile, FALSE);
    }
    else {
      $this->logger->error('Profile @profile could not be loaded.', ['@profile' => $profile_id]);
    }

  }

  /**
   * Fetch and query all available profiles.
   *
   * This does *not* save the user account.
   *
   * @see queryIndividualProfile()
   */
  public function queryAllProfiles(): void {
    $queryResults = $this->entityTypeManager->getStorage('authorization_profile')->getQuery()->execute();
    foreach ($queryResults as $key => $value) {
      $this->queryIndividualProfile($key);
    }
  }

  /**
   * Process Authorizations.
   *
   * @param \Drupal\authorization\Entity\AuthorizationProfile $profile
   *   The profile to act upon.
   * @param bool $save_user
   *   Save the user in the end.
   */
  private function processAuthorizations(AuthorizationProfile $profile, $save_user): void {
    if ($profile->checkConditions()) {
      $this->processedAuthorizations[] = $profile->grantsAndRevokes($this->user, $save_user);
    }
  }

  /**
   * Returns list of all authorizations, which were processed.
   *
   * @return AuthorizationResponse[]
   *   Authorizations by human-readable label.
   */
  public function getProcessedAuthorizations(): array {
    return $this->processedAuthorizations;
  }

  /**
   * Clear processed authorizations.
   *
   * If the service is called multiple times (e.g. for testing with query(),
   * instead of set()) this allows one to clear the list of processed
   * authorizations.
   */
  public function clearAuthorizations(): void {
    $this->processedAuthorizations = [];
  }

}
