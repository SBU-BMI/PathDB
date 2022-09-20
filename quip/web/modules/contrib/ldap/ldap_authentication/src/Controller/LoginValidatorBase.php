<?php

declare(strict_types = 1);

namespace Drupal\ldap_authentication\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\externalauth\Authmap;
use Drupal\ldap_authentication\AuthenticationServers;
use Drupal\ldap_servers\Helper\CredentialsStorage;
use Drupal\ldap_servers\LdapBridgeInterface;
use Drupal\ldap_servers\LdapUserManager;
use Drupal\ldap_servers\Logger\LdapDetailLog;
use Drupal\ldap_servers\LdapUserAttributesInterface;
use Drupal\ldap_user\Processor\DrupalUserProcessor;
use Drupal\user\UserInterface;
use Symfony\Component\Ldap\Entry;

/**
 * Handles the actual testing of credentials and authentication of users.
 */
abstract class LoginValidatorBase implements LdapUserAttributesInterface, LoginValidatorInterface {

  use StringTranslationTrait;

  /**
   * Failure value.
   *
   * @var int
   */
  public const AUTHENTICATION_FAILURE_UNKNOWN = 0;

  /**
   * Failure value.
   *
   * @var int
   */
  public const AUTHENTICATION_FAILURE_BIND = 2;

  /**
   * Failure value.
   *
   * @var int
   */
  public const AUTHENTICATION_FAILURE_FIND = 3;

  /**
   * Failure value.
   *
   * @var int
   */
  public const AUTHENTICATION_FAILURE_DISALLOWED = 4;

  /**
   * Failure value.
   *
   * @var int
   */
  public const AUTHENTICATION_FAILURE_CREDENTIALS = 5;

  /**
   * Success value.
   *
   * @var int
   */
  public const AUTHENTICATION_SUCCESS = 6;

  /**
   * Authname.
   *
   * @var bool|string
   */
  protected $authName = FALSE;

  /**
   * Whether the external authmap is linked with the user.
   *
   * @var bool|mixed
   */
  protected $drupalUserAuthMapped = FALSE;

  /**
   * Drupal User name.
   *
   * @var bool|string
   */
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
  protected $drupalUser;

  /**
   * LDAP Entry.
   *
   * @var \Symfony\Component\Ldap\Entry
   */
  protected $ldapEntry;

  /**
   * Email template used.
   *
   * @var bool
   */
  protected $emailTemplateUsed = FALSE;

  /**
   * Email template tokens.
   *
   * @var array
   */
  protected $emailTemplateTokens = [];

  /**
   * Form State.
   *
   * @var \Drupal\Core\Form\FormState
   *
   * @todo Try to push this up into LoginValidatorLoginForm
   */
  protected $formState;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Detail log.
   *
   * @var \Drupal\ldap_servers\Logger\LdapDetailLog
   */
  protected $detailLog;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Entity type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * LDAP bridge.
   *
   * @var \Drupal\ldap_servers\LdapBridge
   */
  protected $ldapBridge;

  /**
   * External authentication mapper.
   *
   * @var \Drupal\externalauth\Authmap
   */
  protected $externalAuth;

  /**
   * Authentication servers.
   *
   * @var \Drupal\ldap_authentication\AuthenticationServers
   */
  protected $authenticationServers;

  /**
   * LDAP User Manager.
   *
   * @var \Drupal\ldap_servers\LdapUserManager
   */
  protected $ldapUserManager;

