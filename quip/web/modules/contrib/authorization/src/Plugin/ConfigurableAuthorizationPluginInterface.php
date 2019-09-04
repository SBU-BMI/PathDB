<?php

namespace Drupal\authorization\Plugin;

use Drupal\Component\Plugin\ConfigurablePluginInterface as DrupalConfigurablePluginInterface;
use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Describes a configurable Authorization plugin.
 */
interface ConfigurableAuthorizationPluginInterface extends PluginInspectionInterface, DerivativeInspectionInterface, DrupalConfigurablePluginInterface, PluginFormInterface, ContainerFactoryPluginInterface {

  /**
   * Returns the label for use on the administration pages.
   *
   * @return string
   *   The administration label.
   */
  public function label();

  /**
   * Returns the plugin's description.
   *
   * @return string
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
  public function validateRowForm(array &$form, FormStateInterface $form_state);

  /**
   * Submits the authorization form row.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitRowForm(array &$form, FormStateInterface $form_state);

  /**
   * Tokens for the relevant plugin.
   *
   * @return array
   *   Placeholders for string replacement.
   */
  public function getTokens();

}
