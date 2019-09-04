<?php

namespace Drupal\ldap_user\Form;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\ldap_query\Controller\QueryController;
use Drupal\ldap_servers\Helper\ConversionHelper;
use Drupal\ldap_servers\ServerFactory;
use Drupal\ldap_user\Helper\LdapConfiguration;
use Drupal\ldap_servers\LdapUserAttributesInterface;
use Drupal\ldap_user\Helper\SemaphoreStorage;
use Drupal\ldap_user\Helper\SyncMappingHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the form to configure user configuration and field mapping.
 */
class LdapUserAdminForm extends ConfigFormBase implements LdapUserAttributesInterface, ContainerInjectionInterface {

  protected $serverFactory;
  protected $cache;
  protected $moduleHandler;

  protected $drupalAcctProvisionServerOptions;

  protected $ldapEntryProvisionServerOptions;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, ServerFactory $server_factory, CacheBackendInterface $cache, ModuleHandler $module_handler) {
    parent::__construct($config_factory);

    $this->serverFactory = $server_factory;
    $this->cache = $cache;
    $this->moduleHandler = $module_handler;

    $ldap_servers = $this->serverFactory->getEnabledServers();
    if ($ldap_servers) {
      foreach ($ldap_servers as $sid => $ldap_server) {
        /** @var \Drupal\ldap_servers\Entity\Server $ldap_server */
        $enabled = ($ldap_server->get('status')) ? 'Enabled' : 'Disabled';
        $this->drupalAcctProvisionServerOptions[$sid] = $ldap_server->label() . ' (' . $ldap_server->get('address') . ') Status: ' . $enabled;
        $this->ldapEntryProvisionServerOptions[$sid] = $ldap_server->label() . ' (' . $ldap_server->get('address') . ') Status: ' . $enabled;
      }
    }
    $this->drupalAcctProvisionServerOptions['none'] = $this->t('None');
    $this->ldapEntryProvisionServerOptions['none'] = $this->t('None');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static (
      $container->get('config.factory'),
      $container->get('ldap.servers'),
      $container->get('cache.default'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ldap_user_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['ldap_user.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ldap_user.settings');

    if (count($this->drupalAcctProvisionServerOptions) == 0) {
      $url = Url::fromRoute('entity.ldap_server.collection');
      $edit_server_link = Link::fromTextAndUrl($this->t('@path', ['@path' => 'LDAP Servers']), $url)->toString();
      $message = $this->t('At least one LDAP server must configured and <em>enabled</em> before configuring LDAP user. Please go to @link to configure an LDAP server.',
        ['@link' => $edit_server_link]
      );
      $form['intro'] = [
        '#type' => 'item',
        '#markup' => $this->t('<h1>LDAP User Settings</h1>') . $message,
      ];
      return $form;
    }
    $form['#storage'] = [];

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
      '#required' => 1,
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
      '#required' => 1,
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
      '#required' => 1,
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
      $queries = QueryController::getAllEnabledQueries();
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
      '#required' => 1,
      '#default_value' => $config->get('ldapEntryProvisionServer') ? $config->get('ldapEntryProvisionServer') : 'none',
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

    $directions = [
      self::PROVISION_TO_DRUPAL,
      self::PROVISION_TO_LDAP,
    ];

    foreach ($directions as $direction) {

      if ($direction == self::PROVISION_TO_DRUPAL) {
        $parentFieldset = 'basic_to_drupal';
        $description = $this->t('Provisioning from LDAP to Drupal Mappings:');
      }
      else {
        $parentFieldset = 'basic_to_ldap';
        $description = $this->t('Provisioning from Drupal to LDAP Mappings:');
      }

      $mappingId = 'mappings__' . $direction;
      $tableId = $mappingId . '__table';

      $form[$parentFieldset][$mappingId] = [
        '#type' => 'fieldset',
        '#title' => $description,
        '#description' => $this->t('See also the <a href="@wiki_link">Drupal.org wiki page</a> for further information on using LDAP tokens.',
          ['@wiki_link' => 'http://drupal.org/node/1245736']),
      ];

      $form[$parentFieldset][$mappingId][$tableId] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Label'),
          $this->t('Machine name'),
          $this->t('Weight'),
          $this->t('Operations'),
        ],
        '#attributes' => ['class' => ['mappings-table']],
      ];

      $headers = $this->getServerMappingHeader($direction);
      $form[$parentFieldset][$mappingId][$tableId]['#header'] = $headers['header'];
      // Add in the second header as the first row.
      $form[$parentFieldset][$mappingId][$tableId]['second-header'] = [
        '#attributes' => ['class' => 'header'],
      ];
      // Second header uses the same format as header.
      foreach ($headers['second_header'] as $cell) {
        $form[$parentFieldset][$mappingId][$tableId]['second-header'][] = [
          '#title' => $cell['data'],
          '#type' => 'item',
        ];
        if (isset($cell['class'])) {
          $form[$parentFieldset][$mappingId][$tableId]['second-header']['#attributes'] = ['class' => [$cell['class']]];
        }
        if (isset($cell['rowspan'])) {
          $form[$parentFieldset][$mappingId][$tableId]['second-header']['#rowspan'] = $cell['rowspan'];
        }
        if (isset($cell['colspan'])) {
          $form[$parentFieldset][$mappingId][$tableId]['second-header']['#colspan'] = $cell['colspan'];
        }
      }

      $mappingsToAdd = $this->getServerMappingFields($direction);

      if ($mappingsToAdd) {
        $form[$parentFieldset][$mappingId][$tableId] += $mappingsToAdd;
      }

      $moreLdapInfo = '<h3>' . $this->t('Password Tokens') . '</h3><ul>';
      $moreLdapInfo .= '<li>' . $this->t('Pwd: Random -- Uses a random Drupal generated password') . '</li>';
      $moreLdapInfo .= '<li>' . $this->t('Pwd: User or Random -- Uses password supplied on user forms. If none available uses random password.') . '</li></ul>';
      $moreLdapInfo .= '<h3>' . $this->t('Password Concerns') . '</h3>';
      $moreLdapInfo .= '<ul>';
      $moreLdapInfo .= '<li>' . $this->t("Provisioning passwords to LDAP means passwords must meet the LDAP's password requirements.  Password Policy module can be used to add requirements.") . '</li>';
      $moreLdapInfo .= '<li>' . $this->t('Some LDAPs require a user to reset their password if it has been changed  by someone other that user.  Consider this when provisioning LDAP passwords.') . '</li>';
      $moreLdapInfo .= '</ul></p>';
      $moreLdapInfo .= '<h3>' . $this->t('Source Drupal User Tokens and Corresponding Target LDAP Tokens') . '</h3>';

      $moreLdapInfo .= $this->t('Examples in form: Source Drupal User token => Target LDAP Token (notes): <ul>
        <li>Source Drupal User token => Target LDAP Token</li>
        <li>cn=[property.name],ou=test,dc=ad,dc=mycollege,dc=edu => [dn] (example of token and constants)</li>
        <li>top => [objectclass:0] (example of constants mapped to multivalued attribute)</li>
        <li>person => [objectclass:1] (example of constants mapped to multivalued attribute)</li>
        <li>organizationalPerson => [objectclass:2] (example of constants mapped to multivalued attribute)</li>
        <li>user => [objectclass:3] (example of constants mapped to multivalued attribute)</li>
        <li>Drupal Provisioned LDAP Account => [description] (example of constant)</li>
        <li>[field.field_lname] => [sn]</li></ul>');

      // Add some password notes.
      if ($direction == self::PROVISION_TO_LDAP) {
        $form[$parentFieldset]['additional_ldap_hints'] = [
          '#type' => 'details',
          '#title' => $this->t('Additional information'),
          '#collapsible' => TRUE,
          '#collapsed' => TRUE,
          'directions' => [
            '#markup' => $moreLdapInfo,
          ],
        ];
      }
    }

    $inputs = [
      'acctCreation',
      'userConflictResolve',
      'drupalAcctProvisionTriggers',
      'mappings__' . self::PROVISION_TO_DRUPAL,
    ];
    foreach ($inputs as $inputName) {
      $form['basic_to_drupal'][$inputName]['#states']['invisible'] =
        [
          ':input[name=drupalAcctProvisionServer]' => ['value' => 'none'],
        ];
    }

    $form['basic_to_drupal']['orphanedAccounts']['#states']['invisible'] =
      [
        ':input[name=drupalAcctProvisionServer]' => ['value' => 'none'],
      ];

    $inputs = ['orphanedCheckQty', 'orphanedAccountCheckInterval'];
    foreach ($inputs as $inputName) {
      $form['basic_to_drupal']['orphanedAccounts'][$inputName]['#states']['invisible'] =
        [
          ':input[name=orphanedDrupalAcctBehavior]' => ['value' => 'ldap_user_orphan_do_not_check'],
        ];
    }

    $inputs = [
      'ldapEntryProvisionTriggers',
      'additional_ldap_hints',
      'mappings__' . self::PROVISION_TO_LDAP,
    ];
    foreach ($inputs as $inputName) {
      $form['basic_to_ldap'][$inputName]['#states']['invisible'] =
        [
          ':input[name=ldapEntryProvisionServer]' => ['value' => 'none'],
        ];
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Save',
    ];

    $this->notifyMissingSyncServerCombination($config);

    return $form;

  }

  /**
   * Check if the user starts with an an invalid configuration.
   *
   * @param \Drupal\Core\Config\Config $config
   *   Config object.
   */
  private function notifyMissingSyncServerCombination(Config $config) {

    $hasDrupalAcctProvServers = $config->get('drupalAcctProvisionServer');
    $hasDrupalAcctProvSettingsOptions = (count(array_filter($config->get('drupalAcctProvisionTriggers'))) > 0);
    if (!$config->get('drupalAcctProvisionServer') && $hasDrupalAcctProvSettingsOptions) {
      drupal_set_message($this->t('No servers are enabled to provide provisioning to Drupal, but Drupal account provisioning options are selected.'), 'warning');
    }
    elseif ($hasDrupalAcctProvServers && !$hasDrupalAcctProvSettingsOptions) {
      drupal_set_message($this->t('Servers are enabled to provide provisioning to Drupal, but no Drupal account provisioning options are selected. This will result in no syncing happening.'), 'warning');
    }

    $has_ldap_prov_servers = $config->get('ldapEntryProvisionServer');
    $has_ldap_prov_settings_options = (count(array_filter($config->get('ldapEntryProvisionTriggers'))) > 0);
    if (!$has_ldap_prov_servers && $has_ldap_prov_settings_options) {
      drupal_set_message($this->t('No servers are enabled to provide provisioning to LDAP, but LDAP entry options are selected.'), 'warning');
    }
    if ($has_ldap_prov_servers && !$has_ldap_prov_settings_options) {
      drupal_set_message($this->t('Servers are enabled to provide provisioning to LDAP, but no LDAP entry options are selected. This will result in no syncing happening.'), 'warning');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $drupalMapKey = 'mappings__' . self::PROVISION_TO_DRUPAL . '__table';
    $ldapMapKey = 'mappings__' . self::PROVISION_TO_LDAP . '__table';

    if ($values['drupalAcctProvisionServer'] != 'none') {
      foreach ($values[$drupalMapKey] as $key => $mapping) {
        if (isset($mapping['configurable_to_drupal']) && $mapping['configurable_to_drupal'] == 1) {

          // Check that the source is not empty for the selected field to sync
          // to Drupal.
          if ($mapping['user_attr'] !== '0') {
            if ($mapping['ldap_attr'] == NULL) {
              $formElement = $form['basic_to_drupal']['mappings__' . self::PROVISION_TO_DRUPAL][$drupalMapKey][$key];
              $form_state->setError($formElement, $this->t('Missing LDAP attribute'));
            }
          }
        }
      }
    }

    if ($values['ldapEntryProvisionServer'] != 'none') {
      foreach ($values[$ldapMapKey] as $key => $mapping) {
        if (isset($mapping['configurable_to_drupal']) && $mapping['configurable_to_drupal'] == 1) {
          // Check that the token is not empty if a user token is in use.
          if (isset($mapping['user_attr']) && $mapping['user_attr'] == 'user_tokens') {
            if (isset($mapping['user_tokens']) && empty(trim($mapping['user_tokens']))) {
              $formElement = $form['basic_to_ldap']['mappings__' . self::PROVISION_TO_LDAP][$ldapMapKey][$key];
              $form_state->setError($formElement, $this->t('Missing user token.'));
            }
          }

          // Check that a target attribute is set.
          if ($mapping['user_attr'] !== '0') {
            if ($mapping['ldap_attr'] == NULL) {
              $formElement = $form['basic_to_ldap']['mappings__' . self::PROVISION_TO_LDAP][$ldapMapKey][$key];
              $form_state->setError($formElement, $this->t('Missing LDAP attribute'));
            }
          }
        }
      }
    }

    $processedLdapSyncMappings = $this->syncMappingsFromForm($form_state->getValues(), self::PROVISION_TO_LDAP);
    $processedDrupalSyncMappings = $this->syncMappingsFromForm($form_state->getValues(), self::PROVISION_TO_DRUPAL);

    // Set error for entire table if [dn] is missing.
    if ($values['ldapEntryProvisionServer'] != 'none' && !isset($processedLdapSyncMappings['dn'])) {
      $form_state->setErrorByName($ldapMapKey,
        $this->t('Mapping rows exist for provisioning to LDAP, but no LDAP attribute is targeted for [dn]. One row must map to [dn]. This row will have a user token like cn=[property.name],ou=users,dc=ldap,dc=mycompany,dc=com')
      );
    }

    // Make sure only one attribute column is present.
    foreach ($processedLdapSyncMappings as $key => $mapping) {
      $maps = [];
      ConversionHelper::extractTokenAttributes($maps, $mapping['ldap_attr']);
      if (count(array_keys($maps)) > 1) {
        // TODO: Move this check out of processed mappings to be able to set the
        // error by field.
        $form_state->setErrorByName($ldapMapKey,
          $this->t('When provisioning to LDAP, LDAP attribute column must be singular token such as [cn]. %ldap_attr is not. Do not use compound tokens such as "[displayName] [sn]" or literals such as "physics".',
            ['%ldap_attr' => $mapping['ldap_attr']]
          )
        );
      }
    }

    // Notify the user if no actual synchronization event is active for a field.
    $this->checkEmptyEvents($processedLdapSyncMappings);
    $this->checkEmptyEvents($processedDrupalSyncMappings);

    if (!$this->checkPuidForOrphans($values['orphanedDrupalAcctBehavior'], $values['drupalAcctProvisionServer'])) {
      $form_state->setErrorByName('orphanedDrupalAcctBehavior', $this->t('You do not have a persistent user ID set in your server.'));
    }

  }

  /**
   * Check PUID for orphan configuration.
   *
   * Avoids the easy mistake of forgetting PUID and not being able to clean
   * up users which are no longer available due to missing data.
   *
   * @param string $orphanCheck
   *   Whether orphans are checked.
   * @param string $serverId
   *   Which server is used for provisioning.
   *
   * @return bool
   *   If there is an incosistent state.
   */
  private function checkPuidForOrphans($orphanCheck, $serverId) {
    if ($orphanCheck != 'ldap_user_orphan_do_not_check') {
      /** @var \Drupal\ldap_servers\Entity\Server $server */
      $server = $this->serverFactory->getServerById($serverId);
      if (empty($server->get('unique_persistent_attr'))) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Warn about fields without associated events.
   *
   * @param array $mappings
   *   Field mappings.
   */
  private function checkEmptyEvents(array $mappings) {
    foreach ($mappings as $mapping) {
      if (empty($mapping['prov_events'])) {
        drupal_set_message($this->t('No synchronization events checked in %item. This field will not be synchronized until some are checked.',
          ['%item' => $mapping['ldap_attr']]
        ), 'warning');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $drupalAcctProvisionServer = ($form_state->getValue('drupalAcctProvisionServer') == 'none') ? NULL : $form_state->getValue('drupalAcctProvisionServer');
    $ldapEntryProvisionServer = ($form_state->getValue('ldapEntryProvisionServer') == 'none') ? NULL : $form_state->getValue('ldapEntryProvisionServer');

    $processedSyncMappings[self::PROVISION_TO_DRUPAL] = $this->syncMappingsFromForm($form_state->getValues(), self::PROVISION_TO_DRUPAL);
    $processedSyncMappings[self::PROVISION_TO_LDAP] = $this->syncMappingsFromForm($form_state->getValues(), self::PROVISION_TO_LDAP);

    $this->config('ldap_user.settings')
      ->set('drupalAcctProvisionServer', $drupalAcctProvisionServer)
      ->set('ldapEntryProvisionServer', $ldapEntryProvisionServer)
      ->set('drupalAcctProvisionTriggers', $form_state->getValue('drupalAcctProvisionTriggers'))
      ->set('ldapEntryProvisionTriggers', $form_state->getValue('ldapEntryProvisionTriggers'))
      ->set('userUpdateCronQuery', $form_state->getValue('userUpdateCronQuery'))
      ->set('userUpdateCronInterval', $form_state->getValue('userUpdateCronInterval'))
      ->set('orphanedDrupalAcctBehavior', $form_state->getValue('orphanedDrupalAcctBehavior'))
      ->set('orphanedCheckQty', $form_state->getValue('orphanedCheckQty'))
      ->set('orphanedAccountCheckInterval', $form_state->getValue('orphanedAccountCheckInterval'))
      ->set('userConflictResolve', $form_state->getValue('userConflictResolve'))
      ->set('manualAccountConflict', $form_state->getValue('manualAccountConflict'))
      ->set('acctCreation', $form_state->getValue('acctCreation'))
      ->set('disableAdminPasswordField', $form_state->getValue('disableAdminPasswordField'))
      ->set('ldapUserSyncMappings', $processedSyncMappings)
      ->save();
    $form_state->getValues();

    SemaphoreStorage::flushAllValues();
    $this->cache->invalidate('ldap_user_sync_mapping');
    drupal_set_message($this->t('User synchronization configuration updated.'));
  }

  /**
   * Migrated from ldap_user.theme.inc .
   */
  private function getServerMappingHeader($direction) {

    if ($direction == self::PROVISION_TO_DRUPAL) {
      $header = [
        [
          'data' => $this->t('Source LDAP tokens'),
          'rowspan' => 1,
          'colspan' => 2,
        ],
        [
          'data' => $this->t('Target Drupal attribute'),
          'rowspan' => 1,
        ],
        [
          'data' => $this->t('Synchronization event'),
          'colspan' => count(LdapConfiguration::provisionsDrupalEvents()),
          'rowspan' => 1,
        ],

      ];

      $second_header = [
        [
          'data' => $this->t('Examples:<ul><li>[sn]</li><li>[mail:0]</li><li>[ou:last]</li><li>[sn], [givenName]</li></ul> Constants such as <em>17</em> or <em>imported</em> should not be enclosed in [].'),
          'header' => TRUE,
        ],
        [
          'data' => $this->t('Convert from binary'),
          'header' => TRUE,
        ],
        [
          'data' => '',
          'header' => TRUE,
        ],
      ];

      foreach (LdapConfiguration::provisionsDrupalEvents() as $col_name) {
        $second_header[] = [
          'data' => $col_name,
          'header' => TRUE,
          'class' => 'header-provisioning',
        ];
      }
    }
    // To ldap.
    else {
      $header = [
        [
          'data' => $this->t('Source Drupal user attribute'),
          'rowspan' => 1,
          'colspan' => 3,
        ],
        [
          'data' => $this->t('Target LDAP token'),
          'rowspan' => 1,
        ],
        [
          'data' => $this->t('Synchronization event'),
          'colspan' => count($this->provisionsLdapEvents()),
          'rowspan' => 1,
        ],
      ];

      $second_header = [
        [
          'data' => $this->t('Note: Select <em>user tokens</em> to use token field.'),
          'header' => TRUE,
        ],
        [
          'data' => $this->t('Source Drupal user tokens such as: <ul><li>[property.name]</li><li>[field.field_fname]</li><li>[field.field_lname]</li></ul> Constants such as <em>from_drupal</em> or <em>18</em> should not be enclosed in [].'),
          'header' => TRUE,
        ],
        [
          'data' => $this->t('Convert From binary'),
          'header' => TRUE,
        ],
        [
          'data' => $this->t('Use singular token format such as: <ul><li>[sn]</li><li>[givenName]</li></ul>'),
          'header' => TRUE,
        ],
      ];
      foreach ($this->provisionsLdapEvents() as $col_name) {
        $second_header[] = [
          'data' => $col_name,
          'header' => TRUE,
          'class' => 'header-provisioning',
        ];
      }
    }
    return ['header' => $header, 'second_header' => $second_header];
  }

  /**
   * Return the server mappings for the fields.
   *
   * @param string $direction
   *   The provisioning direction.
   *
   * @return array|bool
   *   Returns the mappings.
   */
  private function getServerMappingFields($direction) {
    if ($direction == self::PROVISION_TO_NONE) {
      return FALSE;
    }

    $rows = [];

    $text = ($direction == self::PROVISION_TO_DRUPAL) ? 'target' : 'source';
    $userAttributeOptions = ['0' => $this->t('Select') . ' ' . $text];
    $syncMappingsHelper = new SyncMappingHelper();
    $syncMappings = $syncMappingsHelper->getAllSyncMappings();
    if (!empty($syncMappings[$direction])) {
      foreach ($syncMappings[$direction] as $target_id => $mapping) {

        if (!isset($mapping['name']) || isset($mapping['exclude_from_mapping_ui']) && $mapping['exclude_from_mapping_ui']) {
          continue;
        }
        if (
          (isset($mapping['configurable_to_drupal']) && $mapping['configurable_to_drupal'] && $direction == self::PROVISION_TO_DRUPAL)
          ||
          (isset($mapping['configurable_to_ldap']) && $mapping['configurable_to_ldap'] && $direction == self::PROVISION_TO_LDAP)
        ) {
          $userAttributeOptions[$target_id] = $mapping['name'];
        }
      }
    }

    if ($direction != self::PROVISION_TO_DRUPAL) {
      $userAttributeOptions['user_tokens'] = '-- user tokens --';
    }

    $row = 0;

    // 1. non configurable mapping rows.
    foreach ($syncMappings[$direction] as $target_id => $mapping) {
      $rowId = $this->sanitizeMachineName($target_id);
      if (isset($mapping['exclude_from_mapping_ui']) && $mapping['exclude_from_mapping_ui']) {
        continue;
      }
      // Is configurable by ldap_user module (not direction to ldap_user)
      if (!$this->isMappingConfigurable($mapping, 'ldap_user') && ($mapping['direction'] == $direction || $mapping['direction'] == self::PROVISION_TO_ALL)) {
        $rows[$rowId] = $this->getSyncFormRow('nonconfigurable', $direction, $mapping, $userAttributeOptions, $rowId);
        $row++;
      }
    }
    $config = $this->config('ldap_user.settings');

    // 2. existing configurable mappings rows.
    if (!empty($config->get('ldapUserSyncMappings')[$direction])) {
      // Key could be LDAP attribute name or user attribute name.
      foreach ($config->get('ldapUserSyncMappings')[$direction] as $mapping) {
        if ($direction == self::PROVISION_TO_DRUPAL) {
          $mapping_key = $mapping['user_attr'];
        }
        else {
          $mapping_key = $mapping['ldap_attr'];
        }
        if (isset($mapping['enabled']) && $mapping['enabled'] && $this->isMappingConfigurable($syncMappings[$direction][$mapping_key], 'ldap_user')) {
          $rowId = 'row-' . $row;
          $rows[$rowId] = $this->getSyncFormRow('update', $direction, $mapping, $userAttributeOptions, $rowId);
          $row++;
        }
      }
    }

    // 3. leave 4 rows for adding more mappings.
    for ($i = 0; $i < 4; $i++) {
      $rowId = 'custom-' . $i;
      $rows[$rowId] = $this->getSyncFormRow('add', $direction, [], $userAttributeOptions, $rowId);
      $row++;
    }

    return $rows;
  }

  /**
   * Get mapping form row to LDAP user provisioning mapping admin form table.
   *
   * @param string $action
   *   Action is either add, update, or nonconfigurable.
   * @param string $direction
   *   LdapUserAttributesInterface::PROVISION_TO_DRUPAL or
   *   LdapUserAttributesInterface::PROVISION_TO_LDAP.
   * @param array $mapping
   *   Is current setting for updates or nonconfigurable items.
   * @param array $userAttributeOptions
   *   Attributes of Drupal user target options.
   * @param int $rowId
   *   Is current row in table.
   *
   * @return array
   *   A single row
   */
  private function getSyncFormRow($action, $direction, array $mapping, array $userAttributeOptions, $rowId) {

    $result = [];
    $idPrefix = 'mappings__' . $direction . '__table';
    $userAttributeInputeId = $idPrefix . "[$rowId][user_attr]";

    if ($action == 'nonconfigurable') {
      $ldapAttribute = [
        '#type' => 'item',
        '#default_value' => isset($mapping['ldap_attr']) ? $mapping['ldap_attr'] : '',
        '#markup' => isset($mapping['source']) ? $mapping['source'] : '?',
        '#attributes' => ['class' => ['source']],
      ];
    }
    else {
      $ldapAttribute = [
        '#type' => 'textfield',
        '#title' => 'LDAP attribute',
        '#title_display' => 'invisible',
        '#default_value' => isset($mapping['ldap_attr']) ? $mapping['ldap_attr'] : '',
        '#size' => 20,
        '#maxlength' => 255,
        '#attributes' => ['class' => ['ldap-attr']],
      ];
      // Change the visibility rules if provisioning to LDAP.
      if ($direction == self::PROVISION_TO_LDAP) {
        $userTokens = [
          '#type' => 'textfield',
          '#title' => 'User tokens',
          '#title_display' => 'invisible',
          '#default_value' => isset($mapping['user_tokens']) ? $mapping['user_tokens'] : '',
          '#size' => 20,
          '#maxlength' => 255,
          '#disabled' => ($action == 'nonconfigurable'),
          '#attributes' => ['class' => ['tokens']],
        ];

        $userTokens['#states'] = [
          'visible' => [
            'select[name="' . $userAttributeInputeId . '"]' => ['value' => 'user_tokens'],
          ],
        ];
      }
    }

    $convert = [
      '#type' => 'checkbox',
      '#title' => 'Convert from binary',
      '#title_display' => 'invisible',
      '#default_value' => isset($mapping['convert']) ? $mapping['convert'] : '',
      '#disabled' => ($action == 'nonconfigurable'),
      '#attributes' => ['class' => ['convert']],
    ];

    if ($action == 'nonconfigurable') {
      $userAttribute = [
        '#type' => 'item',
        '#markup' => isset($mapping['name']) ? $mapping['name'] : '?',
      ];
    }
    else {
      $userAttribute = [
        '#type' => 'select',
        '#title' => 'User attribute',
        '#title_display' => 'invisible',
        '#default_value' => isset($mapping['user_attr']) ? $mapping['user_attr'] : '',
        '#options' => $userAttributeOptions,
      ];
    }

    // Get the order of the columns correctly.
    if ($direction == self::PROVISION_TO_LDAP) {
      $result['user_attr'] = $userAttribute;
      $result['user_tokens'] = $userTokens;
      $result['convert'] = $convert;
      $result['ldap_attr'] = $ldapAttribute;
    }
    else {
      $result['ldap_attr'] = $ldapAttribute;
      $result['convert'] = $convert;
      $result['user_attr'] = $userAttribute;
    }

    $result['#storage']['sync_mapping_fields'][$direction] = [
      'action' => $action,
      'direction' => $direction,
    ];
    // FIXME: Add table selection / ordering back:
    // $col and $row used to be paremeters to $result[$prov_event]. ID possible
    // not need needed anymore. Row used to be a parameter to this function.
    // $col = ($direction == LdapUserAttributesInterface::PROVISION_TO_LDAP) ?
    // 5 : 4;.
    if (($direction == self::PROVISION_TO_DRUPAL)) {
      $syncEvents = LdapConfiguration::provisionsDrupalEvents();
    }
    else {
      $syncEvents = $this->provisionsLdapEvents();
    }

    foreach ($syncEvents as $prov_event => $prov_event_name) {
      // @FIXME: Leftover code.
      // See above.
      // $col++;
      // $id = $id_prefix . implode('__', array('sm', $prov_event, $row));.
      $result[$prov_event] = [
        '#type' => 'checkbox',
        '#title' => $prov_event,
        '#title_display' => 'invisible',
        '#default_value' => isset($mapping['prov_events']) ? (int) (in_array($prov_event, $mapping['prov_events'])) : '',
        '#disabled' => (!$this->provisionEventConfigurable($prov_event, $mapping) || ($action == 'nonconfigurable')),
        '#attributes' => ['class' => ['sync-method']],
      ];
    }

    // This one causes the extra column.
    $result['configurable_to_drupal'] = [
      '#type' => 'hidden',
      '#default_value' => ($action != 'nonconfigurable' ? 1 : 0),
      '#class' => '',
    ];

    return $result;
  }

  /**
   * Is a mapping configurable by a given module?
   *
   * @param array|null $mapping
   *   As mapping configuration for field, attribute, property, etc.
   * @param string $module
   *   Machine name such as ldap_user.
   *
   * @return bool
   *   Whether mapping is configurable.
   */
  private function isMappingConfigurable($mapping = [], $module = 'ldap_user') {
    $configurable = (
      (
        (!isset($mapping['configurable_to_drupal']) && !isset($mapping['configurable_to_ldap'])) ||
        (isset($mapping['configurable_to_drupal']) && $mapping['configurable_to_drupal']) ||
        (isset($mapping['configurable_to_ldap']) && $mapping['configurable_to_ldap'])
      )
      &&
      (
        !isset($mapping['config_module']) ||
        (isset($mapping['config_module']) && $mapping['config_module'] == $module)
      )
    );
    return $configurable;
  }

  /**
   * Is a particular sync method viable for a given mapping?
   *
   * That is, can it be enabled in the UI by admins?
   *
   * @param int $prov_event
   *   Event to check.
   * @param array $mapping
   *   Array of mapping configuration.
   *
   * @return bool
   *   Whether configurable or not.
   */
  private function provisionEventConfigurable($prov_event, array $mapping = NULL) {

    $configurable = FALSE;

    if ($mapping) {
      if ($prov_event == self::EVENT_CREATE_LDAP_ENTRY || $prov_event == self::EVENT_SYNC_TO_LDAP_ENTRY) {
        $configurable = (boolean) (!isset($mapping['configurable_to_ldap']) || $mapping['configurable_to_ldap']);
      }
      elseif ($prov_event == self::EVENT_CREATE_DRUPAL_USER || $prov_event == self::EVENT_SYNC_TO_DRUPAL_USER) {
        $configurable = (boolean) (!isset($mapping['configurable_to_drupal']) || $mapping['configurable_to_drupal']);
      }
    }
    else {
      $configurable = TRUE;
    }

    return $configurable;
  }

  /**
   * Returns a config compatible machine name.
   *
   * @param string $string
   *   Field name to process.
   *
   * @return string
   *   Returns safe string.
   */
  private function sanitizeMachineName($string) {
    // Replace dots
    // Replace square brackets.
    return str_replace(['.', '[', ']'], ['-', '', ''], $string);
  }

  /**
   * Extract sync mappings array from mapping table in admin form.
   *
   * @param array $values
   *   As $form_state['values'] from Drupal FormAPI.
   * @param string $direction
   *   Direction to sync to.
   *
   * @return array
   *   Returns the relevant mappings.
   */
  private function syncMappingsFromForm(array $values, $direction) {
    $mappings = [];
    foreach ($values as $field_name => $value) {

      $parts = explode('__', $field_name);
      if ($parts[0] != 'mappings' || !isset($parts[1]) || $parts[1] != $direction) {
        continue;
      }

      // These are our rows.
      foreach ($value as $row_descriptor => $columns) {
        if ($row_descriptor == 'second-header') {
          continue;
        }

        $key = ($direction == self::PROVISION_TO_DRUPAL) ? $this->sanitizeMachineName($columns['user_attr']) : $this->sanitizeMachineName($columns['ldap_attr']);
        // Only save if its configurable and has an LDAP and Drupal attributes.
        // The others are optional.
        if ($columns['configurable_to_drupal'] && $columns['ldap_attr'] && $columns['user_attr']) {
          $mappings[$key] = [
            'ldap_attr' => trim($columns['ldap_attr']),
            'user_attr' => trim($columns['user_attr']),
            'convert' => $columns['convert'],
            'direction' => $direction,
            'user_tokens' => isset($columns['user_tokens']) ? $columns['user_tokens'] : '',
            'config_module' => 'ldap_user',
            'prov_module' => 'ldap_user',
            'enabled' => 1,
          ];

          $syncEvents = ($direction == self::PROVISION_TO_DRUPAL) ? LdapConfiguration::provisionsDrupalEvents() : $this->provisionsLdapEvents();
          foreach ($syncEvents as $prov_event => $discard) {
            if (isset($columns[$prov_event]) && $columns[$prov_event]) {
              $mappings[$key]['prov_events'][] = $prov_event;
            }
          }
        }
      }
    }
    return $mappings;
  }

  /**
   * Returns the two provisioning events.
   *
   * @return array
   *   Create and Sync event in display form.
   */
  private function provisionsLdapEvents() {
    return [
      self::EVENT_CREATE_LDAP_ENTRY => $this->t('On LDAP Entry Creation'),
      self::EVENT_SYNC_TO_LDAP_ENTRY => $this->t('On Sync to LDAP Entry'),
    ];
  }

}
