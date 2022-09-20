<?php

declare(strict_types = 1);

namespace Drupal\authorization\Consumer;

use Drupal\authorization\Plugin\ConfigurableAuthorizationPluginInterface;
use Drupal\user\UserInterface;

/**
 * Defines an interface for Authorization consumer plugins.
 */
interface ConsumerInterface extends ConfigurableAuthorizationPluginInterface {

  /**
   * Revoke all previously applied and no longer valid grants.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to act upon.
   * @param array $context
   *   Grants applied during this procedure.
   */
  public function revokeGrants(UserInterface $user, array $context): void;

  /**
   * Grant one individual proposal.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to act upon.
   * @param mixed $mapping
   *   What to grant.
   */
  public function grantSingleAuthorization(UserInterface $user, $mapping): void;

  /**
   * Are we allowed to create things.
   *
   * Note that this only enables the *option* for users to choose this in the
   * consumer configuration of the profile.
   *
   * @return bool
   *   Whether the consumer provides creating targets.
   */
  public function consumerTargetCreationAllowed(): bool;

  /**
   * Create authorization consumer targets.
   *
   * @param string $mapping
   *   What grant to create.
   */
  public function createConsumerTarget(string $mapping): void;

  /**
   * Consumer-side filtering.
   *
   * @param array $proposals
   *   Proposals left over after provider filtering.
   * @param array $mapping
   *   What the proposals should be mapped against in the consumer.
   *
   * @return array
   *   Remaining, valid proposals.
   */
  public function filterProposals(array $proposals, array $mapping): array;

}