  /**
   * Messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Drupal User Processor.
   *
   * @var \Drupal\ldap_user\Processor\DrupalUserProcessor
   */
  protected $drupalUserProcessor;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\ldap_servers\Logger\LdapDetailLog $detailLog
   *   Detail log.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Logger channel.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   Module handler.
   * @param \Drupal\ldap_servers\LdapBridgeInterface $ldap_bridge
   *   LDAP bridge.
   * @param \Drupal\externalauth\Authmap $external_auth
   *   External auth.
   * @param \Drupal\ldap_authentication\AuthenticationServers $authentication_servers
   *   Authentication servers.
   * @param \Drupal\ldap_servers\LdapUserManager $ldap_user_manager
   *   Ldap user manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger.
   * @param \Drupal\ldap_user\Processor\DrupalUserProcessor $drupal_user_processor
   *   Drupal User Processor.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    LdapDetailLog $detailLog,
    LoggerChannelInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandler $module_handler,
    LdapBridgeInterface $ldap_bridge,
    Authmap $external_auth,
    AuthenticationServers $authentication_servers,
    LdapUserManager $ldap_user_manager,
    MessengerInterface $messenger,
    DrupalUserProcessor $drupal_user_processor
  ) {
    $this->configFactory = $configFactory;
    $this->config = $configFactory->get('ldap_authentication.settings');
    $this->detailLog = $detailLog;
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->ldapBridge = $ldap_bridge;
    $this->externalAuth = $external_auth;
    $this->authenticationServers = $authentication_servers;
    $this->ldapUserManager = $ldap_user_manager;
    $this->messenger = $messenger;
    $this->drupalUserProcessor = $drupal_user_processor;
  }

  /**
   * Determine if the corresponding Drupal account exists and is mapped.
   *
   * Ideally we would only ask the external authmap but are allowing matching
   * by name, too, for association handling later.
   */
  protected function initializeDrupalUserFromAuthName(): void {
    $load_by_name = $this->entityTypeManager->getStorage('user')
      ->loadByProperties(['name' => $this->authName]);
    $this->drupalUser = $load_by_name ? reset($load_by_name) : NULL;
    $authmap_uid = $this->externalAuth->getUid($this->authName, 'ldap_user');
    if (!$this->drupalUser && $authmap_uid) {
      // Drupal username differs but we have a UID in the authmap table for it.
      $this->drupalUser = $this->entityTypeManager
        ->getStorage('user')
        ->load($authmap_uid);
    }
    if ($this->drupalUser && $authmap_uid) {
      $this->drupalUserAuthMapped = TRUE;
    }
  }

  /**
   * Verifies whether the user is available or can be created.
   *
   * @return bool
   *   Whether to allow user login.
   *
   * @todo This duplicates DrupalUserProcessor->excludeUser().
   */
  protected function verifyUserAllowed(): bool {
    if ($this->config->get('skipAdministrators')) {
      $admin_roles = $this->entityTypeManager
        ->getStorage('user_role')
        ->getQuery()
        ->condition('is_admin', TRUE)
        ->execute();
      if (!empty(array_intersect($this->drupalUser->getRoles(), $admin_roles))) {
        $this->detailLog->log(
          '%username: Drupal user name maps to an administrative user and this group is excluded from LDAP authentication.',
          ['%username' => $this->authName],
          'ldap_authentication'
        );
        return FALSE;
      }
    }

    // Exclude users who have been manually flagged as excluded.
    if ($this->drupalUser->get('ldap_user_ldap_exclude')->getString() === '1') {
      $this->detailLog->log(
        '%username: User flagged as excluded.',
        ['%username' => $this->authName],
        'ldap_authentication'
      );
      return FALSE;
    }

    // Everyone else is allowed.
    $this->detailLog->log(
      '%username: Drupal user account found. Continuing on to attempt LDAP authentication.',
      ['%username' => $this->authName],
      'ldap_authentication'
    );
    return TRUE;
  }

  /**
   * Verifies whether the user is available or can be created.
   *
   * @return bool
   *   Whether to allow user login and creation.
   */
  protected function verifyAccountCreation(): bool {
    if (
      $this->configFactory->get('ldap_user.settings')->get('acctCreation') === self::ACCOUNT_CREATION_LDAP_BEHAVIOUR ||
      $this->configFactory->get('user.settings')->get('register') === UserInterface::REGISTER_VISITORS
    ) {
      $this->detailLog->log(
        '%username: Existing Drupal user account not found. Continuing on to attempt LDAP authentication', ['%username' => $this->authName],
        'ldap_authentication'
      );
      return TRUE;
    }

    $this->detailLog->log(
      '%username: Drupal user account not found and configuration is set to not create new accounts.',
      ['%username' => $this->authName],
      'ldap_authentication'
    );
    return FALSE;
  }

  /**
   * Tests the user's password.
   *
   * @return bool
   *   Valid login.
   */
  protected function testUserPassword(): bool {
    $loginValid = FALSE;
    if ($this->serverDrupalUser->get('bind_method') === 'user') {
      $loginValid = TRUE;
    }
    else {
      $this->ldapBridge->setServer($this->serverDrupalUser);
      // @todo Verify value in userPW, document!
      CredentialsStorage::storeUserDn($this->ldapEntry->getDn());
      CredentialsStorage::testCredentials(TRUE);
      $bindResult = $this->ldapBridge->bind();
      CredentialsStorage::testCredentials(FALSE);
      if ($bindResult) {
        $loginValid = TRUE;
      }
      else {
        $this->detailLog->log(
          '%username: Error testing user credentials on server %id with %bind_method.', [
            '%username' => $this->authName,
            '%bind_method' => $this->serverDrupalUser->getFormattedBind(),
            '%id' => $this->serverDrupalUser->id(),
          ], 'ldap_authentication'
        );
      }
    }
    return $loginValid;
  }

  /**
   * Provides formatting for authentication failures.
   *
   * @param int $authenticationResult
   *   Case.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Response text.
   */
  protected function additionalDebuggingResponse(int $authenticationResult): TranslatableMarkup {
    switch ($authenticationResult) {
      case self::AUTHENTICATION_FAILURE_FIND:
        $information = $this->t('(not found)');
        break;

      case self::AUTHENTICATION_FAILURE_CREDENTIALS:
        $information = $this->t('(wrong credentials)');
        break;

      case self::AUTHENTICATION_SUCCESS:
        $information = $this->t('(no issue)');
        break;

      default:
        $information = $this->t('(unknown issue)');
    }
    return $information;
  }

  /**
   * Failure response.
   *
   * @param int $authenticationResult
   *   The error code.
   */
  protected function failureResponse(int $authenticationResult): void {
    // Fail scenario 1. LDAP auth exclusive and failed  throw error so no other
    // authentication methods are allowed.
    if ($this->config->get('authenticationMode') === 'exclusive') {
      $this->detailLog->log(
        '%username: Error raised because failure at LDAP and exclusive authentication is set to true.',
        ['%username' => $this->authName], 'ldap_authentication'
      );

      $this->messenger->addError($this->t('Error: %err_text', ['%err_text' => $this->authenticationHelpText($authenticationResult)]));
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
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Human readable error text.
   */
  protected function authenticationHelpText(int $error): TranslatableMarkup {

    switch ($error) {
      case self::AUTHENTICATION_FAILURE_BIND:
        $msg = $this->t('Failed to bind to LDAP server');
        break;

      case self::AUTHENTICATION_FAILURE_DISALLOWED:
        $msg = $this->t('User disallowed');
        break;

      case self::AUTHENTICATION_FAILURE_FIND:
      case self::AUTHENTICATION_FAILURE_CREDENTIALS:
        $msg = $this->t('Sorry, unrecognized username or password.');
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
   * {@inheritdoc}
   */
  public function checkAllowedExcluded(string $authName, Entry $ldap_user): bool {

    // Do one of the exclude attribute pairs match? If user does not already
    // exists and deferring to user settings AND user settings only allow.
    foreach ($this->config->get('excludeIfTextInDn') as $test) {
      if (stripos($ldap_user->getDn(), $test) !== FALSE) {
        return FALSE;
      }
    }

    // Check if one of the allow attribute pairs match.
    if (count($this->config->get('allowOnlyIfTextInDn'))) {
      $fail = TRUE;
      foreach ($this->config->get('allowOnlyIfTextInDn') as $test) {
        if (stripos($ldap_user->getDn(), $test) !== FALSE) {
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
      $id = $this->externalAuth->getUid($authName, 'ldap_user');
      if ($id) {
        $user = $this->entityTypeManager->getStorage('user')->load($id);
      }

      if (!$user) {
        $user = $this->entityTypeManager->getStorage('user')
          ->create(['name' => $authName]);
      }

      // We are not injecting this service properly to avoid forcing this
      // dependency on authorization.
      /** @var \Drupal\user\Entity\User $user */
      /** @var \Drupal\authorization\AuthorizationController $controller */
      // @codingStandardsIgnoreLine
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
        $this->messenger->addWarning($this->t('The site logon is currently not working due to a configuration error. Please see logs for additional details.'));
        $this->logger->notice('LDAP Authentication is configured to deny users without LDAP Authorization mappings, but 0 LDAP Authorization consumers are configured.');
        return FALSE;
      }

    }

    // Allow other modules to hook in and refuse if they like.
    $hook_result = TRUE;
    $this->moduleHandler->alter('ldap_authentication_allowuser_results', $ldap_user, $authName, $hook_result);

    if (!$hook_result) {
      $this->logger->notice('Authentication Allow User Result=refused for %name', ['%name' => $authName]);
      return FALSE;
    }

    // Default to allowed.
    return TRUE;
  }

  /**
   * Update an outdated email address.
   */
  protected function fixOutdatedEmailAddress(): void {

    if ($this->config->get('emailTemplateUsageNeverUpdate') && $this->emailTemplateUsed) {
      return;
    }

    if (!$this->drupalUser) {
      return;
    }

    if ($this->drupalUser->get('mail')->value === $this->serverDrupalUser->deriveEmailFromLdapResponse($this->ldapEntry)) {
      return;
    }
    $update_type = $this->config->get('emailUpdate');

    if (in_array($update_type, ['update_notify', 'update'], TRUE)) {
      $this->drupalUser->set('mail', $this->serverDrupalUser->deriveEmailFromLdapResponse($this->ldapEntry));
      if (!$this->drupalUser->save()) {
        $this->logger
          ->error('Failed to make changes to user %username updated %changed.', [
            '%username' => $this->drupalUser->getAccountName(),
            '%changed' => $this->serverDrupalUser->deriveEmailFromLdapResponse($this->ldapEntry),
          ]
          );
      }
      elseif ($update_type === 'update_notify') {
        $this->messenger->addStatus(
          $this->t('Your e-mail has been updated to match your current account (%mail).', [
            '%mail' => $this->serverDrupalUser->deriveEmailFromLdapResponse($this->ldapEntry),
          ]
          )
        );
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
  protected function updateAuthNameFromPuid(): void {
    $puid = $this->serverDrupalUser->derivePuidFromLdapResponse($this->ldapEntry);
    if (!empty($puid)) {
      $this->drupalUser = $this->ldapUserManager->getUserAccountFromPuid($puid);
      /** @var \Drupal\user\Entity\User $userMatchingPuid */
      if ($this->drupalUser) {
        $oldName = $this->drupalUser->getAccountName();
        $this->drupalUser->setUsername($this->drupalUserName);
        $this->drupalUser->save();
        $this->externalAuth->save($this->drupalUser, 'ldap_user', $this->authName);
        $this->drupalUserAuthMapped = TRUE;
        $this->messenger->addStatus(
          $this->t('Your existing account %username has been updated to %new_username.',
            [
              '%username' => $oldName,
              '%new_username' => $this->drupalUserName,
            ]
          )
        );
      }
    }
  }

  /**
   * Validate common login constraints for user.
   *
   * @return bool
   *   Continue authentication.
   */
  protected function validateCommonLoginConstraints(): bool {

    if (!$this->authenticationServers->authenticationServersAvailable()) {
      $this->logger->error('No LDAP servers configured for authentication.');
      if ($this->formState) {
        $this->formState->setErrorByName('name', 'Server Error:  No LDAP servers configured.');
      }
      return FALSE;
    }

    $this->initializeDrupalUserFromAuthName();

    if ($this->drupalUser) {
      $result = $this->verifyUserAllowed();
    }
    else {
      $result = $this->verifyAccountCreation();
    }
    return $result;
  }

  /**
   * Derives the Drupal user name from server configuration.
   *
   * @return bool
   *   Success of deriving Drupal user name.
   */
  protected function deriveDrupalUserName(): bool {
    // If account_name_attr is set, Drupal username is different than authName.
    if ($this->serverDrupalUser->hasAccountNameAttribute()) {
      $user_name_from_attribute = $this->ldapEntry->getAttribute($this->serverDrupalUser->getAccountNameAttribute(), FALSE)[0];
      if (!$user_name_from_attribute) {
        $this->logger
          ->error('Derived Drupal username from attribute %account_name_attr returned no username for authname %authname.', [
            '%authname' => $this->authName,
            '%account_name_attr' => $this->serverDrupalUser->getAccountNameAttribute(),
          ]
          );
        return FALSE;
      }

      $this->drupalUserName = $user_name_from_attribute;
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
  protected function prepareEmailTemplateToken(): void {
    $this->emailTemplateTokens = ['@username' => $this->drupalUserName];

    if (!empty($this->config->get('emailTemplate'))) {
      $handling = $this->config->get('emailTemplateHandling');
      if (($handling === 'if_empty' && empty($this->serverDrupalUser->deriveEmailFromLdapResponse($this->ldapEntry))) || $handling === 'always') {
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
  protected function matchExistingUserWithLdap(): bool {
    if ($this->configFactory->get('ldap_user.settings')->get('userConflictResolve') === self::USER_CONFLICT_LOG) {
      $users = $this->entityTypeManager
        ->getStorage('user')
        ->loadByProperties(['mail' => $this->serverDrupalUser->deriveEmailFromLdapResponse($this->ldapEntry)]);

      if (count($users) > 0) {
        /** @var \Drupal\user\UserInterface $account_with_same_email */
        $account_with_same_email = reset($users);
        $this->logger
          ->error('LDAP user with DN %dn has a naming conflict with a local Drupal user %conflict_name',
            [
              '%dn' => $this->ldapEntry->getDn(),
              '%conflict_name' => $account_with_same_email->getAccountName(),
            ]
          );
      }
      $this->messenger
        ->addError($this->t('Another user already exists in the system with the same login name. You should contact the system administrator in order to solve this conflict.'));
      return FALSE;
    }

    $this->externalAuth->save($this->drupalUser, 'ldap_user', $this->authName);
    $this->drupalUserAuthMapped = TRUE;
    $this->detailLog->log(
      'Set authmap for LDAP user %username',
      ['%username' => $this->authName],
      'ldap_authentication'
    );
    return TRUE;
  }

  /**
   * Replace user email address with template.
   */
  protected function replaceUserMailWithTemplate(): void {
    // Fallback template in case one was not specified.
    $template = '@username@localhost';
    if (!empty($this->config->get('emailTemplate'))) {
      $template = $this->config->get('emailTemplate');
    }
    $this->ldapEntry->setAttribute($this->serverDrupalUser->get('mail_attr'), [
      (string) (new FormattableMarkup($template, $this->emailTemplateTokens)),
    ]);
  }

  /**
   * Provision the Drupal user.
   *
   * @return bool
   *   Provisioning successful.
   */
  protected function provisionDrupalUser(): bool {
    $users = $this->entityTypeManager
      ->getStorage('user')
      ->loadByProperties(['mail' => $this->serverDrupalUser->deriveEmailFromLdapResponse($this->ldapEntry)]);
    $accountDuplicateMail = $users ? reset($users) : FALSE;
    // Do not provision Drupal account if another account has same email.
    if ($accountDuplicateMail) {
      $emailAvailable = FALSE;
      if (!$this->emailTemplateUsed && $this->config->get('emailTemplateUsageResolveConflict')) {
        $this->detailLog->log(
          'Conflict detected, using template generated email for %username',
          ['%duplicate_name' => $accountDuplicateMail->getAccountName()],
          'ldap_authentication'
        );

        $this->replaceUserMailWithTemplate();
        $this->emailTemplateUsed = TRUE;
        // Recheck with the template email to make sure it doesn't also exist.
        $users = $this->entityTypeManager
          ->getStorage('user')
          ->loadByProperties(['mail' => $this->serverDrupalUser->deriveEmailFromLdapResponse($this->ldapEntry)]);
        $accountDuplicateMail = $users ? reset($users) : FALSE;
        if ($accountDuplicateMail) {
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
            '%dn' => $this->ldapEntry->getDn(),
            '%duplicate_name' => $accountDuplicateMail->getAccountName(),
          ]
        );

        $this->messenger
          ->addError($this->t('Another user already exists in the system with the same email address. You should contact the system administrator in order to solve this conflict.'));
        return FALSE;
      }

    }

    // Do not provision Drupal account if provisioning disabled.
    $triggers = $this->configFactory->get('ldap_user.settings')
      ->get('drupalAcctProvisionTriggers');
    if (!in_array(self::PROVISION_DRUPAL_USER_ON_USER_AUTHENTICATION, $triggers, TRUE)) {
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
    if (
      $this->configFactory->get('ldap_user.settings')->get('acctCreation') === self::ACCOUNT_CREATION_USER_SETTINGS_FOR_LDAP &&
      $this->configFactory->get('user.settings')->get('register') === UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL
    ) {
      // If admin approval required, set status to 0.
      $user_values = ['name' => $this->drupalUserName, 'status' => 0];
    }
    else {
      $user_values = ['name' => $this->drupalUserName, 'status' => 1];
    }

    if ($this->emailTemplateUsed) {
      $user_values['mail'] = $this->serverDrupalUser->deriveEmailFromLdapResponse($this->ldapEntry);
    }

    $result = $this->drupalUserProcessor->createDrupalUserFromLdapEntry($user_values);

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

    $this->drupalUser = $this->drupalUserProcessor->getUserAccount();
    return TRUE;
  }

  /**
   * Bind to server.
   *
   * @return int
   *   Success or failure result.
   */
  protected function bindToServer(): int {
    if ($this->serverDrupalUser->get('bind_method') === 'user') {
      return $this->bindToServerAsUser();
    }

    $bindResult = $this->ldapBridge->bind();

    if (!$bindResult) {
      $this->detailLog->log(
        '%username: Unsuccessful with server %id (bind method: %bind_method)', [
          '%username' => $this->authName,
          '%id' => $this->serverDrupalUser->id(),
          '%bind_method' => $this->serverDrupalUser->get('bind_method'),
        ], 'ldap_authentication'
      );

      return self::AUTHENTICATION_FAILURE_BIND;
    }
    return self::AUTHENTICATION_SUCCESS;
  }

  /**
   * Bind to server.
   *
   * @return int
   *   Success or failure result.
   */
  protected function bindToServerAsUser(): int {
    $bindResult = FALSE;

    foreach ($this->serverDrupalUser->getBaseDn() as $base_dn) {
      $search = ['%basedn', '%username'];
      $replace = [$base_dn, $this->authName];
      CredentialsStorage::storeUserDn(str_replace($search, $replace, $this->serverDrupalUser->getUserDnExpression()));
      CredentialsStorage::testCredentials(TRUE);
      $bindResult = $this->ldapBridge->bind();
      if ($bindResult) {
        break;
      }
    }

    if (!$bindResult) {
      $this->detailLog->log(
        '%username: Unsuccessful with server %id (bind method: %bind_method)', [
          '%username' => $this->authName,
          '%id' => $this->serverDrupalUser->id(),
          '%bind_method' => $this->serverDrupalUser->get('bind_method'),
        ], 'ldap_authentication'
      );

      return self::AUTHENTICATION_FAILURE_CREDENTIALS;
    }
    return self::AUTHENTICATION_SUCCESS;
  }

  /**
   * {@inheritdoc}
   */
  public function getDrupalUser(): ?UserInterface {
    return $this->drupalUser;
  }

}
