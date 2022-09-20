<?php

declare(strict_types = 1);

namespace Drupal\Tests\ldap_user\Unit;

use Drupal\ldap_authentication\Controller\LoginValidatorBase;
use Drupal\ldap_authentication\Controller\LoginValidatorLoginForm;
use Drupal\ldap_user\Plugin\Validation\Constraint\LdapProtectedUserFieldConstraint;
use Drupal\ldap_user\Plugin\Validation\Constraint\LdapProtectedUserFieldConstraintValidator;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Extended from core tests.
 *
 * @group ldap
 */
class ProtectedUserFieldConstraintValidatorTest extends UnitTestCase {

  /**
   * Creates a validator.
   *
   * @return \Drupal\ldap_user\Plugin\Validation\Constraint\LdapProtectedUserFieldConstraintValidator
   *   Validator.
   */
  protected function createValidator(): LdapProtectedUserFieldConstraintValidator {
    // Setup mocks that don't need to change.
    $unchanged_field = $this->createMock(FieldItemListInterface::class);
    $unchanged_field->expects($this->any())
      ->method('getValue')
      ->willReturn('unchanged-value');

    $unchanged_account = $this->createMock(UserInterface::class);
    $unchanged_account->expects($this->any())
      ->method('get')
      ->willReturn($unchanged_field);
    $user_storage = $this->createMock(UserStorageInterface::class);
    $user_storage->expects($this->any())
      ->method('loadUnchanged')
      ->willReturn($unchanged_account);
    $current_user = $this->createMock(AccountProxyInterface::class);
    $current_user->expects($this->any())
      ->method('id')
      ->willReturn('current-user');
    $validator = new LdapProtectedUserFieldConstraintValidator($user_storage, $current_user);
    $login_service = $this->createMock(LoginValidatorLoginForm::class);
    $login_service->expects($this->any())
      ->method('validateCredentialsLoggedIn')
      ->willReturn(LoginValidatorBase::AUTHENTICATION_FAILURE_CREDENTIALS);
    $validator->setLoginValidator($login_service);
    return $validator;
  }

  /**
   * Test validation.
   *
   * @dataProvider providerTestValidate
   */
  public function testValidate($items, $expected_violation, $name = FALSE): void {
    $constraint = new LdapProtectedUserFieldConstraint();

    // If a violation is expected, then the context's addViolation method will
    // be called, otherwise it should not be called.
    $context = $this->createMock(ExecutionContextInterface::class);

    if ($expected_violation) {
      $context->expects($this->once())
        ->method('addViolation')
        ->with($constraint->message, ['%name' => $name]);
    }
    else {
      $context->expects($this->never())
        ->method('addViolation');
    }

    $validator = $this->createValidator();
    $validator->initialize($context);
    $validator->validate($items, $constraint);
  }

