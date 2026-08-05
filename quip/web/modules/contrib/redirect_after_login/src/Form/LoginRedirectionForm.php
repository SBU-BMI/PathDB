<?php

namespace Drupal\redirect_after_login\Form;

use Drupal;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class LoginRedirectionForm.
 *
 * @package Drupal\redirect_after_login\Form
 */
class LoginRedirectionForm extends ConfigFormBase {

  /**
   * The Entity Type Manager service property.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * LoginRedirectionForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Core entity type manager service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->typedConfigManager = $typedConfigManager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'login_redirection_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('redirect_after_login.settings');
    $savedPathRoles = $config->get('login_redirection');

    $form['roles'] = [
      '#type'  => 'fieldset',
      '#title' => $this->t('All roles'),
    ];
    foreach ($this->getRoles() as $user => $role) {
      $form['roles'][$user] = [
        '#type'          => 'textfield',
        '#title'         => $role->label(),
        '#size'          => 60,
        '#maxlength'     => 128,
        '#description'   => $this->t('Add a valid url or &ltfront> for main page'),
        '#default_value' => isset($savedPathRoles[$user]) ? $savedPathRoles[$user] : '',
      ];
    }

    $form['exclude_urls'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Exclude url from redirection'),
      '#description'   => $this->t('One url per line. Redirection on this urls will be skipped. You can use wildcard "*".'),
      '#default_value' => $config->get('exclude_urls'),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type'        => 'submit',
      '#value'       => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    foreach ($this->getRoles() as $role_id => $role) {
      $path = $form_state->getValue($role_id);
      if ($path !== '' && !(preg_match('/^[#?\/]+/', $form_state->getValue($role_id)) || $form_state->getValue($role_id) == '<front>')) {
        $form_state->setErrorByName($role_id, $this->t('This URL %url is not valid for role %role.', [
          '%url'  => $form_state->getValue($role_id),
          '%role' => $role->label(),
        ]));
      }
      $is_valid = Drupal::service('path.validator')->isValid($path);
      if ($is_valid == NULL) {
        $form_state->setErrorByName($role_id, $this->t('Path does not exists.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $loginUrls = [];

    foreach ($this->getRoles() as $role_id => $role) {
      if ($form_state->getValue($role_id) == '<front>') {
        $loginUrls[$role_id] = '/';
      }
      else {
        $loginUrls[$role_id] = $form_state->getValue($role_id);
        $form_state->getValue($role_id);
      }
      // Remove empty values, but we allow something like 0, if that's a thing.
      $loginUrls = array_filter($loginUrls, function ($path) {
        return $path !== '';
      });
    }
    $this->config('redirect_after_login.settings')
      ->set('login_redirection', $loginUrls)
      ->set('exclude_urls', $form_state->getValue('exclude_urls'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Get Editable config names.
   *
   * @inheritDoc
   */
  protected function getEditableConfigNames() {
    return ['redirect_after_login.settings'];
  }

  protected function getRoles() {
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    unset($roles[RoleInterface::ANONYMOUS_ID]);
    return $roles;
  }

}
