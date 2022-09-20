<?php

declare(strict_types = 1);

namespace Drupal\ldap_servers\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ldap_servers\Entity\Server;

/**
 * Server form.
 *
 * @package Drupal\ldap_servers\Form
 */
class ServerForm extends EntityForm {

  /**
   * The server entity.
   *
   * @var \Drupal\ldap_servers\Entity\Server
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\ldap_servers\Entity\Server $server */
    $server = $this->entity;

    $form['server'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Server'),
      '#open' => TRUE,
    ];

    $form['server']['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#maxlength' => 255,
      '#default_value' => $server->label(),
      '#description' => $this->t('Choose a unique <strong><em>name</em></strong> for this server configuration.'),
      '#required' => TRUE,
    ];

    $form['server']['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $server->id(),
      '#machine_name' => [
        'exists' => '\Drupal\ldap_servers\Entity\Server::load',
      ],
      '#disabled' => !$server->isNew(),
    ];

    /* You will need additional form elements for your custom properties. */
    $form['server']['status'] = [
      '#title' => $this->t('Enabled'),
      '#type' => 'checkbox',
      '#default_value' => $server->get('status'),
      '#description' => $this->t('Disable in order to keep configuration without having it active.'),
    ];

    $form['server']['type'] = [
      '#title' => $this->t('LDAP Server type'),
      '#type' => 'select',
      '#options' => [
        'default' => $this->t('Default LDAP'),
        'ad' => $this->t('Active Directory'),
        'novell_edir' => $this->t('Novell'),
        'openldap' => $this->t('Open LDAP'),
        'opendir' => $this->t('Apple Open Directory'),
      ],
      '#default_value' => $server->get('type'),
      '#description' => $this->t("At the moment this only changes whether unicodePwd should be used for AD."),
    ];

