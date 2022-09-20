<?php

declare(strict_types = 1);

namespace Drupal\ldap_user\Plugin\Validation\Constraint;

use Drupal\ldap_authentication\Controller\LoginValidatorLoginForm;
use Drupal\ldap_servers\Helper\CredentialsStorage;
use Drupal\user\Plugin\Validation\Constraint\ProtectedUserFieldConstraintValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;

/**
 * Validates the LdapProtectedUserFieldConstraint constraint.
 */
class LdapProtectedUserFieldConstraintValidator extends ProtectedUserFieldConstraintValidator {

  /**
   * Login validator.
   *
   * @var \Drupal\ldap_authentication\Controller\LoginValidatorLoginForm
   */
  protected $loginValidator;

  /**
   * Set the login validator.
   *
   * @param \Drupal\ldap_authentication\Controller\LoginValidatorLoginForm $loginValidator
   *   Login validator.
   */
  public function setLoginValidator(LoginValidatorLoginForm $loginValidator): void {
    $this->loginValidator = $loginValidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): LdapProtectedUserFieldConstraintValidator {
    $plugin = parent::create($container);
    $plugin->setLoginValidator($container->get('ldap_authentication.login_validator'));
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint): void {
    if (!isset($items)) {
      return;
    }
    /** @var \Drupal\Core\Field\FieldItemListInterface $items */
    $field = $items->getFieldDefinition();

    /** @var \Drupal\user\UserInterface $account */
    $account = $items->getEntity();
    if (!isset($account) || !empty($account->_skipProtectedUserFieldConstraint)) {
      // Looks like we are validating a field not being part of a user, or the
      // constraint should be skipped, so do nothing.
      return;
    }

    // Only validate for existing entities and if this is the current user.
    if ($account->isNew() || $account->id() != $this->currentUser->id()) {
      return;
    }

    // Special case for the password, it being empty means that the existing
    // password should not be changed, ignore empty password fields.
    $value = $items->value;
    if ($field->getName() === 'pass' && !$value) {
      return;
    }

    /** @var \Drupal\user\UserInterface $account_unchanged */
    $account_unchanged = $this->userStorage->loadUnchanged($account->id());

    if ($items->getValue() === $account_unchanged->get($field->getName())->getValue()) {
      return;
    }

    // We need the password, the existing one should be here.
    CredentialsStorage::storeUserPassword($account->get('pass')->existing);
    $credentialsAuthenticationResult = $this->loginValidator->validateCredentialsLoggedIn($account_unchanged);

    if ($credentialsAuthenticationResult === $this->loginValidator::AUTHENTICATION_SUCCESS) {
      // Directory approved the request, existing password matches.
      return;
    }

    parent::validate($items, $constraint);
  }

}
