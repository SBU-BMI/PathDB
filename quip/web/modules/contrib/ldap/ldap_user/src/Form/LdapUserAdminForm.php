<?php

declare(strict_types = 1);

namespace Drupal\ldap_user\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\ldap_servers\LdapUserAttributesInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the form to configure user configuration and field mapping.
 */
class LdapUserAdminForm extends ConfigFormBase implements LdapUserAttributesInterface {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ldap_user_admin_form';
  }

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal account provisioning server options.
   *
   * @var array
   */
  protected $drupalAcctProvisionServerOptions = [];

  /**
   * LDAP Entry Provisioning server options.
   *
   * @var array
   */
  protected $ldapEntryProvisionServerOptions = [];

  /**
   * Current config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $currentConfig;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ModuleHandler $module_handler,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($config_factory);
    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;

    $storage = $this->entityTypeManager->getStorage('ldap_server');
    $ids = $storage
      ->getQuery()
      ->condition('status', 1)
      ->execute();
    foreach ($storage->loadMultiple($ids) as $sid => $server) {
      /** @var \Drupal\ldap_servers\Entity\Server $server */
      $enabled = ($server->get('status')) ? 'Enabled' : 'Disabled';
      $this->drupalAcctProvisionServerOptions[$sid] = $server->label() . ' (' . $server->get('address') . ') Status: ' . $enabled;
      $this->ldapEntryProvisionServerOptions[$sid] = $server->label() . ' (' . $server->get('address') . ') Status: ' . $enabled;
    }

    $this->drupalAcctProvisionServerOptions['none'] = $this->t('None');
    $this->ldapEntryProvisionServerOptions['none'] = $this->t('None');
    $this->currentConfig = $this->config('ldap_user.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): LdapUserAdminForm {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames(): array {
    return ['ldap_user.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('ldap_user.settings');

    // If nothing except "None" is present, skip the rest of the form.
    if (count($this->drupalAcctProvisionServerOptions) === 1) {
      $url = Url::fromRoute('entity.ldap_server.collection');
      $edit_server_link = Link::fromTextAndUrl($this->t('@path', ['@path' => 'LDAP Servers']), $url)->toString();
      $message = $this->t('At least one LDAP server must be configured and <em>enabled</em> before you can configure user settings. Please go to @link, to configure an LDAP server.',
        ['@link' => $edit_server_link]
      );
      $form['intro'] = [
        '#type' => 'item',
        '#markup' => $this->t('<h1>LDAP User Settings</h1>') . $message,
      ];
      return $form;
    }

    $form['intro'] = [
      '#type' => 'item',
      '#markup' => $this->t('<h1>LDAP User Settings</h1>'),
    ];

    $form['server_mapping_preamble'] = [
      '#type' => 'markup',
      '#markup' => $this->t('The relationship between a Drupal user and an LDAP entry is defined within the LDAP server configurations. The mappings below are for user fields, properties and data that are not automatically mapped elsewhere. <br>Read-only mappings are generally configured on the server configuration page and shown here as a convenience to you.'),
    ];

    $form['manual_drupal_account_editing'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Manual Drupal Account Creation'),
    ];

    $form['manual_drupal_account_editing']['manualAccountConflict'] = [
      '#type' => 'radios',
      '#options' => [
        self::MANUAL_ACCOUNT_CONFLICT_LDAP_ASSOCIATE => $this->t('Associate accounts, if available.'),
        self::MANUAL_ACCOUNT_CONFLICT_NO_LDAP_ASSOCIATE => $this->t('Do not associate accounts, allow conflicting accounts.'),
        self::MANUAL_ACCOUNT_CONFLICT_REJECT => $this->t('Do not associate accounts, reject conflicting accounts.'),
        self::MANUAL_ACCOUNT_CONFLICT_SHOW_OPTION_ON_FORM => $this->t('Show option on user create form to associate or not.'),
      ],
      '#title' => $this->t('How to resolve LDAP conflicts with manually created user accounts.'),
      '#description' => $this->t('This applies only to accounts created manually through admin/people/create for which an LDAP entry can be found on the LDAP server selected in "LDAP Servers Providing Provisioning Data"'),
      '#default_value' => $config->get('manualAccountConflict'),
    ];

    $form['basic_to_drupal'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Basic Provisioning to Drupal Account Settings'),
    ];

    $form['basic_to_drupal']['drupalAcctProvisionServer'] = [
      '#type' => 'radios',
      '#title' => $this->t('LDAP Servers Providing Provisioning Data'),
      '#required' => TRUE,
      '#default_value' => $config->get('drupalAcctProvisionServer') ? $config->get('drupalAcctProvisionServer') : 'none',
      '#options' => $this->drupalAcctProvisionServerOptions,
      '#description' => $this->t('Choose the LDAP server configuration to use in provisioning Drupal users and their user fields.'),
      '#states' => [
        // Action to take.
        'enabled' => [
          ':input[name=drupalAcctProvisionTriggers]' => ['value' => self::PROVISION_DRUPAL_USER_ON_USER_AUTHENTICATION],
        ],
      ],
    ];

    $form['basic_to_drupal']['drupalAcctProvisionTriggers'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Drupal Account Provisioning Events'),
      '#required' => FALSE,
      '#default_value' => $config->get('drupalAcctProvisionTriggers'),
      '#options' => [
        self::PROVISION_DRUPAL_USER_ON_USER_AUTHENTICATION => $this->t('Create or Sync to Drupal user on successful authentication with LDAP credentials. (Requires LDAP Authentication module).'),
        self::PROVISION_DRUPAL_USER_ON_USER_UPDATE_CREATE => $this->t('Create or Sync to Drupal user anytime a Drupal user account is created or updated. Requires a server with binding method of "Service Account Bind" or "Anonymous Bind".'),
      ],
      '#description' => $this->t('Which user fields and properties are synced on create or sync is determined in the "Provisioning from LDAP to Drupal mappings" table below in the right two columns.'),
    ];

    $form['basic_to_drupal']['userConflictResolve'] = [
      '#type' => 'radios',
      '#title' => $this->t('Existing Drupal User Account Conflict'),
      '#required' => TRUE,
      '#default_value' => $config->get('userConflictResolve'),
      '#options' => [
        self::USER_CONFLICT_LOG => $this->t("Don't associate Drupal account with LDAP. Require user to use Drupal password. Log the conflict"),
        self::USER_CONFLICT_ATTEMPT_RESOLVE => $this->t('Associate Drupal account with the LDAP entry. This option is useful for creating accounts and assigning roles before an LDAP user authenticates.'),
      ],
      '#description' => $this->t('What should be done if a local Drupal or other external user account already exists with the same login name.'),
    ];

    $form['basic_to_drupal']['acctCreation'] = [
      '#type' => 'radios',
      '#title' => $this->t('Application of Drupal Account settings to LDAP Authenticated Users'),
      '#required' => TRUE,
      '#default_value' => $config->get('acctCreation'),
      '#options' => [
        self::ACCOUNT_CREATION_LDAP_BEHAVIOUR => $this->t('Account creation settings at /admin/config/people/accounts/settings do not affect "LDAP Associated" Drupal accounts.'),
        self::ACCOUNT_CREATION_USER_SETTINGS_FOR_LDAP => $this->t('Account creation policy at /admin/config/people/accounts/settings applies to both Drupal and LDAP Authenticated users. "Visitors" option automatically creates and account when they successfully LDAP authenticate. "Admin" and "Admin with approval" do not allow user to authenticate until the account is approved.'),
      ],
    ];

    $form['basic_to_drupal']['disableAdminPasswordField'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable the password fields at /admin/create/people and generate a random password.'),
      '#default_value' => $config->get('disableAdminPasswordField'),
    ];

    $form['basic_to_drupal']['userUpdateMechanism'] = [
      '#type' => 'fieldset',
      '#title' => 'Periodic user update mechanism',
      '#description' => $this->t('Allows you to sync the result of an LDAP query with your users. Creates new users and updates existing ones.'),
    ];

    if ($this->moduleHandler->moduleExists('ldap_query')) {
      $updateMechanismOptions = ['none' => $this->t('Do not update')];

      $storage = $this->entityTypeManager->getStorage('ldap_query_entity');
      $ids = $storage
        ->getQuery()
        ->condition('status', 1)
        ->execute();
      $queries = $storage->loadMultiple($ids);
      foreach ($queries as $query) {
        $updateMechanismOptions[$query->id()] = $query->label();
      }
      $form['basic_to_drupal']['userUpdateMechanism']['userUpdateCronQuery'] = [
        '#type' => 'select',
        '#title' => $this->t('LDAP query containing the list of entries to update'),
        '#required' => FALSE,
        '#default_value' => $config->get('userUpdateCronQuery'),
        '#options' => $updateMechanismOptions,
      ];

      $form['basic_to_drupal']['userUpdateMechanism']['userUpdateCronInterval'] = [
        '#type' => 'select',
        '#title' => $this->t('How often should each user be synced?'),
        '#default_value' => $config->get('userUpdateCronInterval'),
        '#options' => [
          'always' => $this->t('On every cron run'),
          'daily' => $this->t('Daily'),
          'weekly' => $this->t('Weekly'),
          'monthly' => $this->t('Monthly'),
        ],
      ];
    }
    else {
      $form['basic_to_drupal']['userUpdateMechanism']['userUpdateCronQuery'] = [
        '#type' => 'value',
        '#value' => 'none',
      ];
      $form['basic_to_drupal']['userUpdateMechanism']['userUpdateCronInterval'] = [
        '#type' => 'value',
        '#value' => 'monthly',
      ];
      $form['basic_to_drupal']['userUpdateMechanism']['notice'] = [
        '#markup' => $this->t('Only available with LDAP Query enabled.'),
      ];
    }

    $form['basic_to_drupal']['orphanedAccounts'] = [
      '#type' => 'fieldset',
      '#title' => 'Periodic orphaned accounts update mechanism',
      '#description' => $this->t('<strong>Warning: Use this feature at your own risk!</strong>'),
    ];

    $form['basic_to_drupal']['orphanedAccounts']['orphanedCheckQty'] = [
      '#type' => 'textfield',
      '#size' => 10,
      '#title' => $this->t('Number of users to check each cron run.'),
      '#default_value' => $config->get('orphanedCheckQty'),
      '#required' => FALSE,
    ];

    $account_options = [];
    $account_options['ldap_user_orphan_do_not_check'] = $this->t('Do not check for orphaned Drupal accounts.');
    $account_options['ldap_user_orphan_email'] = $this->t('Perform no action, but email list of orphaned accounts. (All the other options will send email summaries also.)');
    foreach (user_cancel_methods()['#options'] as $option_name => $option_title) {
      $account_options[$option_name] = $option_title;
    }

    $form['basic_to_drupal']['orphanedAccounts']['orphanedDrupalAcctBehavior'] = [
      '#type' => 'radios',
      '#title' => $this->t('Action to perform on Drupal accounts that no longer have corresponding LDAP entries'),
      '#default_value' => $config->get('orphanedDrupalAcctBehavior'),
      '#options' => $account_options,
      '#description' => $this->t('It is highly recommended to fetch an email report first before attempting to disable or even delete users.'),
    ];

    $form['basic_to_drupal']['orphanedAccounts']['orphanedDrupalAcctReportingInbox'] = [
      '#type' => 'email',
      '#title' => $this->t('Report recipient email address'),
      '#default_value' => $config->get('orphanedDrupalAcctReportingInbox'),
      '#placeholder' => $this->config('system.site')->get('mail'),
      '#description' => $this->t('The email address to report orphaned accounts to. (Defaults to site-wide email address.)'),
      '#states' => [
        'invisible' => [
          ':input[name=orphanedDrupalAcctBehavior]' => [
            'value' => 'ldap_user_orphan_do_not_check',
          ],
        ],
      ],
    ];

    $form['basic_to_drupal']['orphanedAccounts']['orphanedCheckQty'] = [
      '#type' => 'textfield',
      '#size' => 10,
      '#title' => $this->t('Number of users to check each cron run.'),
      '#default_value' => $config->get('orphanedCheckQty'),
      '#required' => FALSE,
    ];

    $form['basic_to_drupal']['orphanedAccounts']['orphanedAccountCheckInterval'] = [
      '#type' => 'select',
      '#title' => $this->t('How often should each user be checked again?'),
      '#default_value' => $config->get('orphanedAccountCheckInterval'),
      '#options' => [
        'always' => $this->t('On every cron run'),
        'daily' => $this->t('Daily'),
        'weekly' => $this->t('Weekly'),
        'monthly' => $this->t('Monthly'),
      ],
      '#required' => FALSE,
    ];

    $form['basic_to_ldap'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Basic Provisioning to LDAP Settings'),
    ];

    $form['basic_to_ldap']['ldapEntryProvisionServer'] = [
      '#type' => 'radios',
      '#title' => $this->t('LDAP Servers to Provision LDAP Entries on'),
      '#required' => TRUE,
      '#default_value' => $config->get('ldapEntryProvisionServer') ?: 'none',
      '#options' => $this->ldapEntryProvisionServerOptions,
      '#description' => $this->t('Check ONE LDAP server configuration to create LDAP entries on.'),
    ];

    $form['basic_to_ldap']['ldapEntryProvisionTriggers'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('LDAP Entry Provisioning Events'),
      '#required' => FALSE,
      '#default_value' => $config->get('ldapEntryProvisionTriggers'),
      '#options' => [
        self::PROVISION_LDAP_ENTRY_ON_USER_ON_USER_UPDATE_CREATE => $this->t('Create or Sync to LDAP entry when a Drupal account is created or updated. Only applied to accounts with a status of approved.'),
        self::PROVISION_LDAP_ENTRY_ON_USER_ON_USER_AUTHENTICATION => $this->t('Create or Sync to LDAP entry when a user authenticates.'),
        self::PROVISION_LDAP_ENTRY_ON_USER_ON_USER_DELETE => $this->t('Delete LDAP entry when the corresponding Drupal Account is deleted.  This only applies when the LDAP entry was provisioned by Drupal by the LDAP User module.'),
        self::PROVISION_DRUPAL_USER_ON_USER_ON_MANUAL_CREATION => $this->t('Provide option on admin/people/create to create corresponding LDAP Entry.'),
      ],
      '#description' => $this->t('Which LDAP attributes are synced on create or sync is determined in the "Provisioning from Drupal to LDAP mappings" table below in the right two columns.'),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Save',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    $drupalAcctProvisionServer = ($form_state->getValue('drupalAcctProvisionServer') === 'none') ? NULL : $form_state->getValue('drupalAcctProvisionServer');
    $ldapEntryProvisionServer = ($form_state->getValue('ldapEntryProvisionServer') === 'none') ? NULL : $form_state->getValue('ldapEntryProvisionServer');

    $this->config('ldap_user.settings')
      ->set('drupalAcctProvisionServer', $drupalAcctProvisionServer)
      ->set('ldapEntryProvisionServer', $ldapEntryProvisionServer)
      ->set('drupalAcctProvisionTriggers', $this->reduceTriggerList($form_state->getValue('drupalAcctProvisionTriggers')))
      ->set('ldapEntryProvisionTriggers', $this->reduceTriggerList($form_state->getValue('ldapEntryProvisionTriggers')))
      ->set('userUpdateCronQuery', $form_state->getValue('userUpdateCronQuery'))
      ->set('userUpdateCronInterval', $form_state->getValue('userUpdateCronInterval'))
      ->set('orphanedDrupalAcctBehavior', $form_state->getValue('orphanedDrupalAcctBehavior'))
      ->set('orphanedDrupalAcctReportingInbox', $form_state->getValue('orphanedDrupalAcctReportingInbox'))
      ->set('orphanedCheckQty', $form_state->getValue('orphanedCheckQty'))
      ->set('orphanedAccountCheckInterval', $form_state->getValue('orphanedAccountCheckInterval'))
      ->set('userConflictResolve', $form_state->getValue('userConflictResolve'))
      ->set('manualAccountConflict', $form_state->getValue('manualAccountConflict'))
      ->set('acctCreation', $form_state->getValue('acctCreation'))
      ->set('disableAdminPasswordField', $form_state->getValue('disableAdminPasswordField'))
      ->save();
    $form_state->getValues();

    $this->messenger()->addMessage($this->t('User synchronization configuration updated.'));
  }

  /**
   * Reduce the trigger list.
   *
   * @param array $values
   *   Triggers.
   *
   * @return array
   *   Reduced triggers.
   */
  private function reduceTriggerList(array $values): array {
    $result = [];
    foreach ($values as $value) {
      if ($value !== 0) {
        $result[] = $value;
      }
    }
    return $result;
  }

}
