<?php

declare(strict_types = 1);

namespace Drupal\ldap_user\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Url;
use Drupal\externalauth\Authmap;
use Drupal\ldap_servers\LdapUserManager;
use Drupal\ldap_servers\LdapUserAttributesInterface;
use Drupal\ldap_user\Processor\DrupalUserProcessor;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A form to allow the administrator to query LDAP.
 */
class LdapUserTestForm extends FormBase implements LdapUserAttributesInterface {

  /**
   * Sync Trigger Options.
   *
   * @var array
   */
  private static $syncTriggerOptions;

  /**
   * Request.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected $request;

  /**
   * LDAP User Manager.
   *
   * @var \Drupal\ldap_servers\LdapUserManager
   */
  protected $ldapUserManager;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Externalauth.
   *
   * @var \Drupal\externalauth\Authmap
   */
  protected $externalAuth;

  /**
   * Drupal User Processor.
   *
   * @var \Drupal\ldap_user\Processor\DrupalUserProcessor
   */
  protected $drupalUserProcessor;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ldap_user_test_form';
  }

  /**
   * LdapUserTestForm constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request stack.
   * @param \Drupal\ldap_servers\LdapUserManager $ldap_user_manager
   *   LDAP user manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\externalauth\Authmap $external_auth
   *   External auth.
   * @param \Drupal\ldap_user\Processor\DrupalUserProcessor $drupal_user_processor
   *   Drupal user processor.
   */
  public function __construct(
    RequestStack $request_stack,
    LdapUserManager $ldap_user_manager,
    EntityTypeManagerInterface $entity_type_manager,
    Authmap $external_auth,
    DrupalUserProcessor $drupal_user_processor
  ) {
    $this->request = $request_stack->getCurrentRequest();
    $this->ldapUserManager = $ldap_user_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->externalAuth = $external_auth;
    $this->drupalUserProcessor = $drupal_user_processor;

    self::$syncTriggerOptions = [
      self::PROVISION_DRUPAL_USER_ON_USER_UPDATE_CREATE => $this->t('On sync to Drupal user create or update. Requires a server with binding method of "Service Account Bind" or "Anonymous Bind".'),
      self::PROVISION_DRUPAL_USER_ON_USER_AUTHENTICATION => $this->t('On create or sync to Drupal user when successfully authenticated with LDAP credentials. (Requires LDAP Authentication module).'),
      self::PROVISION_DRUPAL_USER_ON_USER_ON_MANUAL_CREATION => $this->t('On manual creation of Drupal user from admin/people/create and "Create corresponding LDAP entry" is checked'),
      self::PROVISION_LDAP_ENTRY_ON_USER_ON_USER_UPDATE_CREATE => $this->t('On creation or sync of an LDAP entry when a Drupal account is created or updated. Only applied to accounts with a status of approved.'),
      self::PROVISION_LDAP_ENTRY_ON_USER_ON_USER_AUTHENTICATION => $this->t('On creation or sync of an LDAP entry when a user authenticates.'),
      self::PROVISION_LDAP_ENTRY_ON_USER_ON_USER_DELETE => $this->t('On deletion of an LDAP entry when the corresponding Drupal Account is deleted.  This only applies when the LDAP entry was provisioned by Drupal by the LDAP User module.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): LdapUserTestForm {
    return new static(
      $container->get('request_stack'),
      $container->get('ldap.user_manager'),
      $container->get('entity_type.manager'),
      $container->get('externalauth.authmap'),
      $container->get('ldap.drupal_user_processor')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $op = NULL): array {

    $form['#prefix'] = $this->t('<h1>Debug LDAP synchronization events</h1>');

    $form['usage'] = [
      '#markup' => $this->t("This form is for debugging issues with specific provisioning events. If you want to test your setup in general, try the server's test page first."),
    ];
    $form['warning'] = [
      '#markup' => '<h3>' . $this->t('If you trigger the event this will modify your data.') . '</h3>' . $this->t('When in doubt, always work on a staging environment.'),
    ];

    $form['testing_drupal_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Testing Drupal Username'),
      '#default_value' => $this->request->query->get('username'),
      '#required' => TRUE,
      '#size' => 30,
      '#maxlength' => 255,
      '#description' => $this->t("The user need not exist in Drupal and testing will not affect the user's LDAP or Drupal Account."),
    ];

    $form['action'] = [
      '#type' => 'radios',
      '#title' => $this->t('Actions/Event Handler to Test'),
      '#default_value' => $this->request->query->get('action'),
      '#options' => self::$syncTriggerOptions,
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Test'),
      '#weight' => 100,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    $username = $form_state->getValue(['testing_drupal_username']);
    $selected_action = $form_state->getValue(['action']);

    $config = $this->configFactory()->get('ldap_user.settings')->get();

    $user_ldap_entry = FALSE;

    if ($config['drupalAcctProvisionServer']) {
      $this->ldapUserManager->setServerById($config['drupalAcctProvisionServer']);
      $user_ldap_entry = $this->ldapUserManager->getUserDataByIdentifier($username);
    }
    if ($config['ldapEntryProvisionServer'] && !$user_ldap_entry) {
      $this->ldapUserManager->setServerById($config['ldapEntryProvisionServer']);
      $user_ldap_entry = $this->ldapUserManager->getUserDataByIdentifier($username);
    }
    $results = [];
    $results['username'] = $username;
    $results['related LDAP entry (before provisioning or syncing)'] = $user_ldap_entry;

    /** @var \Drupal\user\Entity\User $account */
    $existingAccount = $this->entityTypeManager->getStorage('user')
      ->loadByProperties(['name' => $username]);
    $existingAccount = $existingAccount ? reset($existingAccount) : FALSE;
    if ($existingAccount) {
      $results['user entity (before provisioning or syncing)'] = $existingAccount->toArray();
      $results['User Authmap'] = $this->externalAuth->get($existingAccount->id(), 'ldap_user');
    }
    else {
      $results['User Authmap'] = 'No authmaps available.  Authmaps only shown if user account exists beforehand';
    }

    $account = ['name' => $username];
    $sync_trigger_description = self::$syncTriggerOptions[$selected_action];
    foreach ([self::PROVISION_TO_DRUPAL, self::PROVISION_TO_LDAP] as $direction) {
      if ($this->provisionEnabled($direction, $selected_action)) {
        if ($direction === self::PROVISION_TO_DRUPAL) {
          $this->drupalUserProcessor->createDrupalUserFromLdapEntry($account);
          $results['createDrupalUserFromLdapEntry method results']["context = $sync_trigger_description"]['proposed'] = $account;
        }
        else {
          // @FIXME
          // This is not testing all supported event,
          // only the new user created event.
          // The form needs to be restructured in general for those!
          // $event = new LdapNewUserCreatedEvent($account);
          // /** @var EventDispatcher $dispatcher */
          // $dispatcher = \Drupal::service('event_dispatcher');
          // $dispatcher->dispatch($event, LdapNewUserCreatedEvent::EVENT_NAME);
          // @FIXME.
          $results['provisionLdapEntry method results']["context = $sync_trigger_description"] = 'Test not ported';
        }
      }
      else {
        if ($direction === self::PROVISION_TO_DRUPAL) {
          $results['createDrupalUserFromLdapEntry method results']["context = $sync_trigger_description"] = 'Not enabled.';
        }
        else {
          $results['provisionLdapEntry method results']["context = $sync_trigger_description"] = 'Not enabled.';
        }
      }
    }

    if (\function_exists('kint')) {
      // @phpcs:ignore
      kint($results);
    }
    else {
      $this->messenger()
        ->addWarning($this->t('This form will not display results unless the devel and kint module is enabled.'));
    }

    $params = [
      'action' => $selected_action,
      'username' => $username,
    ];
    $form_state->setRedirectUrl(Url::fromRoute('ldap_user.test_form', $params));
  }

  /**
   * Given a $prov_event determine if LDAP user configuration supports it.
   *
   * This is overall, not a per field syncing configuration.
   *
   * @param string $direction
   *   self::PROVISION_TO_DRUPAL or self::PROVISION_TO_LDAP.
   * @param string $provision_trigger
   *   Provision trigger, see events above, such as 'sync', 'provision',
   *   'delete_ldap_entry', 'delete_drupal_entry', 'cancel_drupal_entry'.
   *
   * @return bool
   *   Provisioning enabled.
   */
  private function provisionEnabled(string $direction, string $provision_trigger): bool {
    $result = FALSE;

    $config = $this->configFactory()->get('ldap_user.settings');
    if ($direction === self::PROVISION_TO_LDAP) {
      $result = \in_array($provision_trigger, $config->get('ldapEntryProvisionTriggers'), TRUE);
    }
    elseif ($direction === self::PROVISION_TO_DRUPAL) {
      $result = \in_array($provision_trigger, $config->get('drupalAcctProvisionTriggers'), TRUE);
    }

    return $result;
  }

}
