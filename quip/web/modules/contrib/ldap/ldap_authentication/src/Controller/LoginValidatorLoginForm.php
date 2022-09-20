<?php

declare(strict_types = 1);

namespace Drupal\ldap_authentication\Controller;

use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserInterface;

/**
 * Handles the actual testing of credentials and authentication of users.
 */
class LoginValidatorLoginForm extends LoginValidatorBase {

  /**
   * Starts login process.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   The form state.
   */
  public function validateLogin(FormStateInterface $form_state): FormStateInterface {
    $this->authName = trim($form_state->getValue('name') ?? '');
    $this->formState = $form_state;

    $this->detailLog->log(
      '%auth_name : Beginning authentication',
      ['%auth_name' => $this->authName],
      'ldap_authentication'
    );

    $this->processLogin();

    return $this->formState;
  }

  /**
   * {@inheritdoc}
   */
  public function processLogin(): void {
    if ($this->userAlreadyAuthenticated()) {
      return;
    }

    if (!$this->validateCommonLoginConstraints()) {
      return;
    }

    $credentialsAuthenticationResult = $this->testCredentials();

    if ($credentialsAuthenticationResult === self::AUTHENTICATION_FAILURE_FIND &&
      $this->config->get('authenticationMode') === 'exclusive') {
      $this->formState->setErrorByName('non_ldap_login_not_allowed', $this->t('User disallowed'));
    }

    if ($credentialsAuthenticationResult !== self::AUTHENTICATION_SUCCESS) {
      return;
    }

    if (!$this->deriveDrupalUserName()) {
      return;
    }

    // We now have an LDAP account, matching username and password and the
    // reference Drupal user.
    if (!$this->drupalUser && $this->serverDrupalUser) {
      $this->updateAuthNameFromPuid();
    }

    // Existing Drupal but not mapped to LDAP.
    if ($this->drupalUser && !$this->drupalUserAuthMapped) {
      if (!$this->matchExistingUserWithLdap()) {
        return;
      }
    }

    // Existing Drupal account with incorrect email. Fix email if appropriate.
    $this->fixOutdatedEmailAddress();

    // No existing Drupal account. Consider provisioning Drupal account.
    if (!$this->drupalUser) {
      if (!$this->provisionDrupalUser()) {
        return;
      }
    }

    // All passed, log the user in by handing over the UID.
    if ($this->drupalUser) {
      $this->formState->set('uid', $this->drupalUser->id());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function testCredentials(): int {
    $authenticationResult = self::AUTHENTICATION_FAILURE_UNKNOWN;
    foreach ($this->authenticationServers->getAvailableAuthenticationServers() as $server) {
      $this->serverDrupalUser = $this->entityTypeManager
        ->getStorage('ldap_server')
        ->load($server);
      $this->ldapBridge->setServer($this->serverDrupalUser);
      $this->detailLog->log(
        '%username: Trying server %id with %bind_method', [
          '%username' => $this->authName,
          '%id' => $this->serverDrupalUser->id(),
          '%bind_method' => $this->serverDrupalUser->getFormattedBind(),
        ], 'ldap_authentication'
      );

      // @todo Verify new usage of CredentialsStorage here.
      $bindResult = $this->bindToServer();
      if ($bindResult !== self::AUTHENTICATION_SUCCESS) {
        $authenticationResult = $bindResult;
        // If bind fails, onto next server.
        continue;
      }

      // Check if user exists in LDAP.
      $this->ldapUserManager->setServer($this->serverDrupalUser);
      $entry = $this->ldapUserManager->queryAllBaseDnLdapForUsername($this->authName);
      if ($entry) {
        $this->ldapUserManager->sanitizeUserDataResponse($entry, $this->authName);
      }
      $this->ldapEntry = $entry;

      if (!$this->ldapEntry) {
        $authenticationResult = self::AUTHENTICATION_FAILURE_FIND;
        // Next server, please.
        continue;
      }

      if (!$this->checkAllowedExcluded($this->authName, $this->ldapEntry)) {
        $authenticationResult = self::AUTHENTICATION_FAILURE_DISALLOWED;
        // Regardless of how many servers, disallowed user fails.
        break;
      }

      if (!$this->testUserPassword()) {
        $authenticationResult = self::AUTHENTICATION_FAILURE_CREDENTIALS;
        // Next server, please.
        continue;
      }

      $authenticationResult = self::AUTHENTICATION_SUCCESS;
      break;
    }

    $this->detailLog->log(
      '%username: Authentication result is "%err_text"',
      [
        '%username' => $this->authName,
        '%err_text' => $this->authenticationHelpText($authenticationResult) . ' ' . $this->additionalDebuggingResponse($authenticationResult),
      ], 'ldap_authentication'
    );

    if ($authenticationResult !== self::AUTHENTICATION_SUCCESS) {
      $this->failureResponse($authenticationResult);
    }

    return $authenticationResult;
  }

  /**
   * Validate already authenticated user.
   *
   * @return bool
   *   User already authenticated.
   */
  protected function userAlreadyAuthenticated(): bool {

    if (!empty($this->formState->get('uid'))) {
      if ($this->config->get('authenticationMode') === 'mixed') {
        $this->detailLog->log(
          '%username: Previously authenticated in mixed mode, pass on validation.',
          ['%username' => $this->authName],
          'ldap_authentication'
        );
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Check credentials on an signed-in user from the account.
   *
   * This helper function is intended for the user edit form to allow
   * the constraint validator to check against LDAP for the current password.
   *
   * @param \Drupal\user\UserInterface $account
   *   User account.
   *
   * @return int
   *   Authentication status.
   */
  public function validateCredentialsLoggedIn(UserInterface $account): int {
    $this->drupalUser = $account;
    $data = $this->externalAuth->getAuthData($account->id(), 'ldap_user');
    if (!empty($data) && $data['authname']) {
      $this->authName = $data['authname'];
      $this->drupalUserAuthMapped = TRUE;
    }

    $this->detailLog->log(
      '%auth_name : Testing existing credentials authentication',
      ['%auth_name' => $this->authName],
      'ldap_authentication'
    );

    return $this->testCredentials();
  }

}
