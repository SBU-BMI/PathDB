<?php

namespace Drupal\ldap_user\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Url;
use Drupal\ldap_servers\ServerFactory;
use Drupal\ldap_user\Helper\ExternalAuthenticationHelper;
use Drupal\ldap_user\Helper\LdapConfiguration;
use Drupal\ldap_servers\LdapUserAttributesInterface;
use Drupal\ldap_user\Processor\DrupalUserProcessor;
use Drupal\ldap_user\Processor\LdapUserProcessor;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A form to allow the administrator to query LDAP.
 */
class LdapUserTestForm extends FormBase implements LdapUserAttributesInterface {

  private static $syncTriggerOptions;

  protected $request;
  protected $serverFactory;
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ldap_user_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(RequestStack $request_stack, ServerFactory $server_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->request = $request_stack->getCurrentRequest();
    $this->serverFactory = $server_factory;
    $this->entityTypeManager = $entity_type_manager;

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
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('ldap.servers'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $op = NULL) {

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
      '#required' => 1,
      '#size' => 30,
      '#maxlength' => 255,
      '#description' => $this->t("The user need not exist in Drupal and testing will not affect the user's LDAP or Drupal Account."),
    ];

    $form['action'] = [
      '#type' => 'radios',
      '#title' => $this->t('Actions/Event Handler to Test'),
      '#required' => 0,
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
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $username = $form_state->getValue(['testing_drupal_username']);
    $selected_action = $form_state->getValue(['action']);

    $config = $this->configFactory()->get('ldap_user.settings')->get();
    $processor = new DrupalUserProcessor();
    $ldapProcessor = new LdapUserProcessor();
    $user_ldap_entry = FALSE;

    if ($config['drupalAcctProvisionServer']) {
      $user_ldap_entry = $this->serverFactory->getUserDataFromServerByIdentifier($username, $config['drupalAcctProvisionServer']);
    }
    if ($config['ldapEntryProvisionServer']) {
      if (!$user_ldap_entry) {
        $user_ldap_entry = $this->serverFactory->getUserDataFromServerByIdentifier($username, $config['ldapEntryProvisionServer']);
      }
    }
    $results = [];
    $results['username'] = $username;
    $results['related LDAP entry (before provisioning or syncing)'] = $user_ldap_entry;

    /** @var \Drupal\user\Entity\User $account */
    $existingAccount = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => $username]);
    $existingAccount = $existingAccount ? reset($existingAccount) : FALSE;
    if ($existingAccount) {
      $results['user entity (before provisioning or syncing)'] = $existingAccount->toArray();
      $results['User Authmap'] = ExternalAuthenticationHelper::getUserIdentifierFromMap($existingAccount->id());
    }
    else {
      $results['User Authmap'] = 'No authmaps available.  Authmaps only shown if user account exists beforehand';
    }

    $account = ['name' => $username];
    $sync_trigger_description = self::$syncTriggerOptions[$selected_action];
    foreach ([self::PROVISION_TO_DRUPAL, self::PROVISION_TO_LDAP] as $direction) {
      if ($this->provisionEnabled($direction, $selected_action)) {
        if ($direction == self::PROVISION_TO_DRUPAL) {
          $processor->provisionDrupalAccount($account);
          $results['provisionDrupalAccount method results']["context = $sync_trigger_description"]['proposed'] = $account;
        }
        else {
          $provision_result = $ldapProcessor->provisionLdapEntry($username, NULL);
          $results['provisionLdapEntry method results']["context = $sync_trigger_description"] = $provision_result;
        }
      }
      else {
        if ($direction == self::PROVISION_TO_DRUPAL) {
          $results['provisionDrupalAccount method results']["context = $sync_trigger_description"] = 'Not enabled.';
        }
        else {
          $results['provisionLdapEntry method results']["context = $sync_trigger_description"] = 'Not enabled.';
        }
      }
    }

    if (function_exists('dpm')) {
      dpm($results);
    }
    else {
      drupal_set_message($this->t('This form will not display results unless the devel module is enabled.'), 'warning');
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
   * @param int $direction
   *   self::PROVISION_TO_DRUPAL or self::PROVISION_TO_LDAP.
   * @param int $provision_trigger
   *   Provision trigger, see events above, such as 'sync', 'provision',
   *   'delete_ldap_entry', 'delete_drupal_entry', 'cancel_drupal_entry'.
   *
   * @deprecated
   *
   * @return bool
   *   Provisioning enabled.
   *   TODO: Move to ldapusertestform and/or kill.
   */
  private function provisionEnabled($direction, $provision_trigger) {
    $result = FALSE;

    if ($direction == self::PROVISION_TO_LDAP) {
      $result = LdapConfiguration::provisionAvailableToLdap($provision_trigger);

    }
    elseif ($direction == self::PROVISION_TO_DRUPAL) {
      $result = LdapConfiguration::provisionAvailableToDrupal($provision_trigger);
    }

    return $result;
  }

}
