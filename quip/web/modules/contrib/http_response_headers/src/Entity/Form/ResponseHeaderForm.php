<?php

namespace Drupal\http_response_headers\Entity\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Executable\ExecutableManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the Response Header add and edit forms.
 */
class ResponseHeaderForm extends EntityForm {

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The condition plugin manager.
   *
   * @var \Drupal\Core\Condition\ConditionManager
   */
  protected $manager;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $language;

  /**
   * The context repository service.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * Constructs an ResponseHeaderForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager ;
   *   The entity query.
   * @param ExecutableManagerInterface $manager
   * @param ContextRepositoryInterface $context_repository
   */
  public function __construct(EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityTypeManagerInterface $entity_type_manager, ExecutableManagerInterface $manager, LanguageManagerInterface $language, ContextRepositoryInterface $context_repository) {
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityTypeManager = $entity_type_manager;
    $this->manager = $manager;
    $this->language = $language;
    $this->contextRepository = $context_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.condition'),
      $container->get('language_manager'),
      $container->get('context.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form_state->setTemporaryValue('gathered_contexts', $this->contextRepository->getAvailableContexts());
    $form['#attached']['library'][] = 'http_response_headers/http_response_headers.admin';
    $form['#tree'] = TRUE;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#placeholder' => $this->t("Administrative label."),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => 'Drupal\http_response_headers\Entity\ResponseHeader::load',
      ],
      '#disabled' => !$this->entity->isNew(),
    ];
    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#rows' => 2,
      '#default_value' => $this->entity->get('description'),
      '#placeholder' => $this->t("Description for the Response Header."),
      '#required' => FALSE,
    ];
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('HTTP Header name'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->get('name'),
      '#placeholder' => $this->t("HTTP Response Header name."),
      '#required' => TRUE,
    ];
    $form['value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('HTTP Header value'),
      '#rows' => 2,
      '#default_value' => $this->entity->get('value'),
      '#placeholder' => $this->t("The value for the Response Header."),
      '#required' => FALSE,
    ];

    $form['visibility'] = $this->buildVisibilityInterface([], $form_state);

    return $form;
  }

  /**
   * Helper function for building the visibility UI form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form array with the visibility UI added in.
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function buildVisibilityInterface(array $form, FormStateInterface $form_state) {
    $form['visibility_tabs'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Conditions'),
      '#parents' => ['visibility_tabs'],
      '#attached' => [
        'library' => [
          'http_response_headers/http_response_headers.form',
        ],
      ],
    ];
    $visibility = $this->entity->getVisibility();
    $definitions = $this->manager->getFilteredDefinitions('http_response_headers', $form_state->getTemporaryValue('gathered_contexts'), ['response_header' => $this->entity]);
    foreach ($definitions as $condition_id => $definition) {
      // Don't display the current theme condition.
      if ($condition_id == 'current_theme' || ($condition_id == 'language' && !$this->language->isMultilingual())) {
        continue;
      }

      /** @var \Drupal\Core\Condition\ConditionInterface $condition */
      $condition = $this->manager->createInstance($condition_id, $visibility[$condition_id] ?? []);
      $form_state->set(['conditions', $condition_id], $condition);
      $condition_form = $condition->buildConfigurationForm([], $form_state);
      $condition_form['#type'] = 'details';
      $condition_form['#title'] = $condition->getPluginDefinition()['label'];
      $condition_form['#group'] = 'visibility_tabs';
      $form[$condition_id] = $condition_form;
    }

    // Disable negation for specific conditions.
    $disable_negation = [
      'entity_bundle:node', 'language',  'response_status', 'user_role',
    ];
    foreach ($disable_negation as $condition) {
      if (isset($form[$condition])) {
        $form[$condition]['negate']['#type'] = 'value';
        $form[$condition]['negate']['#value'] = $form[$condition]['negate']['#default_value'];
      }
    }

    if (isset($form['user_role'])) {
      $form['user_role']['#title'] = $this->t('Roles');
      unset($form['user_role']['roles']['#description']);
    }
    if (isset($form['request_path'])) {
      $form['request_path']['#title'] = $this->t('Pages');
      $form['request_path']['negate']['#type'] = 'radios';
      $form['request_path']['negate']['#default_value'] = (int) $form['request_path']['negate']['#default_value'];
      $form['request_path']['negate']['#title_display'] = 'invisible';
      $form['request_path']['negate']['#options'] = [
        $this->t('Show for the listed pages'),
        $this->t('Hide for the listed pages'),
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $this->validateVisibility($form, $form_state);
  }

  /**
   * Helper function to independently validate the visibility UI.
   *
   * @param array $form
   *   A nested array form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function validateVisibility(array $form, FormStateInterface $form_state) {
    foreach ($form_state->getValue('visibility') as $condition_id => $values) {
      $condition = $form_state->get(['conditions', $condition_id]);
      $condition->validateConfigurationForm($form['visibility'][$condition_id], SubformState::createForSubform($form['visibility'][$condition_id], $form, $form_state));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\http_response_headers\Entity\ResponseHeader $entity */
    $entity = parent::buildEntity($form, $form_state);
    foreach ($form_state->getValue('visibility') as $condition_id => $values) {
      $condition = $form_state->get(['conditions', $condition_id]);
      $condition->submitConfigurationForm($form['visibility'][$condition_id], SubformState::createForSubform($form['visibility'][$condition_id], $form, $form_state));
      $condition_configuration = $condition->getConfiguration();
      $this->entity->getVisibilityConditions()->addInstanceId($condition_id, $condition_configuration);
    }
    $entity->set('visibility', $entity->getVisibilityConditions()->getConfiguration());
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $response_header = $this->entity;
    $status = $response_header->save();
    if ($status) {
      $this->messenger()->addMessage($this->t('Saved the %label HTTP header.', array(
        '%label' => $response_header->label(),
      )));
    }
    else {
      $this->messenger()->addMessage($this->t('The %label HTTP header was not saved.', array(
        '%label' => $response_header->label(),
      )));
    }
    $form_state->setRedirect('entity.response_header.collection');
  }

}
