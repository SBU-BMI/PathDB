<?php

namespace Drupal\ldap_authentication\Controller;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ldap_authentication\Helper\LdapAuthenticationConfiguration;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers\Helper\CredentialsStorage;
use Drupal\ldap_servers\Logger\LdapDetailLog;
use Drupal\ldap_user\Helper\ExternalAuthenticationHelper;
use Drupal\ldap_user\Helper\LdapConfiguration;
use Drupal\ldap_servers\LdapUserAttributesInterface;
use Drupal\ldap_user\Processor\DrupalUserProcessor;
use Drupal\user\Entity\User;
use Drupal\Core\Form\FormStateInterface;

/**
 * Handles the actual testing of credentials and authentication of users.
 */
final class LoginValidator implements LdapUserAttributesInterface {

  use StringTranslationTrait;

  const AUTHENTICATION_FAILURE_CONNECTION = 1;
  const AUTHENTICATION_FAILURE_BIND = 2;
  const AUTHENTICATION_FAILURE_FIND = 3;
  const AUTHENTICATION_FAILURE_DISALLOWED = 4;
  const AUTHENTICATION_FAILURE_CREDENTIALS = 5;
  const AUTHENTICATION_SUCCESS = 6;
  const AUTHENTICATION_FAILURE_GENERIC = 7;
  const AUTHENTICATION_FAILURE_SERVER = 8;

  protected $authName = FALSE;

  protected $drupalUserAuthMapped = FALSE;
  protected $drupalUserName = FALSE;

  /**
   * The Server for the Drupal user.
   *
   * @var \Drupal\ldap_servers\Entity\Server
   */
  protected $serverDrupalUser;

