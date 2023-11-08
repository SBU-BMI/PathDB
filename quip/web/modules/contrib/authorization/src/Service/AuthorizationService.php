<?php

declare(strict_types=1);

namespace Drupal\authorization\Service;

use Drupal\authorization\AuthorizationServiceInterface;
use Drupal\authorization\Entity\AuthorizationProfile;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * Authorization service.
 */
class AuthorizationService implements AuthorizationServiceInterface {

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
   * Constructs a new AuthorizationService object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerInterface $logger_channel_authorization
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_channel_authorization;
  }

  /**
   * {@inheritdoc}
   */
  public function setUser(UserInterface $user): void {
    $this->user = $user;
  }

  /**
   * {@inheritdoc}
   */
  public function getUser(): UserInterface {
    return $this->user;
  }

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function setAllProfiles(): void {
    $queryResults = $this->entityTypeManager
      ->getStorage('authorization_profile')
      ->getQuery()
      ->accessCheck(TRUE)
      ->execute();
    foreach ($queryResults as $key => $value) {
      $this->setIndividualProfile($key);
    }
  }

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function queryAllProfiles(): void {
    $queryResults = $this->entityTypeManager->getStorage('authorization_profile')
      ->getQuery()
      ->accessCheck(TRUE)
      ->execute();
    foreach ($queryResults as $key => $value) {
      $this->queryIndividualProfile($key);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getProcessedAuthorizations(): array {
    return $this->processedAuthorizations;
  }

  /**
   * {@inheritdoc}
   */
  public function clearAuthorizations(): void {
    $this->processedAuthorizations = [];
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

}
