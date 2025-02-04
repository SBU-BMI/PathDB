<?php

declare(strict_types=1);

namespace Drupal\authorization_test\Plugin\authorization\Provider;

use Drupal\Core\Form\FormStateInterface;
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
  protected $syncOnLogonSupported = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $revocationSupported = TRUE;

  /**
   * {@inheritdoc}
   */
  public function getProposals(UserInterface $user): array {
    $proposals = $user->proposals ?? [];
    foreach ($proposals as $proposal) {
      if ($proposal === 'exception') {
        throw new AuthorizationSkipAuthorization('Skip');
      }
    }

    return $proposals;
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

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    if ($form_state->has('build_dummy_form')) {
      $form['description'] = [
        '#type' => 'markup',
        '#markup' => $this->t('There are no settings for the Dummy plug-in.'),
      ];
    }

    return $form;
  }

}
