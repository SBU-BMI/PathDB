<?php

declare(strict_types = 1);

namespace Drupal\ldap_authentication\Controller;

/**
 * Handles the actual testing of credentials and authentication of users.
 */
class LoginValidatorSso extends LoginValidatorBase {

  /**
   * Set authname.
   *
   * @param string $authname
   *   Authname.
   */
  public function setAuthname(string $authname): void {
    $this->authName = $authname;
  }

  /**
   * {@inheritdoc}
   */
  public function processLogin(): void {
    if (!$this->validateCommonLoginConstraints()) {
      return;
    }

    if ($this->testCredentials() !== self::AUTHENTICATION_SUCCESS) {
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

    if (!$this->drupalUser) {
      // No existing Drupal account, try provisioning Drupal account.
      $this->provisionDrupalUser();
    }
  }

  /**
   * {@inheritdoc}
   *
   * @todo Reduce code duplication w/ LoginValidator, split this function up.
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

    return $authenticationResult;
  }

  /**
   * Bind to server.
   *
   * @return int
   *   Success or failure result.
   */
  protected function bindToServerAsUser(): int {
    $this->logger->error('Trying to use SSO with user bind method.');
    return self::AUTHENTICATION_FAILURE_CREDENTIALS;
  }

}