  /**
   * The Drupal user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $drupalUser = FALSE;
  protected $ldapUser = FALSE;

  protected $emailTemplateUsed = FALSE;
  protected $emailTemplateTokens = [];

  protected $formState;

  protected $configFactory;
  protected $config;
  protected $detailLog;
  protected $logger;
  protected $entityTypeManager;
  protected $moduleHandler;

  /**
   * Constructor.
   */
  public function __construct(ConfigFactoryInterface $configFactory, LdapDetailLog $detailLog, LoggerChannelInterface $logger, EntityTypeManagerInterface $entity_type_manager, ModuleHandler $module_handler) {
    $this->configFactory = $configFactory;
    $this->config = $configFactory->get('ldap_authentication.settings');
    $this->detailLog = $detailLog;
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Starts login process.
   *
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   The form state.
   */
  public function validateLogin(FormStateInterface $formState) {
    $this->authName = trim($formState->getValue('name'));
    $this->formState = $formState;

    $this->detailLog->log(
      '%auth_name : Beginning authentication',
      ['%auth_name' => $this->authName],
    'ldap_authentication'
    );

    $this->processLogin();

    return $this->formState;
  }

  /**
   * Perform the actual logging in.
   *
   * @return bool
   *   Success or failure of authentication.
   *
   * @TODO: Return values aren't actually reviewed, can be simplified.
   */
  private function processLogin() {
    if (!$this->validateAlreadyAuthenticated()) {
      return FALSE;
    }
    if (!$this->validateCommonLoginConstraints()) {
      return FALSE;
    }

    $credentialsAuthenticationResult = $this->testCredentials($this->formState->getValue('pass'));

    if ($credentialsAuthenticationResult == self::AUTHENTICATION_FAILURE_FIND &&
      $this->config->get('authenticationMode') == LdapAuthenticationConfiguration::MODE_EXCLUSIVE) {
      $this->formState->setErrorByName('non_ldap_login_not_allowed', $this->t('User disallowed'));
    }

    if ($credentialsAuthenticationResult != self::AUTHENTICATION_SUCCESS) {
      return FALSE;
    }

    if (!$this->deriveDrupalUserName()) {
      return FALSE;
    }

    // We now have an LDAP account, matching username and password and the
    // reference Drupal user.
    if (!$this->drupalUser && $this->serverDrupalUser) {
      $this->updateAuthNameFromPuid();
    }

    // Existing Drupal but not mapped to LDAP.
    if ($this->drupalUser && !$this->drupalUserAuthMapped) {
      if (!$this->matchExistingUserWithLdap()) {
        return FALSE;
      }
    }

    // Existing Drupal account with incorrect email. Fix email if appropriate.
    $this->fixOutdatedEmailAddress();

    // No existing Drupal account. Consider provisioning Drupal account.
    if (!$this->drupalUser) {
      if (!$this->provisionDrupalUser()) {
        return FALSE;
      }
    }

    // All passed, log the user in by handing over the UID.
    if ($this->drupalUser) {
      $this->formState->set('uid', $this->drupalUser->id());
    }

    return TRUE;
  }

  /**
   * Processes an SSO login.
   *
   * @param string $authName
   *   The provided authentication name.
   *
   * @Todo: Postprocessing could be wrapped in a function, identical in
   * processLogin().
   * @TODO: Return values aren't actually reviewed, can be simplified.
   */
  public function processSsoLogin($authName) {
    $this->authName = $authName;

    if (!$this->validateCommonLoginConstraints()) {
      return FALSE;
    }

    $credentialsAuthenticationResult = $this->testSsoCredentials($this->authName);

    if ($credentialsAuthenticationResult == self::AUTHENTICATION_FAILURE_FIND &&
      $this->config->get('authenticationMode') == LdapAuthenticationConfiguration::MODE_EXCLUSIVE) {
      $this->formState->setErrorByName('non_ldap_login_not_allowed', $this->t('User disallowed'));
    }

    if ($credentialsAuthenticationResult != self::AUTHENTICATION_SUCCESS) {
      return FALSE;
    }

    if (!$this->deriveDrupalUserName()) {
      return FALSE;
    }

    // We now have an LDAP account, matching username and password and the
    // reference Drupal user.
    if (!$this->drupalUser && $this->serverDrupalUser) {
      $this->updateAuthNameFromPuid();
    }

    // Existing Drupal but not mapped to LDAP.
    if ($this->drupalUser && !$this->drupalUserAuthMapped) {
      if (!$this->matchExistingUserWithLdap()) {
        return FALSE;
      }
    }

    // Existing Drupal account with incorrect email. Fix email if appropriate.
    $this->fixOutdatedEmailAddress();

    // No existing Drupal account. Consider provisioning Drupal account.
    if (!$this->drupalUser) {
      if (!$this->provisionDrupalUser()) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Determine if the corresponding Drupal account exists and is mapped.
   *
   * The authName property is checked against external authentication mapping.
   */
  private function initializeDrupalUserFromAuthName() {
    $this->drupalUser = user_load_by_name($this->authName);
    if (!$this->drupalUser) {
      $uid = ExternalAuthenticationHelper::getUidFromIdentifierMap($this->authName);
      if ($uid) {
        $this->drupalUser = $this->entityTypeManager->getStorage('user')->load($uid);
      }
    }
    if ($this->drupalUser) {
      $this->drupalUserAuthMapped = ExternalAuthenticationHelper::getUserIdentifierFromMap($this->drupalUser->id());
    }
  }

  /**
   * Verifies whether the user is available or can be created.
   *
   * @return bool
   *   Whether to allow user login and creation.
   */
  private function verifyAccountCreation() {
    if (is_object($this->drupalUser)) {
      // @TODO 2914053.
      if ($this->drupalUser->id() == 1) {
        $this->detailLog->log(
          '%username: Drupal user name maps to user 1, so do not authenticate with LDAP.',
          ['%username' => $this->authName],
          'ldap_authentication'
        );
        return FALSE;
      }
      else {
        $this->detailLog->log(
          '%username: Drupal user account found. Continuing on to attempt LDAP authentication.',
          ['%username' => $this->authName],
          'ldap_authentication'
        );
        return TRUE;
      }
    }
    // Account does not exist, verify it can be created.
    else {
      $ldapUserConfig = $this->configFactory->get('ldap_user.settings');
      if ($ldapUserConfig->get('acctCreation') == self::ACCOUNT_CREATION_LDAP_BEHAVIOUR ||
        $ldapUserConfig->get('register') == USER_REGISTER_VISITORS) {
        $this->detailLog->log(
          '%username: Existing Drupal user account not found. Continuing on to attempt LDAP authentication', ['%username' => $this->authName],
          'ldap_authentication'
        );
        return TRUE;
      }
      else {
        $this->detailLog->log(
          '%username: Drupal user account not found and configuration is set to not create new accounts.',
          ['%username' => $this->authName],
          'ldap_authentication'
        );
        return FALSE;
      }
    }
  }

  /**
   * Credentials are tested.
   *
   * @return int
   *   Returns the authentication result.
   */
  private function testCredentials($password) {
    $authenticationResult = self::AUTHENTICATION_FAILURE_GENERIC;

    foreach (LdapAuthenticationConfiguration::getEnabledAuthenticationServers() as $server) {
      $authenticationResult = self::AUTHENTICATION_FAILURE_GENERIC;
      $this->serverDrupalUser = Server::load($server);
      $this->detailLog->log(
        '%username: Trying server %id with %bind_method', [
          '%username' => $this->authName,
          '%id' => $this->serverDrupalUser->id(),
          '%bind_method' => $this->serverDrupalUser->getFormattedBind(),
        ], 'ldap_authentication'
      );

      if (!$this->connectToServer()) {
        continue;
      }

      $bindStatus = $this->bindToServer($password);
      // @FIXME: We can do this better.
      if ($bindStatus != 'success') {
        $authenticationResult = $bindStatus;
        // If bind fails, onto next server.
        continue;
      }

      // Check if user exists in LDAP.
      $this->ldapUser = $this->serverDrupalUser->matchUsernameToExistingLdapEntry($this->authName);

      if (!$this->ldapUser) {
        $this->detailLog->log(
          '%username: User not found for server %id with %bind_method.', [
            '%username' => $this->authName,
            '%error' => $this->serverDrupalUser->formattedError($this->serverDrupalUser->ldapErrorNumber()),
            '%bind_method' => $this->serverDrupalUser->getFormattedBind(),
            '%id' => $this->serverDrupalUser->id(),
          ], 'ldap_authentication'
        );
        if ($this->serverDrupalUser->hasError()) {
          $authenticationResult = self::AUTHENTICATION_FAILURE_SERVER;
          break;
        }
        $authenticationResult = self::AUTHENTICATION_FAILURE_FIND;
        // Next server, please.
        continue;
      }

      if (!$this->checkAllowedExcluded($this->authName, $this->ldapUser)) {
        $authenticationResult = self::AUTHENTICATION_FAILURE_DISALLOWED;
        // Regardless of how many servers, disallowed user fails.
        break;
      }

      // Test the password.
      $credentials_pass = $this->testUserPassword($password);

      if (!$credentials_pass) {
        $authenticationResult = self::AUTHENTICATION_FAILURE_CREDENTIALS;
        // Next server, please.
        continue;
      }
      else {
        $authenticationResult = self::AUTHENTICATION_SUCCESS;
        if ($this->serverDrupalUser->get('bind_method') == 'anon_user') {
          // After successful bind, lookup user again to get private attributes.
          $this->ldapUser = $this->serverDrupalUser->matchUsernameToExistingLdapEntry($this->authName);
        }
        if ($this->serverDrupalUser->get('bind_method') == 'service_account' ||
          $this->serverDrupalUser->get('bind_method') == 'anon_user') {
          $this->serverDrupalUser->disconnect();
        }
        // Success.
        break;
      }
      // End of loop through servers.
    }

    $this->detailLog->log(
      '%username: Authentication result is "%err_text"',
      [
        '%username' => $this->authName,
        '%err_text' => $this->authenticationHelpText($authenticationResult) . ' ' . $this->additionalDebuggingResponse($authenticationResult),
      ], 'ldap_authentication'
    );

    if ($authenticationResult != self::AUTHENTICATION_SUCCESS) {
      $this->failureResponse($authenticationResult);
    }

    return $authenticationResult;
  }

  /**
   * Tests the user's password.
   *
   * @return bool
   *   Valid login.
   */
  private function testUserPassword($password) {
    $loginValid = FALSE;
    if ($this->serverDrupalUser->get('bind_method') == 'user') {
      $loginValid = TRUE;
    }
    else {
      CredentialsStorage::storeUserDn($this->ldapUser['dn']);
      CredentialsStorage::testCredentials(TRUE);
      $bindResult = $this->serverDrupalUser->bind();
      CredentialsStorage::testCredentials(FALSE);
      if ($bindResult == Server::LDAP_SUCCESS) {
        $loginValid = TRUE;
      }
      else {
        $this->detailLog->log(
          '%username: Error testing user credentials on server %id with %bind_method. Error: %err_text', [
            '%username' => $this->authName,
            '%bind_method' => $this->serverDrupalUser->getFormattedBind(),
            '%id' => $this->serverDrupalUser->id(),
            '%err_text' => $this->serverDrupalUser->formattedError($bindResult),
          ], 'ldap_authentication'
        );
      }
    }
    return $loginValid;
  }

  /**
   * Test the SSO credentials.
   *
   * @return int
   *   Returns the authentication result.
   */
  public function testSsoCredentials($authName) {
    // TODO: Verify if MODE_EXCLUSIVE check is a regression.
    $authenticationResult = self::AUTHENTICATION_FAILURE_GENERIC;

    foreach (LdapAuthenticationConfiguration::getEnabledAuthenticationServers() as $server) {
      $authenticationResult = self::AUTHENTICATION_FAILURE_GENERIC;
      $this->serverDrupalUser = Server::load($server);
      $this->detailLog->log(
        '%username: Trying server %id where bind_method = %bind_method',
        [
          '%username' => $authName,
          '%id' => $this->serverDrupalUser->id(),
          '%bind_method' => $this->serverDrupalUser->get('bind_method'),
        ], 'ldap_authentication'
      );

      if (!$this->connectToServer()) {
        continue;
      }

      $bindResult = $this->bindToServerSso();
      if ($bindResult != 'success') {
        $authenticationResult = $bindResult;
        // If bind fails, onto next server.
        continue;
      }

      $this->ldapUser = $this->serverDrupalUser->matchUsernameToExistingLdapEntry($authName);

      if (!$this->ldapUser) {
        $this->detailLog->log(
          '%username: Trying server %id where bind_method = %bind_method. Error: %err_text', [
            '%username' => $authName,
            '%bind_method' => $this->serverDrupalUser->get('bind_method'),
            '%err_text' => $this->serverDrupalUser->formattedError($this->serverDrupalUser->ldapErrorNumber()),
          ], 'ldap_authentication'
        );

        if ($this->serverDrupalUser->hasError()) {
          $authenticationResult = self::AUTHENTICATION_FAILURE_SERVER;
          break;
        }
        $authenticationResult = self::AUTHENTICATION_FAILURE_FIND;
        // Next server, please.
        continue;
      }

      if (!$this->checkAllowedExcluded($this->authName, $this->ldapUser)) {
        $authenticationResult = self::AUTHENTICATION_FAILURE_DISALLOWED;
        // Regardless of how many servers, disallowed user fails.
        break;
      }

      $authenticationResult = self::AUTHENTICATION_SUCCESS;
      if ($this->serverDrupalUser->get('bind_method') == 'anon_user') {
        // After successful bind, lookup user again to get private attributes.
        $this->ldapUser = $this->serverDrupalUser->matchUsernameToExistingLdapEntry($authName);
      }
      if ($this->serverDrupalUser->get('bind_method') == 'service_account' ||
        $this->serverDrupalUser->get('bind_method') == 'anon_user') {
        $this->serverDrupalUser->disconnect();
      }
      // Success.
      break;
      // End loop through servers.
    }

    $this->detailLog->log(
      'Authentication result for %username is: %err_text',
      [
        '%username' => $authName,
        '%err_text' => $this->authenticationHelpText($authenticationResult) . ' ' . $this->additionalDebuggingResponse($authenticationResult),
      ], 'ldap_authentication'
    );

    return $authenticationResult;
  }

  /**
   * Provides formatting for authentication failures.
   *
   * @return string
   *   Response text.
   */
  private function additionalDebuggingResponse($authenticationResult) {
    $information = '';
    switch ($authenticationResult) {
      case self::AUTHENTICATION_FAILURE_FIND:
        $information = $this->t('(not found)');
        break;

      case self::AUTHENTICATION_FAILURE_CREDENTIALS:
        $information = $this->t('(wrong credentials)');
        break;

      case self::AUTHENTICATION_FAILURE_GENERIC:
        $information = $this->t('(generic)');
        break;
    }
    return $information;
  }

  /**
   * Failure response.
   *
   * @param int $authenticationResult
   *   The error code.
   */
  private function failureResponse($authenticationResult) {
    // Fail scenario 1. LDAP auth exclusive and failed  throw error so no other
    // authentication methods are allowed.
    if ($this->config->get('authenticationMode') == LdapAuthenticationConfiguration::MODE_EXCLUSIVE) {
      $this->detailLog->log(
        '%username: Error raised because failure at LDAP and exclusive authentication is set to true.',
        ['%username' => $this->authName], 'ldap_authentication'
      );

      drupal_set_message($this->t('Error: %err_text', ['%err_text' => $this->authenticationHelpText($authenticationResult)]), "error");
    }
    else {
      // Fail scenario 2.  Simply fails LDAP. Return false, but don't throw form
      // error don't show user message, may be using other authentication after
      // this that may succeed.
      $this->detailLog->log(
        '%username: Failed LDAP authentication. User may have authenticated successfully by other means in a mixed authentication site.',
        ['%username' => $this->authName],
        'ldap_authentication'
      );
    }
  }

  /**
   * Get human readable authentication error string.
   *
   * @param int $error
   *   Error code.
   *
   * @return string
   *   Human readable error text.
   */
  private function authenticationHelpText($error) {

    switch ($error) {
      case self::AUTHENTICATION_FAILURE_CONNECTION:
        $msg = $this->t('Failed to connect to LDAP server');
        break;

      case self::AUTHENTICATION_FAILURE_BIND:
        $msg = $this->t('Failed to bind to LDAP server');
        break;

      case self::AUTHENTICATION_FAILURE_DISALLOWED:
        $msg = $this->t('User disallowed');
        break;

      case self::AUTHENTICATION_FAILURE_FIND:
      case self::AUTHENTICATION_FAILURE_CREDENTIALS:
      case self::AUTHENTICATION_FAILURE_GENERIC:
        $msg = $this->t('Sorry, unrecognized username or password.');
        break;

      case self::AUTHENTICATION_FAILURE_SERVER:
        $msg = $this->t('Authentication Server or Configuration Error.');
        break;

      case self::AUTHENTICATION_SUCCESS:
        $msg = $this->t('Authentication successful');
        break;

      default:
        $msg = $this->t('unknown error: @error', ['@error' => $error]);
        break;
    }

    return $msg;
  }

  /**
   * Check if exclusion criteria match.
   *
   * @return bool
   *   Exclusion result.
   */
  public function checkAllowedExcluded($authName, $ldap_user) {

    // Do one of the exclude attribute pairs match? If user does not already
    // exists and deferring to user settings AND user settings only allow.
    foreach ($this->config->get('excludeIfTextInDn') as $test) {
      if (stripos($ldap_user['dn'], $test) !== FALSE) {
        return FALSE;
      }
    }

    // Check if one of the allow attribute pairs match.
    if (count($this->config->get('allowOnlyIfTextInDn'))) {
      $fail = TRUE;
      foreach ($this->config->get('allowOnlyIfTextInDn') as $test) {
        if (stripos($ldap_user['dn'], $test) !== FALSE) {
          $fail = FALSE;
        }
      }
      if ($fail) {
        return FALSE;
      }

    }

    // Handle excludeIfNoAuthorizations enabled and user has no groups.
    if ($this->moduleHandler->moduleExists('ldap_authorization') &&
      $this->config->get('excludeIfNoAuthorizations')) {

      $user = FALSE;
      $id = ExternalAuthenticationHelper::getUidFromIdentifierMap($authName);
      if ($id) {
        $user = $this->entityTypeManager->getStorage('user')->load($id);
      }

      if (!$user) {
        $user = User::create(['name' => $authName]);
      }

      // We are not injecting this service properly to avoid forcing this
      // dependency on authorization.
      /** @var \Drupal\authorization\AuthorizationController $controller */
      $controller = \Drupal::service('authorization.manager');
      $controller->setUser($user);

      $profiles = $this->entityTypeManager
        ->getStorage('authorization_profile')
        ->getQuery()
        ->condition('provider', 'ldap_provider')
        ->execute();
      foreach ($profiles as $profile) {
        $controller->queryIndividualProfile($profile);
      }
      $authorizations = $controller->getProcessedAuthorizations();
      $controller->clearAuthorizations();

      $valid_profile = FALSE;
      foreach ($authorizations as $authorization) {
        if (!empty($authorization->getAuthorizationsApplied())) {
          $valid_profile = TRUE;
        }
      }

      if (!$valid_profile) {
        drupal_set_message($this->t('The site logon is currently not working due to a configuration error. Please see logs for additional details.'), 'warning');
        $this->logger->notice('LDAP Authentication is configured to deny users without LDAP Authorization mappings, but 0 LDAP Authorization consumers are configured.');
        return FALSE;
      }

    }

    // Allow other modules to hook in and refuse if they like.
    $hook_result = TRUE;
    $this->moduleHandler->alter('ldap_authentication_allowuser_results', $ldap_user, $authName, $hook_result);

    if ($hook_result === FALSE) {
      $this->logger->notice('Authentication Allow User Result=refused for %name', ['%name' => $authName]);
      return FALSE;
    }

    // Default to allowed.
    return TRUE;
  }

  /**
   * Update an outdated email address.
   *
   * @return bool
   *   Email updated.
   */
  private function fixOutdatedEmailAddress() {

    if ($this->config->get('emailTemplateUsageNeverUpdate') && $this->emailTemplateUsed) {
      return FALSE;
    }

    if (!$this->drupalUser) {
      return FALSE;
    }

    if ($this->drupalUser->getEmail() == $this->ldapUser['mail']) {
      return FALSE;
    }

    if ($this->config->get('emailUpdate') == LdapAuthenticationConfiguration::$emailUpdateOnLdapChangeEnableNotify ||
        $this->config->get('emailUpdate') == LdapAuthenticationConfiguration::$emailUpdateOnLdapChangeEnable) {
      $this->drupalUser->set('mail', $this->ldapUser['mail']);
      if (!$this->drupalUser->save()) {
        $this->logger
          ->error('Failed to make changes to user %username updated %changed.', [
            '%username' => $this->drupalUser->getAccountName(),
            '%changed' => $this->ldapUser['mail'],
          ]
          );
        return FALSE;
      }
      elseif ($this->config->get('emailUpdate') == LdapAuthenticationConfiguration::$emailUpdateOnLdapChangeEnableNotify
      ) {
        drupal_set_message($this->t(
          'Your e-mail has been updated to match your current account (%mail).',
          ['%mail' => $this->ldapUser['mail']]),
          'status'
        );
        return TRUE;
      }
    }
  }

  /**
   * Update the authName if it's no longer valid.
   *
   * Drupal account does not exist for authName used to logon, but puid exists
   * in another Drupal account; this means username has changed and needs to be
   * saved in Drupal account.
   */
  private function updateAuthNameFromPuid() {
    $puid = $this->serverDrupalUser->userPuidFromLdapEntry($this->ldapUser['attr']);
    if ($puid) {
      $this->drupalUser = $this->serverDrupalUser->userAccountFromPuid($puid);
      /** @var \Drupal\user\Entity\User $userMatchingPuid */
      if ($this->drupalUser) {
        $oldName = $this->drupalUser->getAccountName();
        $this->drupalUser->setUsername($this->drupalUserName);
        $this->drupalUser->save();
        ExternalAuthenticationHelper::setUserIdentifier($this->drupalUser, $this->authName);
        $this->drupalUserAuthMapped = TRUE;
        drupal_set_message(
            $this->t('Your existing account %username has been updated to %new_username.',
              ['%username' => $oldName, '%new_username' => $this->drupalUserName]),
            'status');
      }
    }
  }

  /**
   * Validate already authenticated user.
   *
   * @return bool
   *   Pass or continue.
   */
  private function validateAlreadyAuthenticated() {

    if (!empty($this->formState->get('uid'))) {
      if ($this->config->get('authenticationMode') == LdapAuthenticationConfiguration::MODE_MIXED) {
        $this->detailLog->log(
            '%username: Previously authenticated in mixed mode, pass on validation.',
            ['%username' => $this->authName],
            'ldap_authentication'
          );
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Validate common login constraints for user.
   *
   * @return bool
   *   Continue authentication.
   */
  private function validateCommonLoginConstraints() {

    // Check that enabled servers are available.
    if (!LdapAuthenticationConfiguration::hasEnabledAuthenticationServers()) {
      $this->logger->error('No LDAP servers configured.');
      if ($this->formState) {
        $this->formState->setErrorByName('name', 'Server Error:  No LDAP servers configured.');
      }
      return FALSE;
    }

    $this->initializeDrupalUserFromAuthName();
    return $this->verifyAccountCreation();
  }

  /**
   * Derives the Drupal user name from server configuration.
   *
   * @return bool
   *   Success of deriving Drupal user name.
   */
  private function deriveDrupalUserName() {
    // If account_name_attr is set, Drupal username is different than authName.
    if (!empty($this->serverDrupalUser->get('account_name_attr'))) {
      $processedName = mb_strtolower($this->serverDrupalUser->get('account_name_attr'));
      $userNameFromAttribute = $this->ldapUser['attr'][$processedName][0];
      if (!$userNameFromAttribute) {
        $this->logger
          ->error('Derived Drupal username from attribute %account_name_attr returned no username for authname %authname.', [
            '%authname' => $this->authName,
            '%account_name_attr' => $this->serverDrupalUser->get('account_name_attr'),
          ]
          );
        return FALSE;
      }
      else {
        $this->drupalUserName = $userNameFromAttribute;
      }
    }
    else {
      $this->drupalUserName = $this->authName;
    }
    $this->prepareEmailTemplateToken();

    return TRUE;
  }

  /**
   * Prepare the email template token.
   */
  private function prepareEmailTemplateToken() {
    $this->emailTemplateTokens = ['@username' => $this->drupalUserName];

    if (!empty($this->config->get('emailTemplate'))) {
      $handling = $this->config->get('emailTemplateHandling');
      if (($handling == 'if_empty' && empty($this->ldapUser['mail'])) || $handling == 'always') {
        $this->replaceUserMailWithTemplate();
        $this->detailLog->log(
          'Using template generated email for %username',
          ['%username' => $this->drupalUserName],
          'ldap_authentication'
        );

        $this->emailTemplateUsed = TRUE;
      }
    }
  }

  /**
   * Match existing user with LDAP.
   *
   * @return bool
   *   User matched.
   */
  private function matchExistingUserWithLdap() {
    if ($this->configFactory->get('ldap_user.settings')->get('userConflictResolve') == self::USER_CONFLICT_LOG) {
      if ($account_with_same_email = user_load_by_mail($this->ldapUser['mail'])) {
        /** @var \Drupal\user\UserInterface $account_with_same_email */
        $this->logger
          ->error('LDAP user with DN %dn has a naming conflict with a local Drupal user %conflict_name',
            [
              '%dn' => $this->ldapUser['dn'],
              '%conflict_name' => $account_with_same_email->getAccountName(),
            ]
          );
      }
      drupal_set_message($this->t('Another user already exists in the system with the same login name. You should contact the system administrator in order to solve this conflict.'), 'error');
      return FALSE;
    }
    else {
      ExternalAuthenticationHelper::setUserIdentifier($this->drupalUser, $this->authName);
      $this->drupalUserAuthMapped = TRUE;
      $this->detailLog->log(
        'Set authmap for LDAP user %username',
        ['%username' => $this->authName],
        'ldap_authentication'
      );
    }
    return TRUE;
  }

  /**
   * Replace user email address with template.
   */
  private function replaceUserMailWithTemplate() {
    // Fallback template in case one was not specified.
    $template = '@username@localhost';
    if (!empty($this->config->get('emailTemplate'))) {
      $template = $this->config->get('emailTemplate');
    }
    $this->ldapUser['mail'] = SafeMarkup::format($template, $this->emailTemplateTokens)->__toString();
  }

  /**
   * Provision the Drupal user.
   *
   * @return bool
   *   Provisioning successful.
   */
  private function provisionDrupalUser() {

    // Do not provision Drupal account if another account has same email.
    if ($accountDuplicateMail = user_load_by_mail($this->ldapUser['mail'])) {
      $emailAvailable = FALSE;
      if ($this->config->get('emailTemplateUsageResolveConflict') && (!$this->emailTemplateUsed)) {
        $this->detailLog->log(
          'Conflict detected, using template generated email for %username',
          ['%duplicate_name' => $accountDuplicateMail->getAccountName()],
          'ldap_authentication'
        );

        $this->replaceUserMailWithTemplate();
        $this->emailTemplateUsed = TRUE;
        // Recheck with the template email to make sure it doesn't also exist.
        if ($accountDuplicateMail = user_load_by_mail($this->ldapUser['mail'])) {
          $emailAvailable = FALSE;
        }
        else {
          $emailAvailable = TRUE;
        }
      }
      if (!$emailAvailable) {
        /*
         * Username does not exist but email does. Since
         * user_external_login_register does not deal with mail attribute and
         * the email conflict error needs to be caught beforehand, need to throw
         * error here.
         */
        $this->logger->error(
          'LDAP user with DN %dn has email address (%mail) conflict with a Drupal user %duplicate_name', [
            '%dn' => $this->ldapUser['dn'],
            '%duplicate_name' => $accountDuplicateMail->getAccountName(),
          ]
        );

        drupal_set_message($this->t('Another user already exists in the system with the same email address. You should contact the system administrator in order to solve this conflict.'), 'error');
        return FALSE;
      }

    }

    // Do not provision Drupal account if provisioning disabled.
    if (!LdapConfiguration::provisionAvailableToDrupal(self::PROVISION_DRUPAL_USER_ON_USER_AUTHENTICATION)) {
      $this->logger->error(
        'Drupal account for authname=%authname does not exist and provisioning of Drupal accounts on authentication is not enabled',
        ['%authname' => $this->authName]
      );
      return FALSE;
    }

    /*
     * New ldap_authentication provisioned account could let
     * user_external_login_register create the account and set authmaps, but
     * would need to add mail and any other user->data data in hook_user_presave
     * which would mean requerying LDAP or having a global variable. At this
     * point the account does not exist, so there is no reason not to create
     * it here.
     */

    if ($this->configFactory->get('ldap_user.settings')->get('acctCreation') == self::ACCOUNT_CREATION_USER_SETTINGS_FOR_LDAP &&
      $this->configFactory->get('user.settings')->get('register') == USER_REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL
    ) {
      // If admin approval required, set status to 0.
      $user_values = ['name' => $this->drupalUserName, 'status' => 0];
    }
    else {
      $user_values = ['name' => $this->drupalUserName, 'status' => 1];
    }

    if ($this->emailTemplateUsed) {
      $user_values['mail'] = $this->ldapUser['mail'];
    }

    $processor = new DrupalUserProcessor();
    $result = $processor->provisionDrupalAccount($user_values);

    if (!$result) {
      $this->logger->error(
        'Failed to find or create %drupal_accountname on logon.',
        ['%drupal_accountname' => $this->drupalUserName]
        );
      if ($this->formState) {
        $this->formState->setErrorByName('name', $this->t(
          'Server Error: Failed to create Drupal user account for %drupal_accountname',
          ['%drupal_accountname' => $this->drupalUserName])
        );
      }
      return FALSE;
    }
    else {
      $this->drupalUser = $processor->getUserAccount();
      return TRUE;
    }
  }

  /**
   * Connect to server.
   *
   * @return bool
   *   Connection successful.
   */
  private function connectToServer() {
    $result = $this->serverDrupalUser->connect();
    if ($result != Server::LDAP_SUCCESS) {
      // self::AUTHENTICATION_FAILURE_CONNECTION.
      $this->detailLog->log(
        '%username: Failed connecting to %id.', [
          '%username' => $this->authName,
          '%id' => $this->serverDrupalUser->id(),
        ],
        'ldap_authentication'
      );

      // Next server, please.
      return FALSE;
    }
    else {
      $this->detailLog->log(
        '%username: Success at connecting to %id', [
          '%username' => $this->authName,
          '%id' => $this->serverDrupalUser->id(),
        ], 'ldap_authentication'
      );
    }
    return TRUE;
  }

  /**
   * Bind to server.
   *
   * @param string $password
   *   User password.
   *
   * @return mixed
   *   Success or failure result.
   */
  private function bindToServer($password) {
    $bindResult = FALSE;
    $bindMethod = $this->serverDrupalUser->get('bind_method');
    if ($bindMethod == 'user') {
      foreach ($this->serverDrupalUser->getBaseDn() as $basedn) {
        $search = ['%basedn', '%username'];
        $replace = [$basedn, $this->authName];
        CredentialsStorage::storeUserDn(str_replace($search, $replace, $this->serverDrupalUser->get('user_dn_expression')));
        CredentialsStorage::testCredentials(TRUE);
        $bindResult = $this->serverDrupalUser->bind();
        if ($bindResult == Server::LDAP_SUCCESS) {
          break;
        }
      }
    }
    else {
      $bindResult = $this->serverDrupalUser->bind();
    }

    if ($bindResult != Server::LDAP_SUCCESS) {
      $this->detailLog->log(
        '%username: Trying server %id (bind method: %bind_method). Error: %err_text', [
          '%username' => $this->authName,
          '%id' => $this->serverDrupalUser->id(),
          '%err_text' => $this->serverDrupalUser->formattedError($bindResult),
          '%bind_method' => $this->serverDrupalUser->get('bind_method'),
        ], 'ldap_authentication'
      );

      if ($this->serverDrupalUser->get('bind_method') == 'user') {
        return self::AUTHENTICATION_FAILURE_CREDENTIALS;
      }
      else {
        return self::AUTHENTICATION_FAILURE_BIND;

      }
    }
    return 'success';
  }

  /**
   * Bind to SSO server.
   *
   * @return bool
   *   Binding successful.
   */
  private function bindToServerSso() {
    $bindResult = FALSE;

    if ($this->serverDrupalUser->get('bind_method') == 'user') {
      $this->logger
        ->error('Trying to use SSO with user bind method.');
      $this->logger
        ->debug('No bind method set in ldap_server->bind_method in ldap_authentication_user_login_authenticate_validate.');
      return self::AUTHENTICATION_FAILURE_CREDENTIALS;
    }
    else {
      $bindResult = $this->serverDrupalUser->bind();
    }

    if ($bindResult != Server::LDAP_SUCCESS) {
      $this->detailLog->log(
        '%username: Trying server %id where bind_method = %bind_method.  Error: %err_text',
        [
          '%username' => $this->authName,
          '%bind_method' => $this->serverDrupalUser->get('bind_method'),
          '%err_text' => $this->serverDrupalUser->formattedError($bindResult),
        ],
        'ldap_authentication'
      );

      return self::AUTHENTICATION_FAILURE_BIND;
    }
    return 'success';
  }

  /**
   * Returns the derived user account.
   *
   * @return \Drupal\user\Entity\User
   *   User account.
   */
  public function getDrupalUser() {
    return $this->drupalUser;
  }

}
