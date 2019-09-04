<?php

namespace Drupal\authorization\Provider;

use Drupal\authorization\Plugin\ConfigurableAuthorizationPluginBase;
use Drupal\user\UserInterface;

/**
 * Base class for Authorization provider plugins.
 */
abstract class ProviderPluginBase extends ConfigurableAuthorizationPluginBase implements ProviderInterface {

  /**
   * Defines the type, for example used by getToken().
   *
   * @var string
   */
  protected $type = 'provider';

  /**
   * List of modules handling this provider.
   *
   * Can potentially be removed.
   *
   * @var array
   */
  protected $handlers = [];

  /**
   * Whether this provider supports sync on user logon.
   *
   * @var bool
   *   Sync on logon supported.
   */
  protected $syncOnLogonSupported = FALSE;

  /**
   * Whether this provider supports revocation.
   *
   * @var bool
   *   Revocation supported.
   */
  protected $revocationSupported = FALSE;

  /**
   * {@inheritdoc}
   */
  public function isSyncOnLogonSupported() {
    return $this->syncOnLogonSupported;
  }

  /**
   * {@inheritdoc}
   */
  public function revocationSupported() {
    return $this->revocationSupported;
  }

  /**
   * Which modules are handling this provider.
   *
   * Potentially unused.
   *
   * @return array
   *   Handlers.
   */
  public function getHandlers() {
    return $this->handlers;
  }

  /**
   * {@inheritdoc}
   */
  public function getProposals(UserInterface $user) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function filterProposals(array $proposals, array $providerMapping) {
    return $proposals;
  }

  /**
   * {@inheritdoc}
   */
  public function sanitizeProposals(array $proposals) {
    return $proposals;
  }

}
