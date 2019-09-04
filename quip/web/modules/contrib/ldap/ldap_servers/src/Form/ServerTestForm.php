<?php

namespace Drupal\ldap_servers\Form;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Render\Renderer;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers\Helper\CredentialsStorage;
use Drupal\ldap_servers\Processor\TokenProcessor;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Use Drupal\Core\Form\FormBase;.
 */
final class ServerTestForm extends EntityForm {

  /**
   * The main server to work with.
   *
   * @var \Drupal\ldap_servers\Entity\Server
   */
  protected $ldapServer;

  /**
   * Results table.
   *
   * @var array
   */
  protected $resultsTables = [];

  /**
   * Flag for any exception in form.
   *
   * @var bool
   */
  protected $exception = FALSE;

  protected $config;
  protected $moduleHandler;
  protected $tokenProcessor;
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ldap_servers_test_form';
  }

  /**
   * Class constructor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandler $module_handler, TokenProcessor $token_processor, Renderer $renderer) {
    $this->config = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->tokenProcessor = $token_processor;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('ldap.token_processor'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $ldap_server = NULL) {
    if ($ldap_server) {
      $this->ldapServer = $ldap_server;
    }

    $form['#title'] = $this->t('Test LDAP Server Configuration: @server', ['@server' => $this->ldapServer->label()]);

    $form['#prefix'] = $this->t('<h3>Send test queries</h3><p>Enter identifiers here to query LDAP directly based on your server configuration. The only data this function will modify is the test LDAP group, which will be deleted and added</p>');

    if (!$this->moduleHandler->moduleExists('ldap_user')) {
      $form['error'] = [
        '#markup' => '<h3>' . $this->t('This form requires ldap_user to function correctly, please enable it.') . '</h3>',
      ];
      return $form;
    }

    $properties = [];

    $settings = [
      '#theme' => 'item_list',
      '#items' => $properties,
      '#list_type' => 'ul',
    ];
    $form['server_variables'] = [
      '#markup' => $this->renderer->render($settings),
    ];

    $form['id'] = [
      '#type' => 'hidden',
      '#title' => $this->t('Machine name for this server'),
      '#default_value' => $this->ldapServer->id(),
    ];

    if ($this->ldapServer->get('bind_method') == 'anon_user' || $this->ldapServer->get('bind_method') == 'user') {
      $userCredentialsRequired = TRUE;
    }
    else {
      $userCredentialsRequired = FALSE;
    }

    $form['testing_drupal_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Authentication name'),
      '#default_value' => $this->ldapServer->get('testing_drupal_username'),
      '#size' => 30,
      '#maxlength' => 255,
      '#required' => $userCredentialsRequired,
      '#description' => $this->t("This is usually the equivalent of the Drupal username. The user need not exist in Drupal and testing will not affect the user's LDAP or Drupal account."),
    ];

    if ($userCredentialsRequired) {
      $form['testing_drupal_userpw'] = [
        '#type' => 'password',
        '#title' => $this->t('Testing Drupal User Password'),
        '#size' => 30,
        '#maxlength' => 255,
        '#required' => TRUE,
        '#description' => $this->t('Credentials required for testing with user binding.'),
      ];
    }

    $form['testing_drupal_user_dn'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Testing Drupal DN'),
      '#default_value' => $this->ldapServer->get('testing_drupal_user_dn'),
      // TODO: Add this field back in. The logic for it is missing completely.
      '#access' => FALSE,
      '#size' => 120,
      '#maxlength' => 255,
      '#description' => $this->t("The user is not required to exist in Drupal and testing will not affect the user's LDAP or Drupal Account."),
    ];

    $form['grp_test_grp_dn'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Group DN'),
      '#default_value' => $this->ldapServer->get('grp_test_grp_dn'),
      '#size' => 120,
      '#description' => $this->t("Optionally add a group to received information about it."),
      '#maxlength' => 255,
    ];

    $form['grp_test_grp_dn_writeable'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Group DN (write testing)'),
      '#default_value' => $this->ldapServer->get('grp_test_grp_dn_writeable'),
      '#size' => 120,
      '#maxlength' => 255,
      '#description' => $this->t("<strong>Warning: Testing writable groups means that existing groups can be deleted, created or have members added to it!</strong><br>Note that this test assumes that your group definition follows a pattern such as objectClass:['YOUR_CATEGORY','top']. If your directory differs, this might give false negatives."),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Test',
      '#weight' => 100,
    ];

    if ($form_state->get(['ldap_server_test_data'])) {
      $test_data = $form_state->get(['ldap_server_test_data']);
      $form['#suffix'] = '';

      $titles = [
        'basic' => 'Test Results',
        'group1' => 'Group Create, Delete, Add Member, Remove Member Tests',
        'group2' => 'User Group Membership Functions Test',
        'group_direct' => 'Direct queries for the group',
        'tokens' => 'User Token Samples',
        'groupfromDN' => 'Groups Derived From User DN',
      ];

      foreach ($test_data['results_tables'] as $table_name => $table_data) {
        $settings = [
          '#theme' => 'table',
          '#header' => $table_name == 'basic' ? ['Test'] : ['Test', 'Result'],
          '#rows' => $table_data,
        ];
        $form['#suffix'] .= '<h2>' . $titles[$table_name] . '</h2>' . $this->renderer->render($settings);
      }

      if (isset($test_data['username']) && isset($test_data['ldap_user'])) {
        $rows = $this->computeUserData($test_data);

        $settings = [
          '#theme' => 'table',
          '#header' => ['Attribute Name', 'Instance', 'Value', 'Token'],
          '#rows' => $rows,
        ];

        $form['#suffix'] .= '<div class="content">
        <h2>' . $this->t('LDAP Entry for %username (dn: %dn)', ['%dn' => $test_data['ldap_user']['dn'], '%username' => $test_data['username']]) . '</h2>'
                            . $this->renderer->render($settings) . '</div>';
      }

      if (!empty($test_data['username'])) {
        $user_name = $test_data['username'];
        if ($user = user_load_by_name($user_name)) {
          $form['#suffix'] .= '<h3>' . $this->t('Corresponding Drupal user object for @user:', ['@user' => $user_name]) . '</h3>';
          $form['#suffix'] .= '<pre>' . json_encode($user->toArray(), JSON_PRETTY_PRINT) . '</pre>';
          if (isset($test_data['group_entry'], $test_data['group_entry'][0])) {
            $form['#suffix'] .= '<h3>' . $this->t('Corresponding test group LDAP entry:') . '</h3>';
            $form['#suffix'] .= '<pre>' . json_encode($test_data['group_entry'][0], JSON_PRETTY_PRINT) . '</pre>';
          }
        }
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $server = Server::load($values['id']);

    if (!$values['id']) {
      $form_state->setErrorByName(NULL, $this->t('No server id found in form'));
    }
    elseif (!$server) {
      $form_state->setErrorByName(NULL, $this->t('Failed to create server object for server with server id=%id', [
        '%id' => $values['id'],
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Pass data back to form builder.
    $form_state->setRebuild(TRUE);

    $values = $form_state->getValues();
    $id = $values['id'];
    $this->ldapServer = Server::load($id);

    $this->resultsTables = [];

    $this->testConnection($values);

    $ldap_user = $this->testUserMapping($values['testing_drupal_username']);

    if ($ldap_user) {
      if (!$this->exception && !empty($values['grp_test_grp_dn'])) {
        $user = !empty($values['testing_drupal_username']) ? $values['testing_drupal_username'] : NULL;
        $group_entry = $this->testGroupDn($values['grp_test_grp_dn'], $user);
      }

      if (!empty($values['grp_test_grp_dn_writeable'])) {
        $this->testWritableGroup($values['grp_test_grp_dn_writeable'], $ldap_user['dn']);
      }
    }

    if ($ldap_user && isset($ldap_user['attr'])) {
      $tokens = $this->tokenProcessor->tokenizeLdapEntry($ldap_user['attr'], [], TokenProcessor::PREFIX, TokenProcessor::SUFFIX);
    }
    else {
      $tokens = [];
    }
    foreach ($tokens as $key => $value) {
      $this->resultsTables['tokens'][] = [$key, $this->binaryCheck($value)];
    }

    $form_state->set(['ldap_server_test_data'], [
      'username' => $values['testing_drupal_username'],
      'results_tables' => $this->resultsTables,
    ]);

    if ($ldap_user) {
      $form_state->set(['ldap_server_test_data', 'ldap_user'], $ldap_user);
    }

    if (isset($group_entry)) {
      $form_state->set(['ldap_server_test_data', 'group_entry'], $group_entry);
    }

  }

  /**
   * Test the Group DN.
   *
   * @param string $group_dn
   *   Group DN.
   * @param mixed|null $user
   *   User? Unknown.
   *
   * @return array
   *   Response.
   */
  private function testGroupDn($group_dn, $user) {
    $group_entry = $this->ldapServer->search($group_dn, 'objectClass=*');

    if ($group_entry) {
      foreach ([FALSE, TRUE] as $nested) {
        $this->ldapServer->set('grp_nested', $nested);
        // FALSE.
        $nested_display = ($nested) ? 'Yes' : 'No';
        if ($user) {
          // This is the parent function that will call FromUserAttr or
          // FromEntry.
          $memberships = $this->ldapServer->groupMembershipsFromUser($user);
          $settings = [
            '#theme' => 'item_list',
            '#items' => $memberships,
            '#list_type' => 'ul',
          ];
          $result = $this->renderer->render($settings);
          $this->resultsTables['group2'][] = [
            'Group memberships from user ("group_dns", nested=' . $nested_display . ') (' . count($memberships) . ' found)',
            $result,
          ];

          $result = ($this->ldapServer->groupIsMember($group_dn, $user)) ? 'Yes' : 'No';
          $this->resultsTables['group2'][] = [
            'groupIsMember from group DN ' . $group_dn . 'for ' . $user . ' nested=' . $nested_display . ')',
            $result,
          ];

          if ($this->ldapServer->groupUserMembershipsFromAttributeConfigured()) {
            $groupUserMembershipsFromUserAttributes = $this->ldapServer->groupUserMembershipsFromUserAttr($user);
            $settings = [
              '#theme' => 'item_list',
              '#items' => $groupUserMembershipsFromUserAttributes,
              '#list_type' => 'ul',
            ];
            $result = $this->renderer->render($settings);
          }
          else {
            $groupUserMembershipsFromUserAttributes = [];
            $result = "'A user LDAP attribute such as memberOf exists that contains a list of their group' is not configured.";
          }
          $this->resultsTables['group2'][] = [
            'Group memberships from user attribute for ' . $user . ' (nested=' . $nested_display . ') (' . count($groupUserMembershipsFromUserAttributes) . ' found)',
            $result,
          ];

          if ($this->ldapServer->groupGroupEntryMembershipsConfigured()) {
            $groupUserMembershipsFromEntry = $this->ldapServer->groupUserMembershipsFromEntry($user);
            $settings = [
              '#theme' => 'item_list',
              '#items' => $groupUserMembershipsFromEntry,
              '#list_type' => 'ul',
            ];
            $result = $this->renderer->render($settings);
          }
          else {
            $groupUserMembershipsFromEntry = [];
            $result = "Groups by entry not configured.";
          }
          $this->resultsTables['group2'][] = [
            'Group memberships from entry for ' . $user . ' (nested=' . $nested_display . ') (' . count($groupUserMembershipsFromEntry) . ' found)',
            $result,
          ];

          if (count($groupUserMembershipsFromEntry) && count($groupUserMembershipsFromUserAttributes)) {
            $diff1 = array_diff($groupUserMembershipsFromUserAttributes, $groupUserMembershipsFromEntry);
            $diff2 = array_diff($groupUserMembershipsFromEntry, $groupUserMembershipsFromUserAttributes);
            $settings = [
              '#theme' => 'item_list',
              '#items' => $diff1,
              '#list_type' => 'ul',
            ];
            $result1 = $this->renderer->render($settings);

            $settings = [
              '#theme' => 'item_list',
              '#items' => $diff2,
              '#list_type' => 'ul',
            ];
            $result2 = $this->renderer->render($settings);

            $this->resultsTables['group2'][] = [
              "groupUserMembershipsFromEntry and FromUserAttr Diff)",
              $result1,
            ];
            $this->resultsTables['group2'][] = [
              "FromUserAttr and groupUserMembershipsFromEntry Diff)",
              $result2,
            ];
          }
        }
      }
    }

    if ($groups_from_dn = $this->ldapServer->groupUserMembershipsFromDn($user)) {
      $settings = [
        '#theme' => 'item_list',
        '#items' => $groups_from_dn,
        '#list_type' => 'ul',
      ];
      $this->resultsTables['groupfromDN'][] = [
        $this->t("Groups from DN"),
        $this->renderer->render($settings),
      ];
    }

    $result = $this->ldapServer->groupAllMembers($group_dn);
    $settings = [
      '#theme' => 'item_list',
      '#items' => $result,
      '#list_type' => 'ul',
    ];
    $this->resultsTables['group_direct'][] = [
      $this->t('Entries found on group DN directly'),
      $this->renderer->render($settings),
    ];
    return $group_entry;
  }

  /**
   * Check if binary and escape if necessary.
   *
   * @param string $input
   *   Input string.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   Escaped string.
   */
  public static function binaryCheck($input) {
    if (preg_match('~[^\x20-\x7E\t\r\n]~', $input) > 0) {
      $truncatedString = Unicode::truncate($input, 120, FALSE, TRUE);
      return t('Binary (excerpt): @excerpt', ['@excerpt' => $truncatedString]);
    }
    else {
      return $input;
    }
  }

  /**
   * Test the user mappings.
   *
   * @param string $drupal_username
   *   The Drupal username.
   *
   * @return array
   *   Errors and the user.
   */
  public function testUserMapping($drupal_username) {
    $ldap_user = $this->ldapServer->matchUsernameToExistingLdapEntry($drupal_username);

    if (!$ldap_user) {
      $this->resultsTables['basic'][] = [
        'class' => 'color-error',
        'data' => [$this->t('Failed to find test user %username by searching on %user_attr = %username.',
          [
            '%username' => $drupal_username,
            '%user_attr' => $this->ldapServer->get('user_attr'),
          ]
          ),
        ],
      ];
      $this->exception = TRUE;
    }
    else {
      $this->resultsTables['basic'][] = [
        'class' => 'color-success',
        'data' => [
          $this->t('Found test user %username by searching on  %user_attr = %username.',
            ['%username' => $drupal_username, '%user_attr' => $this->ldapServer->get('user_attr')]
          ),
        ],
      ];
    }
    return $ldap_user;
  }

  /**
   * Boolean result message.
   *
   * @param bool $input
   *   State.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Output message.
   */
  private function booleanResult($input) {
    if ($input) {
      return $this->t('PASS');
    }
    else {
      return $this->t('FAIL');
    }
  }

  /**
   * Test writable groups.
   *
   * @param string $new_group
   *   The CN of the group to test.
   * @param string $member
   *   The CN of the member to test.
   */
  private function testWritableGroup($new_group, $member) {

    $writableGroupAttributes = [
      'objectClass' => [
        $this->ldapServer->get('grp_object_cat'),
        'top',
      ],
    ];
    $openLdap = FALSE;

    // This empty is needed for OpenLDAP, otherwise it won't get created.
    if (strtolower($this->ldapServer->get('grp_object_cat')) == 'groupofnames') {
      $openLdap = TRUE;
      $writableGroupAttributes['member'] = [''];
    }

    // Delete test group if it exists.
    if ($this->ldapServer->checkDnExists($new_group)) {
      $this->ldapServer->groupRemoveGroup($new_group, FALSE);
    }

    $this->resultsTables['group1'][] = [
      $this->t('Starting test without group (group was deleted if present): @group', ['@group' => $new_group]),
      $this->booleanResult(($this->ldapServer->checkDnExists($new_group) === FALSE)),
    ];

    // Make sure there are no entries being a member of it.
    $this->resultsTables['group1'][] = [
      $this->t('Are there no members in the writable group?', ['@group' => $new_group]),
      $this->booleanResult(($this->ldapServer->groupMembers($new_group) === FALSE)),
    ];

    // Add group.
    $attr = json_encode($writableGroupAttributes);
    $this->resultsTables['group1'][] = [
      $this->t('Add group @group with attributes @attributes', ['@group' => $new_group, '@attributes' => $attr]),
      $this->booleanResult($this->ldapServer->groupAddGroup($new_group, $writableGroupAttributes)),
    ];

    // Call to all members in an empty group returns empty array, not FALSE.
    $result = $this->ldapServer->groupMembers($new_group);
    if ($openLdap) {
      array_shift($result);
    }
    $this->resultsTables['group1'][] = [
      $this->t('Call to all members in an empty group returns an empty array for group', ['@group' => $new_group]),
      $this->booleanResult((is_array($result) && empty($result))),
    ];

    // Add member to group.
    $this->ldapServer->groupAddMember($new_group, $member);
    $result = $this->ldapServer->groupMembers($new_group);
    if ($openLdap) {
      array_shift($result);
    }
    $this->resultsTables['group1'][] = [
      $this->t('Add member to group @group with DN @dn', ['@group' => $new_group, '@dn' => $member]),
      $this->booleanResult(is_array($result) && !empty($result)),
    ];

    // Try to remove group with member in it.
    $result = $this->ldapServer->groupRemoveGroup($new_group, TRUE);
    $this->resultsTables['group1'][] = [
      $this->t('Remove group @group with member in it (not allowed)', ['@group' => $new_group]),
      $this->booleanResult(!$result),
    ];

    // Remove group member.
    $this->ldapServer->groupRemoveMember($new_group, $member);
    $result = $this->ldapServer->groupMembers($new_group);
    if ($openLdap) {
      array_shift($result);
    }
    $this->resultsTables['group1'][] = [
      $this->t('Remove group member @dn from @group', ['@group' => $new_group, '@dn' => $member]),
      $this->booleanResult((is_array($result) && empty($result))),
    ];

    if ($openLdap) {
      $this->ldapServer->groupRemoveGroup($new_group, FALSE);
      $this->resultsTables['group1'][] = [
        $this->t('Forced group removal of @group because this OpenLDAP configuration does not allow for safe removal.', ['@group' => $new_group]),
        $this->booleanResult(!($this->ldapServer->checkDnExists($new_group))),
      ];
    }
    else {
      $this->ldapServer->groupRemoveGroup($new_group, TRUE);
      $this->resultsTables['group1'][] = [
        $this->t('Remove group @group if empty', ['@group' => $new_group]),
        $this->booleanResult(!($this->ldapServer->checkDnExists($new_group))),
      ];
    }
  }

  /**
   * Helper function to bind as required for testing.
   */
  public function testBinding() {
    if ($this->config('')) {
      $bindResult = $this->ldapServer->bind();
    }
    if ($bindResult == Server::LDAP_SUCCESS) {
      $this->resultsTables['basic'][] = [
        'class' => 'color-success',
        'data' => [$this->t('Successfully bound to server')],
      ];
    }
    else {
      $this->resultsTables['basic'][] = [
        'class' => 'color-error',
        'data' => [$this->t('Failed to bind with service account. LDAP error: @error', ['@error' => $this->ldapServer->formattedError($bindResult)])],
      ];
      $this->exception = TRUE;
    }
  }

  /**
   * Compute user data.
   *
   * @param array $test_data
   *   Data to test on.
   *
   * @return array
   *   Computed data.
   */
  private function computeUserData(array $test_data) {
    $rows = [];
    foreach ($test_data['ldap_user']['attr'] as $key => $value) {
      if (is_numeric($key) || $key == 'count') {
      }
      elseif (is_array($value) && count($value) > 1) {
        $count = (int) $value['count'];
        foreach ($value as $i => $value2) {

          if ((string) $i == 'count') {
            continue;
          }
          elseif ($i == 0 && $count == 1) {
            $token = TokenProcessor::PREFIX . $key . TokenProcessor::SUFFIX;
          }
          elseif ($i == 0 && $count > 1) {
            $token = TokenProcessor::PREFIX . $key . TokenProcessor::DELIMITER . '0' . TokenProcessor::SUFFIX;
          }
          elseif (($i == $count - 1) && $count > 1) {
            $token = TokenProcessor::PREFIX . $key . TokenProcessor::DELIMITER . 'last' . TokenProcessor::SUFFIX;
          }
          elseif ($count > 1) {
            $token = TokenProcessor::PREFIX . $key . TokenProcessor::DELIMITER . $i . TokenProcessor::SUFFIX;
          }
          else {
            $token = "";
          }
          $rows[] = ['data' => [$key, $i, self::binaryCheck($value2), $token]];
        }
      }
    }
    return $rows;
  }

  /**
   * Test the connection.
   *
   * @param array $values
   *   Input data.
   */
  private function testConnection(array $values) {

    if ($this->ldapServer->connect() != Server::LDAP_SUCCESS) {
      $this->resultsTables['basic'][] = [
        'class' => 'color-error',
        'data' => [$this->t('Failed to connect to LDAP server: @error', $this->ldapServer->formattedError($this->ldapServer->ldapErrorNumber()))],
      ];
      $this->exception = TRUE;
      return;
    }

    if ($this->ldapServer->get('bind_method') == 'service_account') {
      $this->resultsTables['basic'][] = [$this->t('Binding with DN for non-anonymous search (%bind_dn).', ['%bind_dn' => $this->ldapServer->get('binddn')])];
      $this->testBinding();
    }
    else {
      $this->resultsTables['basic'][] = [$this->t('Binding with null DN for anonymous search.')];
      $this->testBinding();
    }

    if ($this->ldapServer->get('bind_method') == 'anon_user' || $this->ldapServer->get('bind_method') == 'user') {
      CredentialsStorage::storeUserDn($values['testing_drupal_username']);
      CredentialsStorage::storeUserPassword($values['testing_drupal_userpw']);

      $this->resultsTables['basic'][] = [$this->t('Binding with user credentials (%bind_dn).', ['%bind_dn' => $values['testing_drupal_username']])];
      $this->testBinding();
      $ldap_user = $this->testUserMapping($values['testing_drupal_username']);
      if (!$this->exception) {
        $mapping[] = "dn = " . $ldap_user['dn'];
        foreach ($ldap_user['attr'] as $key => $value) {
          if (is_array($value)) {
            $mapping[] = "$key = " . $this->binaryCheck($value[0]);
          }
        }

        $item_list = [
          '#list_type' => 'ul',
          '#theme' => 'item_list',
          '#items' => $mapping,
          '#title' => $this->t('Attributes available to anonymous search', [
            '%bind_dn' => $this->ldapServer->get('binddn'),
          ]),
        ];
        $this->resultsTables['basic'][] = [render($item_list)];
      }
      $this->resultsTables['basic'][] = [
        $this->t('Binding with DN (%bind_dn), using supplied password.', [
          '%bind_dn' => $ldap_user['dn'],
        ]),
      ];
      $this->testBinding();
    }
  }

}
