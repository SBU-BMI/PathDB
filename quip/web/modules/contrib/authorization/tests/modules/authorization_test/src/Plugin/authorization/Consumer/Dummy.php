<?php

declare(strict_types=1);

namespace Drupal\authorization_test\Plugin\authorization\Consumer;

use Drupal\Core\Form\FormStateInterface;
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
  public function revokeGrants(UserInterface $user, array $context, string $profile_id): void {
    $user->revoked = $context;
  }

  /**
   * {@inheritdoc}
   */
  public function grantSingleAuthorization(UserInterface $user, $mapping, string $profile_id): void {
    $user->granted[] = $mapping;
  }

  /**
   * {@inheritdoc}
   */
  public function createConsumerTarget(string $mapping): void {}

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