    $form['server']['address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Server address'),
      '#maxlength' => 255,
      '#default_value' => $server->get('address'),
      '#description' => $this->t('The domain name or IP address of your LDAP Server such as "ad.unm.edu".'),
      '#required' => TRUE,
    ];

    $form['server']['port'] = [
      '#type' => 'number',
      '#title' => $this->t('Server port'),
      '#min' => 1,
      '#max' => 65535,
      '#default_value' => $server->get('port') ?: 389,
      '#description' => $this->t('Usually 389 for unencrypted and STARTTLS, 636 for SSL.'),
      '#required' => TRUE,
    ];

    $form['server']['timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Timeout'),
      '#min' => -1,
      '#max' => 999,
      '#default_value' => $server->get('timeout') ?: 10,
      '#description' => $this->t('How long to wait for a response from the LDAP server in seconds (network or query).'),
      '#required' => TRUE,
    ];

    $form['server']['encryption'] = [
      '#title' => $this->t('Encryption'),
      '#type' => 'select',
      '#options' => [
        'none' => $this->t('Unencrypted'),
        'ssl' => $this->t('SSL (i.e. the ldaps:// protocol)'),
        'tls' => $this->t('STARTTLS'),
      ],
      '#default_value' => $server->get('encryption') ?: 'none',
    ];

    $form['bind'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Binding'),
    ];

    $form['bind']['bind_method'] = [
      '#default_value' => $server->get('bind_method') ?: 'service_account',
      '#type' => 'radios',
      '#title' => $this->t('Binding Method for Searches'),
      '#options' => [
        'service_account' => $this->t('Service Account Bind: Use credentials in the Service Account field below to bind to LDAP <br> <div class="description">This option is usually a best practice.<br> This is also required for provisioning LDAP accounts and groups.<br> For security reasons, this pair should belong to an  LDAP account with stripped down permissions.</div>'),
        'user' => $this->t('Bind with Users Credentials: Use user\'s entered credentials to bind to LDAP<br> <div class="description">This is only useful for modules that execute during user logon such as LDAP Authentication and LDAP Authorization.<br> This option is not a best practice in most cases.<br> The user\'s dn must be of the form "cn=[username],[base dn]" for this option to work.</div>'),
        'anon_user' => $this->t('Anonymous Bind for search, then Bind with Users Credentials<br> <div class="description">Searches for user dn then uses user\'s entered credentials to bind to LDAP.<br/> This is only useful for modules that work during user logon such as LDAP Authentication and LDAP Authorization. <br>
        The user\'s dn must be discovered by an anonymous search for this option to work.</div>'),
        'anon' => $this->t('Anonymous Bind: Use no credentials to bind to LDAP server<br/> <div class="description">This option will not work on most LDAPS connections.</div>'),
      ],
    ];

    $form['bind']['binddn'] = [
      '#default_value' => $server->get('binddn'),
      '#type' => 'textfield',
      '#title' => $this->t('DN for non-anonymous search'),
      '#size' => 80,
      '#maxlength' => 512,
      '#states' => [
        'visible' => [
          ':input[name=bind_method]' => ['value' => 'service_account'],
        ],
        'required' => [
          ':input[name=bind_method]' => ['value' => 'service_account'],
        ],
      ],
    ];

    $form['bind']['bindpw'] = [
      '#type' => 'password',
      '#title' => $this->t('Password for non-anonymous search'),
      '#size' => 80,
      '#states' => [
        'visible' => [
          ':input[name=bind_method]' => ['value' => 'service_account'],
        ],
        'required' => [
          ':input[name=bind_method]' => ['value' => 'service_account'],
        ],
      ],
    ];

    if ($server->get('bindpw')) {
      $form['bind']['bindpw']['#attributes'] = ['value' => '****'];
    }

    $form['users'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Users'),
    ];

    // @todo Change to "add another" pattern, to avoid linebreak handling.
    $form['users']['basedn'] = [
      '#default_value' => $server->get('basedn') ? implode("\r\n", $server->get('basedn')) : '',
      '#type' => 'textarea',
      '#cols' => 50,
      '#rows' => 6,
      '#title' => $this->t('Base DNs for LDAP users, groups, and other entries.'),
      '#description' => '<div>' . $this->t('DNs that have  relevant entries, e.g. <code>ou=campus accounts,dc=ad,dc=uiuc,dc=edu</code>.<br> Keep in mind that every additional basedn likely doubles the number of queries. <br> Place the more heavily used one first and consider using one higher base DN rather than 2 or more lower base DNs.<br> Enter one per line in case if you need more than one.') . '</div>',
    ];

    $form['users']['user_attr'] = [
      '#default_value' => $server->getAuthenticationNameAttribute(),
      '#type' => 'textfield',
      '#size' => 30,
      '#title' => $this->t('Authentication name attribute'),
      '#description' => $this->t("The attribute that holds the user's login name. (eg. <code>cn</code> for eDir or <code>sAMAccountName</code> for Active Directory)."),
    ];

    $form['users']['account_name_attr'] = [
      '#default_value' => $server->getAccountNameAttribute(),
      '#type' => 'textfield',
      '#size' => 30,
      '#title' => $this->t('Account name attribute'),
      '#description' => $this->t('The attribute that holds the unique account name. Defaults to the same as the authentication name attribute.'),
    ];

    $form['users']['mail_attr'] = [
      '#default_value' => $server->get('mail_attr'),
      '#type' => 'textfield',
      '#size' => 30,
      '#title' => $this->t('Email attribute'),
      '#description' => $this->t("The attribute that holds the user's email address. (eg. <code>mail</code>). Leave empty if no such attribute exists"),
    ];

    $form['users']['mail_template'] = [
      '#default_value' => $server->getMailTemplate(),
      '#type' => 'textfield',
      '#size' => 30,
      '#title' => $this->t('Email template'),
      '#description' => $this->t("If no attribute contains the user's email address, but it can be derived from other attributes, enter an email \"template\" here.<br> Templates should have the user's attribute name in form such as [cn], [uin], etc. such as <code>[cn]@mycompany.com</code>.<br> See also the <a href=\"http://drupal.org/node/997082\">drupal.org documentation on LDAP tokens</a>."),
    ];

    $form['users']['picture_attr'] = [
      '#default_value' => $server->getPictureAttribute(),
      '#type' => 'textfield',
      '#size' => 30,
      '#title' => $this->t('Thumbnail attribute'),
      '#description' => $this->t("The attribute that holds the user's thumnail image. (e.g. <code>thumbnailPhoto</code>). Leave empty if no such attribute exists"),
    ];

    $form['users']['unique_persistent_attr'] = [
      '#default_value' => $server->getUniquePersistentAttribute(),
      '#type' => 'textfield',
      '#size' => 30,
      '#title' => $this->t('Persistent and Unique User ID Attribute'),
      '#description' => $this->t("Login attributes are not always persistent (e.g. change in last name or email).<br> Most setups should set this attribute to avoid creation of duplicate accounts or other issues.<br> In cases where DN does not change, enter 'dn' here. If no such attribute exists, leave this blank."),
    ];

    $form['users']['unique_persistent_attr_binary'] = [
      '#default_value' => $server->get('unique_persistent_attr_binary'),
      '#type' => 'checkbox',
      '#title' => $this->t('Does the <em>Persistent and Unique User ID Attribute</em> hold a binary value?'),
      '#description' => $this->t("You need to set this if you are using a binary attribute such as objectSid in ActiveDirectory for the PUID.<br> If you don't want this consider switching to another attribute, such as samaccountname."),
    ];

    $form['users']['user_dn_expression'] = [
      '#default_value' => $server->getUserDnExpression(),
      '#type' => 'textfield',
      '#size' => 80,
      '#title' => $this->t('Expression for user DN. Required when "Bind with Users Credentials" method selected.'),
      '#description' => $this->t('%username and %basedn are valid tokens in the expression.<br> Typically it will be: <code>cn=%username,%basedn</code> which might evaluate to <code>cn=jdoe,ou=campus accounts,dc=ad,dc=mycampus,dc=edu</code>'),
    ];

    $form['users']['testing_drupal_username'] = [
      '#default_value' => $server->get('testing_drupal_username'),
      '#type' => 'textfield',
      '#size' => 30,
      '#title' => $this->t('Testing Drupal Username'),
      '#description' => $this->t("This is optional and used for testing this server's configuration against an actual username<br>The user need not exist in Drupal and testing will not affect the user's LDAP or Drupal Account."),
    ];

    $form['users']['testing_drupal_user_dn'] = [
      '#default_value' => $server->getTestingDrupalUserDn(),
      '#type' => 'textfield',
      '#size' => 120,
      '#title' => $this->t('DN of testing username'),
      '#description' => $this->t("This is optional and used for testing this server's configuration against an actual username, e.g. cn=hpotter,ou=people,dc=hogwarts,dc=edu.<br> The user need not exist in Drupal and testing will not affect the user's LDAP or Drupal Account."),
    ];

    $form['groups'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Groups'),
    ];

    $form['groups']['grp_unused'] = [
      '#default_value' => $server->get('grp_unused'),
      '#type' => 'checkbox',
      '#title' => $this->t('Groups are not relevant to this Drupal site. This is generally true if LDAP Groups and LDAP Authorization are not in use.'),
      '#disabled' => FALSE,
    ];

    $form['groups']['grp_nested'] = [
      '#default_value' => $server->get('grp_nested'),
      '#type' => 'checkbox',
      '#title' => $this->t('Nested groups are used in my LDAP'),
      '#disabled' => FALSE,
      '#description' => $this->t('If a user is a member of group A and group A is a member of group B, user should be considered to be in group A and B.<br> If your LDAP has nested groups, but you want to ignore nesting, leave this unchecked.'),
      '#states' => [
        'visible' => [
          ':input[name=grp_unused]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['groups']['grp_memb_attr'] = [
      '#default_value' => $server->get('grp_memb_attr'),
      '#type' => 'textfield',
      '#size' => 30,
      '#title' => $this->t("LDAP Group Entry Attribute Holding User's DN, CN, etc."),
      '#description' => $this->t('e.g uniquemember, memberUid'),
      '#states' => [
        'visible' => [
          ':input[name=grp_unused]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['groups']['derive_group'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Derive from group'),
      '#states' => [
        'visible' => [
          ':input[name=grp_unused]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['groups']['derive_group']['grp_object_cat'] = [
      '#default_value' => $server->get('grp_object_cat'),
      '#type' => 'textfield',
      '#size' => 30,
      '#title' => $this->t('Name of Group Object Class'),
      '#description' => $this->t('e.g. groupOfNames, groupOfUniqueNames, group.'),
      '#states' => [
        'visible' => [
          ':input[name=grp_unused]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['groups']['derive_group']['grp_memb_attr_match_user_attr'] = [
      '#default_value' => $server->get('grp_memb_attr_match_user_attr'),
      '#type' => 'textfield',
      '#size' => 30,
      '#title' => $this->t('User attribute held in "LDAP Group Entry Attribute Holding..."'),
      '#description' => $this->t('This is almost always "dn" (which technically isn\'t an attribute). Sometimes its "cn".'),
      '#states' => [
        'visible' => [
          ':input[name=grp_unused]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['groups']['attribute'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Derive from user attribute'),
      '#states' => [
        'visible' => [
          ':input[name=grp_unused]' => ['checked' => FALSE],
        ],
      ],
    ];
    $form['groups']['attribute']['grp_user_memb_attr_exists'] = [
      '#default_value' => $server->get('grp_user_memb_attr_exists'),
      '#type' => 'checkbox',
      '#title' => $this->t('A user LDAP attribute such as <code>memberOf</code> exists that contains a list of their groups.'),
      '#description' => $this->t('Active Directory and openLdap with memberOf overlay fit this model. <br> Using this ignores "derive from group"'),
      '#disabled' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name=grp_unused]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['groups']['attribute']['grp_user_memb_attr'] = [
      '#default_value' => $server->getGroupUserMembershipAttribute(),
      '#type' => 'textfield',
      '#size' => 30,
      '#title' => $this->t('Attribute in User Entry Containing Groups'),
      '#description' => $this->t('Example: memberOf'),
      '#states' => [
        'enabled' => [
          ':input[name=grp_user_memb_attr_exists]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[name=grp_unused]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['groups']['deriveDN'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Derive from DN'),
      '#states' => [
        'visible' => [
          ':input[name=grp_unused]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['groups']['deriveDN']['grp_derive_from_dn'] = [
      '#default_value' => $server->isGroupDerivedFromDn(),
      '#type' => 'checkbox',
      '#title' => $this->t("Groups are derived from user's LDAP entry DN."),
      '#description' => $this->t('This group definition has very limited functionality and most modules will not take this into account.  LDAP Authorization will.'),
      '#disabled' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name=grp_unused]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['groups']['deriveDN']['grp_derive_from_dn_attr'] = [
      '#default_value' => $server->getDerivedGroupFromDnAttribute(),
      '#type' => 'textfield',
      '#size' => 30,
      '#title' => $this->t("Attribute of the user's LDAP entry DN which contains the group"),
      '#description' => $this->t('e.g. ou'),
      '#states' => [
        'enabled' => [
          ':input[name=grp_derive_from_dn]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[name=grp_unused]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['groups']['grp_test_grp_dn'] = [
      '#default_value' => $server->get('grp_test_grp_dn'),
      '#type' => 'textfield',
      '#size' => 120,
      '#title' => $this->t('Testing LDAP Group DN'),
      '#description' => $this->t('This is optional and can be useful for debugging and validating forms.'),
      '#states' => [
        'visible' => [
          ':input[name=grp_unused]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['groups']['grp_test_grp_dn_writeable'] = [
      '#default_value' => $server->get('grp_test_grp_dn_writeable'),
      '#type' => 'textfield',
      '#size' => 120,
      '#title' => $this->t('Testing LDAP Group DN that is writable.'),
      '#description' => $this->t('<strong>WARNING:</strong> the test script for the server will create, delete, and add members to this group! <br> This is optional and can be useful for debugging and validating forms.'),
      '#placeholder' => $this->t('Careful!'),
      '#states' => [
        'visible' => [
          ':input[name=grp_unused]' => ['checked' => FALSE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): void {
    if ($form_state->getValue('bind_method') !== 'service_account') {
      $this->entity->set('binddn', NULL);
      $this->entity->set('bindpw', NULL);
    }
    else {
      if ($form_state->getValue('bindpw') !== '****') {
        $this->entity->set('bindpw', $form_state->getValue('bindpw'));
      }
      else {
        // Fetch existing password since the placeholder is present.
        $oldConfiguration = Server::load($this->entity->id());
        if ($oldConfiguration && $oldConfiguration->get('bindpw')) {
          $this->entity->set('bindpw', $oldConfiguration->get('bindpw'));
        }
      }
    }
    $this->entity->set('basedn', preg_split('/\r\n|\r|\n/', $form_state->getValue('basedn')));

    $fields = [
      'user_attr',
      'account_name_attr',
      'mail_attr',
      'mail_template',
      'picture_attr',
      'unique_persistent_attr',
      'user_dn_expression',
      'grp_memb_attr',
      'grp_object_cat',
      'grp_memb_attr_match_user_attr',
      'grp_user_memb_attr',
      'grp_derive_from_dn_attr',
    ];

    foreach ($fields as $field) {
      // We lowercase all fields to match Entry attributes being lowercased in
      // \Drupal\ldap_servers\LdapBaseManager::sanitizeUserDataResponse().
      $this->entity->set($field, mb_strtolower(trim($this->entity->get($field))));
    }

    // Remove prefix to avoid user error.
    $this->entity->set('address', preg_replace('#^ldaps?://#', '', $form_state->getValue('address')));

    $status = $this->entity->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addMessage($this->t('Created the %label Server.', [
        '%label' => $this->entity->label(),
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t('Saved the %label Server.', [
        '%label' => $this->entity->label(),
      ]));
    }
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
  }

}
