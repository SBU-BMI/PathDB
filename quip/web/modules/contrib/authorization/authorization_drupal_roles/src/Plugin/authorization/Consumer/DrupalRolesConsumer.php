<?php

declare(strict_types = 1);

namespace Drupal\authorization_drupal_roles\Plugin\authorization\Consumer;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\authorization\Consumer\ConsumerPluginBase;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function in_array;

/**
 * Provides a consumer for Drupal roles.
 *
 * @AuthorizationConsumer(
 *   id = "authorization_drupal_roles",
 *   label = @Translation("Drupal Roles")
 * )
 */
class DrupalRolesConsumer extends ConsumerPluginBase {

  /**
   * Allow consumer target creation.
   *
   * @var bool
   */
  protected $allowConsumerTargetCreation = TRUE;

  /**
   * Wildcard.
   *
   * @var string
   */
  protected $wildcard = 'source';

  /**
   * Transliteration.
   *
   * @var \Drupal\Component\Transliteration\TransliterationInterface
   */
  protected $transliteration;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    TransliterationInterface $transliteration,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->transliteration = $transliteration;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('transliteration'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['description'] = [
      '#type' => 'markup',
      '#markup' => $this->t('There are no settings for Drupal roles.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRowForm(array $form, FormStateInterface $form_state, $index = 0): array {
    $row = [];
    $mappings = $this->configuration['profile']->getConsumerMappings();
    $roleOptions = ['none' => $this->t('- N/A -')];
    $roles = user_roles(TRUE);
    foreach ($roles as $key => $role) {
      if ($key !== 'authenticated') {
        $roleOptions[$key] = $role->label();
      }
    }
    $roleOptions['source'] = $this->t('Source (Any group)');
    $row['role'] = [
      '#type' => 'select',
      '#title' => $this->t('Role'),
      '#options' => $roleOptions,
      '#default_value' => isset($mappings[$index]) ? $mappings[$index]['role'] : NULL,
      '#description' => $this->t("Choosing 'Source' maps any input directly to Drupal, use with caution."),
    ];
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function grantSingleAuthorization(UserInterface $user, $mapping): void {
    $mapping = $this->sanitizeRoleId($mapping);

    $previousRoles = [];
    $savedRoles = $user->get('authorization_drupal_roles_roles')->getValue();
    foreach ($savedRoles as $savedRole) {
      $previousRoles[] = $savedRole['value'];
    }
    if (!in_array($mapping, $previousRoles, TRUE)) {
      $previousRoles[] = $mapping;
    }
    $user->set('authorization_drupal_roles_roles', $previousRoles);
    $user->addRole($mapping);
  }

  /**
   * {@inheritdoc}
   */
  public function revokeGrants(UserInterface $user, array $context): void {
    foreach ($context as $key => $mapping) {
      $context[$key] = $this->sanitizeRoleId($mapping);
    }

    $previousRoles = [];
    $savedRoles = $user->get('authorization_drupal_roles_roles')->getValue();
    foreach ($savedRoles as $savedRole) {
      $previousRoles[] = $savedRole['value'];
    }
    foreach ($previousRoles as $key => $value) {
      if (!in_array($value, $context, TRUE)) {
        $user->removeRole($value);
        unset($previousRoles[$key]);
      }
    }
    $user->set('authorization_drupal_roles_roles', $previousRoles);
  }

  /**
   * {@inheritdoc}
   */
  public function createConsumerTarget(string $mapping): void {
    $sanitizedId = $this->sanitizeRoleId($mapping);
    $storage = $this->entityTypeManager->getStorage('user_role');
    if (!$storage->load($sanitizedId)) {
      $role = $storage->create(['id' => $sanitizedId, 'label' => $mapping]);
      $role->save();
    }
  }

  /**
   * Return the wildcard in use.
   *
   * We use this to allow for direct mapping within the filter proposals.
   *
   * @return string
   *   Wildcard.
   */
  private function getWildcard(): string {
    return $this->wildcard;
  }

  /**
   * {@inheritdoc}
   */
  public function filterProposals(array $proposals, array $mapping): array {
    if ($mapping['role'] === $this->getWildcard()) {
      return $proposals;
    }

    // Filters out valid providers with invalid assignments.
    if ($mapping['role'] === 'none') {
      return [];
    }

    if (!empty($proposals)) {
      // The match from the provider already ensured that the consumer mapping
      // is correct, thus we can safely map the value directly.
      return [$mapping['role'] => $mapping['role']];
    }

    return [];
  }

  /**
   * Take a proposed mapping and provide a safe value for Drupal roles.
   *
   * @param string $consumer
   *   A valid proposal for this consumer.
   *
   * @return string
   *   A valid string for Drupal roles.
   */
  private function sanitizeRoleId(string $consumer): string {
    $sanitizedId = $this->transliteration->transliterate($consumer, 'en', '');
    $sanitizedId = mb_strtolower($sanitizedId);
    return preg_replace('@[^a-z0-9_.]+@', '_', $sanitizedId);
  }

}
