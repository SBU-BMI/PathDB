<?php

declare(strict_types = 1);

namespace Drupal\authorization_test\Plugin\authorization\Consumer;

use Drupal\authorization\Consumer\ConsumerPluginBase;
use Drupal\user\UserInterface;

/**
 * Provides a consumer for Drupal roles.
 *
 * @AuthorizationConsumer(
 *   id = "dummy",
 *   label = @Translation("Dummy")
 * )
 */
class Dummy extends ConsumerPluginBase {

  /**
   * {@inheritdoc}
   */
  protected $allowConsumerTargetCreation = TRUE;

  /**
   * {@inheritdoc}
   */
  public function revokeGrants(UserInterface $user, array $context): void {
    $user->revoked = $context;
  }

  /**
   * {@inheritdoc}
   */
  public function grantSingleAuthorization(UserInterface $user, $mapping): void {
    $user->granted[] = $mapping;
  }

  /**
   * {@inheritdoc}
   */
  public function createConsumerTarget(string $mapping): void {}

}