  /**
   * Data provider for ::testValidate().
   */
  public function providerTestValidate(): array {
    $cases = [];

    // Case 0: Empty user should be ignored.
    $field_definition = $this->createMock(FieldDefinitionInterface::class);
    $items = $this->createMock(FieldItemListInterface::class);
    $items->expects($this->once())
      ->method('getFieldDefinition')
      ->willReturn($field_definition);
    $items->expects($this->once())
      ->method('getEntity')
      ->willReturn(NULL);
    $cases[] = [$items, FALSE];

    // Case 1: Account flagged to skip protected user should be ignored.
    $field_definition = $this->createMock(FieldDefinitionInterface::class);
    $account = $this->createMock(UserInterface::class);
    $account->_skipProtectedUserFieldConstraint = TRUE;
    $items = $this->createMock(FieldItemListInterface::class);
    $items->expects($this->once())
      ->method('getFieldDefinition')
      ->willReturn($field_definition);
    $items->expects($this->once())
      ->method('getEntity')
      ->willReturn($account);
    $cases[] = [$items, FALSE];

    // Case 2: New user should be ignored.
    $field_definition = $this->createMock(FieldDefinitionInterface::class);
    $account = $this->createMock(UserInterface::class);
    $account->expects($this->once())
      ->method('isNew')
      ->willReturn(TRUE);
    $items = $this->createMock(FieldItemListInterface::class);
    $items->expects($this->once())
      ->method('getFieldDefinition')
      ->willReturn($field_definition);
    $items->expects($this->once())
      ->method('getEntity')
      ->willReturn($account);
    $cases[] = [$items, FALSE];

    // Case 3: Non-password fields that have not changed should be ignored.
    $field_definition = $this->createMock(FieldDefinitionInterface::class);
    $field_definition->expects($this->exactly(2))
      ->method('getName')
      ->willReturn('field_not_password');
    $account = $this->createMock(UserInterface::class);
    $account->expects($this->once())
      ->method('isNew')
      ->willReturn(FALSE);
    $account->expects($this->exactly(2))
      ->method('id')
      ->willReturn('current-user');
    $items = $this->createMock(FieldItemListInterface::class);
    $items->expects($this->once())
      ->method('getFieldDefinition')
      ->willReturn($field_definition);
    $items->expects($this->once())
      ->method('getEntity')
      ->willReturn($account);
    $items->expects($this->once())
      ->method('getValue')
      ->willReturn('unchanged-value');
    $cases[] = [$items, FALSE];

    // Case 4: Password field with no value set should be ignored.
    $field_definition = $this->createMock(FieldDefinitionInterface::class);
    $field_definition->expects($this->once())
      ->method('getName')
      ->willReturn('pass');
    $account = $this->createMock(UserInterface::class);
    $account->expects($this->once())
      ->method('isNew')
      ->willReturn(FALSE);
    $account->expects($this->exactly(2))
      ->method('id')
      ->willReturn('current-user');
    $items = $this->createMock(FieldItemListInterface::class);
    $items->expects($this->once())
      ->method('getFieldDefinition')
      ->willReturn($field_definition);
    $items->expects($this->once())
      ->method('getEntity')
      ->willReturn($account);
    $cases[] = [$items, FALSE];

    // Case 5: Non-password field changed, user wrong current password.
    $field_definition = $this->createMock(FieldDefinitionInterface::class);
    $field_definition
      ->method('getName')
      ->willReturn('field_not_password');
    $account = $this->createMock(UserInterface::class);
    $account
      ->method('isNew')
      ->willReturn(FALSE);
    $account
      ->method('id')
      ->willReturn('current-user');
    $pass = new \stdClass();
    $pass->existing = 'existing';
    $account->expects($this->once())
      ->method('get')
      ->willReturn($pass);
    $items = $this->createMock(FieldItemListInterface::class);
    $items
      ->method('getFieldDefinition')
      ->willReturn($field_definition);
    $items
      ->method('getEntity')
      ->willReturn($account);
    $items
      ->method('getValue')
      ->willReturn('changed-value');
    $cases[] = [$items, TRUE, NULL];

    return $cases;
  }

  /**
   * Test validation.
   *
   * This could be optimized / simplified if we could override
   * User::checkExistingPassword() instead of directly querying in the
   * constraint.
   */
  public function testSuccess(): void {
    $constraint = new LdapProtectedUserFieldConstraint();

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->never())->method('addViolation');

    // Case 6: Non-password field changed, user gave correct password.
    $field_definition = $this->createMock(FieldDefinitionInterface::class);
    $field_definition
      ->method('getName')
      ->willReturn('field_not_password');
    $account = $this->createMock(UserInterface::class);
    $account
      ->method('isNew')
      ->willReturn(FALSE);
    $account
      ->method('id')
      ->willReturn('current-user');
    $pass = new \stdClass();
    $pass->existing = 'existing';
    $account->expects($this->once())
      ->method('get')
      ->willReturn($pass);
    $items = $this->createMock(FieldItemListInterface::class);
    $items
      ->method('getFieldDefinition')
      ->willReturn($field_definition);
    $items
      ->method('getEntity')
      ->willReturn($account);
    $items
      ->method('getValue')
      ->willReturn('changed-value');

    $validator = $this->createValidator();
    $login_service = $this->createMock(LoginValidatorLoginForm::class);
    $login_service->expects($this->any())
      ->method('validateCredentialsLoggedIn')
      ->willReturn(LoginValidatorBase::AUTHENTICATION_SUCCESS);
    $validator->setLoginValidator($login_service);
    $validator->initialize($context);
    $validator->validate($items, $constraint);
  }

}
