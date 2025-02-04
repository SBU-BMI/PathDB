<?php

declare(strict_types=1);

namespace Drupal\authorization\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\authorization\Consumer\ConsumerPluginManager;
use Drupal\authorization\Provider\ProviderPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Authorization profile form.
 *
 * @package Drupal\authorization\Form
 */
final class AuthorizationProfileAddForm extends AuthorizationProfileForm {

  /**
   * Constructs a AuthorizationProfileForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\authorization\Provider\ProviderPluginManager $provider_plugin_manager
   *   The Provider plugin manager.
   * @param \Drupal\authorization\Consumer\ConsumerPluginManager $consumer_plugin_manager
   *   The Consumer plugin manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ProviderPluginManager $provider_plugin_manager,
    ConsumerPluginManager $consumer_plugin_manager,
  ) {
    $this->storage = $entity_type_manager->getStorage('authorization_profile');
    $this->providerPluginManager = $provider_plugin_manager;
    $this->consumerPluginManager = $consumer_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.authorization.provider'),
      $container->get('plugin.manager.authorization.consumer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'authorization_profile_add_form';
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    $this->buildEntityForm($form, $form_state, TRUE);

    return $form;
  }

}
