<?php

declare(strict_types=1);

namespace Drupal\authorization\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\authorization\Consumer\ConsumerPluginManager;
use Drupal\authorization\Provider\ProviderPluginManager;

/**
 * Authorization profile form.
 *
 * @package Drupal\authorization\Form
 */
abstract class AuthorizationProfileForm extends EntityForm {

  const SAVED_NEW = 1;

  /**
   * The provider plugin manager.
   *
   * @var \Drupal\authorization\Provider\ProviderPluginManager
   */
  protected $providerPluginManager;

  /**
   * The consumer plugin manager.
   *
   * @var \Drupal\authorization\Consumer\ConsumerPluginManager
   */
  protected $consumerPluginManager;

  /**
   * The provider in use.
   *
   * @var string
   */
  protected $provider;

  /**
   * The consumer in use.
   *
   * @var string
   */
  protected $consumer;

  /**
   * Storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * Retrieves the Provider plugin manager.
   *
   * @return \Drupal\authorization\Provider\ProviderPluginManager
   *   The Provider plugin manager.
   */
  protected function getProviderPluginManager(): ProviderPluginManager {
    return $this->providerPluginManager;
  }

  /**
   * Retrieves the Consumer plugin manager.
   *
   * @return \Drupal\authorization\Consumer\ConsumerPluginManager
   *   The Consumer plugin manager.
   */
  protected function getConsumerPluginManager(): ConsumerPluginManager {
    return $this->consumerPluginManager;
  }

  /**
   * Builds the form for the basic server properties.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param bool $is_new
   *   Whether the form is for a new entity.
   */
  protected function buildEntityForm(array &$form, FormStateInterface $form_state, $is_new): void {
    /** @var \Drupal\authorization\AuthorizationProfileInterface $authorization_profile */
    $authorization_profile = $this->getEntity();
    $create = $this->t('Authorization profile cannot be created.');
    $edit = $this->t('Authorization profile cannot be edited.');
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Profile name'),
      '#maxlength' => 255,
      '#default_value' => $authorization_profile->label(),
      '#required' => TRUE,
      '#weight' => -50,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $authorization_profile->id(),
      '#machine_name' => [
        'exists' => '\Drupal\authorization\Entity\AuthorizationProfile::load',
      ],
      '#disabled' => !$authorization_profile->isNew(),
      '#weight' => -40,
    ];

    /* You will need additional form elements for your custom properties. */
    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $authorization_profile->get('status'),
      '#weight' => -30,
    ];

    $provider_options = $this->getProviderOptions();
    if ($provider_options) {
      if (count($provider_options) === 1) {
        $authorization_profile->set('provider', key($provider_options));
      }

      $form['provider'] = [
        '#type' => 'radios',
        '#title' => $this->t('Provider'),
        '#options' => $provider_options,
        '#default_value' => $authorization_profile->getProviderId(),
        '#required' => TRUE,
        '#weight' => -20,
      ];
    }
    else {
      $this->messenger()->addError($this->t('There are no provider plugins available. You will need to install and enable something like the LDAP Authorization Provider module that ships with LDAP.'));
      $form['#access'] = FALSE;
      $form['#markup'] = $is_new ? $create : $edit;
      $form['#cache'] = [
        'tags' => [],
        'contexts' => [],
        'max-age' => 0,
      ];
    }

    $consumer_options = $this->getConsumerOptions();
    if ($consumer_options) {
      if (count($consumer_options) == 1) {
        $authorization_profile->set('consumer', key($consumer_options));
      }

      $form['consumer'] = [
        '#type' => 'radios',
        '#title' => $this->t('Consumer'),
        '#options' => $consumer_options,
        '#default_value' => $authorization_profile->getConsumerId(),
        '#required' => TRUE,
        '#weight' => -10,
      ];
    }
    else {
      $this->messenger()->addError($this->t('There are no consumer plugins available. You can enable the Authorization Drupal Roles submodule to provide integration with core user roles or write your own using that as a template.'));
      $form['#access'] = FALSE;
      $form['#markup'] = $is_new ? $create : $edit;
      $form['#cache'] = [
        'tags' => [],
        'contexts' => [],
        'max-age' => 0,
      ];
    }
  }

  /**
   * Returns all available Provider plugins, as an options list.
   *
   * @return string[]
   *   An associative array mapping Provider plugin IDs to their (HTML-escaped)
   *   labels.
   */
  protected function getProviderOptions(): array {
    $options = [];
    foreach ($this->getProviderPluginManager()->getDefinitions() as $plugin_id => $plugin_definition) {
      $label = $plugin_definition['label']->render();
      $options[$plugin_id] = Html::escape($label);
    }
    return $options;
  }

  /**
   * Returns all available Consumer plugins, as an options list.
   *
   * @return string[]
   *   An associative array mapping Consumer plugin IDs to their (HTML-escaped)
   *   labels.
   */
  protected function getConsumerOptions(): array {
    $options = [];
    foreach ($this->getConsumerPluginManager()->getDefinitions() as $plugin_id => $plugin_definition) {
      $label = $plugin_definition['label']->render();
      $options[$plugin_id] = Html::escape($label);
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): void {
    $authorization_profile = $this->entity;
    $status = $authorization_profile->save();

    switch ($status) {
      case self::SAVED_NEW:
        $this->messenger()->addStatus($this->t('Created the %label Authorization profile.', [
          '%label' => $authorization_profile->label(),
        ]));
        $form_state->setRedirectUrl($authorization_profile->toUrl('edit-form'));
        break;

      default:
        $this->messenger()->addStatus($this->t('Saved the %label Authorization profile.', [
          '%label' => $authorization_profile->label(),
        ]));
        $form_state->setRedirectUrl($authorization_profile->toUrl('collection'));
        break;
    }

  }

}
