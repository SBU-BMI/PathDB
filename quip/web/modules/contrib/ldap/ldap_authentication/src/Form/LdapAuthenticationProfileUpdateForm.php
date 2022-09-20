<?php

declare(strict_types = 1);

namespace Drupal\ldap_authentication\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\ldap_authentication\Routing\EmailTemplateService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Profile update form.
 *
 * This form is meant to presented to the user if the LDAP account does not
 * have an e-mail address associated with it and we need it for Drupal
 * to function correctly, thus we ask the user.
 */
class LdapAuthenticationProfileUpdateForm extends FormBase {

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ldap_authentication_profile_update_form';
  }

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): LdapAuthenticationProfileUpdateForm {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    if (EmailTemplateService::profileNeedsUpdate()) {
      $form['mail'] = [
        '#type' => 'textfield',
        '#required' => TRUE,
        '#title' => $this->t('Email address'),
      ];
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Update account'),
      ];
    }
    else {
      $form['submit'] = [
        '#markup' => '<h2>' . $this->t('This form is only available to profiles which need an update.') . '</h2>',
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!filter_var($form_state->getValue(['mail']), FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('mail', $this->t('You must specify a valid email address.'));
    }
    $users = $this->entityTypeManager->getStorage('user')
      ->loadByProperties(['mail' => $form_state->getValue(['mail'])]);
    if (count($users) > 0) {
      $form_state->setErrorByName('mail', $this->t('This email address is already in use.'));
    }
    $regex = sprintf('`%s`i', $this->configFactory()->get('ldap_authentication.settings')->get('emailTemplateUsagePromptRegex'));
    if (preg_match($regex, $form_state->getValue(['mail']))) {
      $form_state->setErrorByName('mail', $this->t('This email address still matches the invalid email template.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\user\Entity\User $user */
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    $user->set('mail', $form_state->getValue('mail'));
    $user->save();
    $this->messenger()->addMessage($this->t('Your profile has been updated.'));
    $form_state->setRedirect('<front>');
  }

}
