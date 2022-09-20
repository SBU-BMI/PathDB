<?php

declare(strict_types = 1);

namespace Drupal\authorization\Plugin;

use Drupal\Core\Entity\DependencyTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for all configurable Authorization plugins.
 */
abstract class ConfigurableAuthorizationPluginBase extends PluginBase implements ConfigurableAuthorizationPluginInterface {

  use DependencyTrait;

  /**
   * Type.
   *
   * @var string
   */
  protected $type;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition
  ) {
    $configuration += $this->defaultConfiguration();
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    $plugin = new static($configuration, $plugin_id, $plugin_definition);

    /** @var \Drupal\Core\StringTranslation\TranslationInterface $translation */
    $translation = $container->get('string_translation');
    $plugin->setStringTranslation($translation);

    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): TranslatableMarkup {
    $plugin_definition = $this->getPluginDefinition();
    return $plugin_definition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $plugin_definition = $this->getPluginDefinition();
    return $plugin_definition['description'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return $this->type;
  }

  /**
   * {@inheritdoc}
   */
  public function getTokens(): array {
    $tokens = [];
    $tokens['@' . $this->getType() . '_name'] = $this->label();
    $tokens['@' . $this->getType() . '_mappingDirections'] = '';
    $tokens['@examples'] = '';
    return $tokens;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

  /**
   * Unused, configuration is saved in the profile, required by base class.
   */
  public function getConfiguration() {}

  /**
   * Unused, configuration is saved in the profile, required by base class.
   *
   * @param array $configuration
   *   Configuration.
   */
  public function setConfiguration(array $configuration) {}

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function buildRowForm(array $form, FormStateInterface $form_state, $index): array {
    // Should be removed.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateRowForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function buildRowDescription(array $form, FormStateInterface $form_state): string {
    // Should be removed.
    return "";
  }

  /**
   * {@inheritdoc}
   */
  public function submitRowForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $this->addDependency('module', $this->getPluginDefinition()['provider']);
    return $this->dependencies;
  }

}
