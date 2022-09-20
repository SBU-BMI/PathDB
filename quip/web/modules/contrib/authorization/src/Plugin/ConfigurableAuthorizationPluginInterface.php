<?php

declare(strict_types = 1);

namespace Drupal\authorization\Plugin;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Describes a configurable Authorization plugin.
 */
interface ConfigurableAuthorizationPluginInterface extends PluginInspectionInterface, DerivativeInspectionInterface, ConfigurableInterface, DependentPluginInterface, PluginFormInterface, ContainerFactoryPluginInterface {

  /**
   * Returns the label for use on the administration pages.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The administration label.
   */
  public function label(): TranslatableMarkup;

  /**
   * Returns the plugin's description.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   A string describing the plugin. Might contain HTML and should be already
   *   sanitized for output.
   */
  public function getDescription();

  /**
   * Builds the authorization form row.
   *
   * Return array.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param int $index
   *   The row number of the mapping.
   */
  public function buildRowForm(array $form, FormStateInterface $form_state, $index);

  /**
   * Builds the authorization row description.
   *
   * Return string.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function buildRowDescription(array $form, FormStateInterface $form_state);

  /**
   * Validates the authorization form row.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateRowForm(array &$form, FormStateInterface $form_state): void;

  /**
   * Submits the authorization form row.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitRowForm(array &$form, FormStateInterface $form_state): void;

  /**
   * Tokens for the relevant plugin.
   *
   * @return array
   *   Placeholders for string replacement.
   */
  public function getTokens(): array;

}
