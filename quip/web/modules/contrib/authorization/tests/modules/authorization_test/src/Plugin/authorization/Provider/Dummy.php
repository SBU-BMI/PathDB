<?php

declare(strict_types = 1);

namespace Drupal\authorization_test\Plugin\authorization\Provider;

use Drupal\authorization\AuthorizationSkipAuthorization;
use Drupal\authorization\Provider\ProviderPluginBase;
use Drupal\user\UserInterface;

/**
 * The LDAP authorization provider for authorization module.
 *
 * @AuthorizationProvider(
 *   id = "dummy",
 *   label = @Translation("Dummy")
 * )
 */
class Dummy extends ProviderPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getProposals(UserInterface $user): array {
    foreach ($user->proposals as $proposal) {
      if ($proposal === 'exception') {
        throw new AuthorizationSkipAuthorization('Skip');
      }
    }

    return $user->proposals;
  }

  /**
   * {@inheritdoc}
   */
  public function filterProposals(array $proposals, array $providerMapping): array {
    return $proposals;
  }

  /**
   * {@inheritdoc}
   */
  public function sanitizeProposals(array $proposals): array {
    return $proposals;
  }

}
