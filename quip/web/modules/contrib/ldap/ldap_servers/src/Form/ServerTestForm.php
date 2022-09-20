<?php

declare(strict_types = 1);

namespace Drupal\ldap_servers\Form;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Render\Renderer;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers\LdapBridgeInterface;
use Drupal\ldap_servers\LdapGroupManager;
use Drupal\ldap_servers\Processor\TokenProcessor;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\LdapException;
use function in_array;

/**
 * Use Drupal\Core\Form\FormBase;.
 */
class ServerTestForm extends EntityForm {

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

  /**
   * Config.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Token processor.
   *
   * @var \Drupal\ldap_servers\Processor\TokenProcessor
   */
  protected $tokenProcessor;

  /**
   * Renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * LDAP bridge.
   *
   * @var \Drupal\ldap_servers\LdapBridge
   */
  protected $ldapBridge;

  /**
   * LDAP Group Manager.
   *
   * @var \Drupal\ldap_servers\LdapGroupManager
   */
  protected $ldapGroupManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ldap_servers_test_form';
  }

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config Factory.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   Module handler.
   * @param \Drupal\ldap_servers\Processor\TokenProcessor $token_processor
   *   Token Processor.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   Renderer.
   * @param \Drupal\ldap_servers\LdapBridgeInterface $ldap_bridge
   *   LDAP Bridge.
   * @param \Drupal\ldap_servers\LdapGroupManager $ldap_group_manager
   *   LDAP Group Manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ModuleHandler $module_handler,
    TokenProcessor $token_processor,
    Renderer $renderer,
    LdapBridgeInterface $ldap_bridge,
    LdapGroupManager $ldap_group_manager
  ) {
    $this->config = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->tokenProcessor = $token_processor;
    $this->renderer = $renderer;
    $this->ldapBridge = $ldap_bridge;
    $this->ldapGroupManager = $ldap_group_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ServerTestForm {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('ldap.token_processor'),
      $container->get('renderer'),
      $container->get('ldap.bridge'),
      $container->get('ldap.group_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $ldap_server = NULL): array {
    if ($ldap_server) {
      $this->ldapServer = $ldap_server;
    }

    $form['#title'] = $this->t(
      'Test LDAP Server Configuration: @server',
      ['@server' => $this->ldapServer->label()]
    );

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

    $userCredentialsRequired = FALSE;
    if (in_array($this->ldapServer->get('bind_method'), [
      'anon_user',
      'user',
    ], TRUE)) {
      $userCredentialsRequired = TRUE;
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
      '#default_value' => $this->ldapServer->getTestingDrupalUserDn(),
      // @todo Add this field back in. The logic for it is missing completely.
      '#access' => FALSE,
      '#size' => 120,
      '#maxlength' => 255,
      '#description' => $this->t("The user is not required to exist in Drupal and testing will not affect the user's LDAP or Drupal account."),
    ];

    $form['grp_test_grp_dn'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Group DN'),
      '#default_value' => $this->ldapServer->get('grp_test_grp_dn'),
      '#size' => 120,
      '#description' => $this->t('Optionally add a group to received information about it.'),
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
          '#header' => $table_name === 'basic' ? ['Test'] : ['Test', 'Result'],
          '#rows' => $table_data,
        ];
        $form['#suffix'] .= '<h2>' . $titles[$table_name] . '</h2>' . $this->renderer->render($settings);
      }

      if (isset($test_data['username'], $test_data['ldap_user'])) {
        $rows = $this->computeUserData($test_data['ldap_user']);

        $settings = [
          '#theme' => 'table',
          '#header' => ['Attribute Name', 'Instance', 'Value', 'Token'],
          '#rows' => $rows,
        ];

        $form['#suffix'] .= sprintf(
          '<div class="content"><h2>%s</h2>%s</div>',
          $this->t('LDAP Entry for %username (dn: %dn)', [
            '%dn' => $test_data['ldap_user']->getDn(),
            '%username' => $test_data['username'],
          ]),
          $this->renderer->render($settings)
        );
      }

      if (!empty($test_data['username'])) {
        $users = $this->entityTypeManager->getStorage('user')
          ->loadByProperties(['name' => $test_data['username']]);
        $users ? reset($users) : FALSE;
        if ($users) {
          $form['#suffix'] .= sprintf(
            '<h3>%s</h3>, <pre>%s</pre>',
            $this->t('Corresponding Drupal user object for @user:', [
              '@user' => $test_data['username'],
            ]),
            json_encode(reset($users)->toArray(), JSON_PRETTY_PRINT)
          );
          if (isset($test_data['group_entry'][0])) {
            $form['#suffix'] .= sprintf(
              '<h3>%s</h3>, <pre>%s</pre>',
              $this->t('Corresponding test group LDAP entry:'),
              json_encode($test_data['group_entry'][0], JSON_PRETTY_PRINT)
            );
          }
        }
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
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
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Pass data back to form builder.
    $form_state->setRebuild();

    $values = $form_state->getValues();
    $this->ldapServer = Server::load($values['id']);
    $this->ldapBridge->setServer($this->ldapServer);

    $this->resultsTables = [];

    $this->testConnection();

    $ldap_entry = $this->testUserMapping($values['testing_drupal_username']);

    if ($ldap_entry) {
      if (!$this->exception && !empty($values['grp_test_grp_dn'])) {
        $user = !empty($values['testing_drupal_username']) ? $values['testing_drupal_username'] : NULL;
        $group_entry = $this->testGroupDn($values['grp_test_grp_dn'], $user);
      }

      if (!empty($values['grp_test_grp_dn_writeable'])) {
        $this->testWritableGroup($values['grp_test_grp_dn_writeable'], $ldap_entry->getDn());
      }
    }

    if ($ldap_entry) {
      $desired_attributes = '';
      foreach (array_keys($ldap_entry->getAttributes()) as $attribute) {
        $desired_attributes .= sprintf('[%s]', $attribute);
      }
      $this->tokenProcessor->ldapEntryReplacementsForDrupalAccount(
          $ldap_entry,
          $desired_attributes
        );
    }

    foreach ($this->tokenProcessor->getTokens() as $key => $value) {
      $this->resultsTables['tokens'][] = [$key, self::binaryCheck($value)];
    }

    $form_state->set(['ldap_server_test_data'], [
      'username' => $values['testing_drupal_username'],
      'results_tables' => $this->resultsTables,
    ]);

    if ($ldap_entry) {
      $form_state->set(['ldap_server_test_data', 'ldap_user'], $ldap_entry);
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
   * @param string|null $username
   *   Username.
   *
   * @return array
   *   Response.
   */
  private function testGroupDn(string $group_dn, ?string $username): array {

    $ldap = $this->ldapBridge->get();
    try {
      $group_entry = $ldap->query($group_dn, 'objectClass=*')->execute()->toArray();
    }
    catch (LdapException $e) {
      $group_entry = [];
    }

    if (!empty($group_entry)) {
      foreach ([TRUE, FALSE] as $nested) {
        $this->ldapServer->set('grp_nested', $nested);
        // @todo Need to pass server by reference to inject nesting state.
        $this->ldapGroupManager->setServerById($this->ldapServer->id());
        // FALSE.
        $nested_display = ($nested) ? 'Yes' : 'No';
        if ($username) {
          // This is the parent function that will call FromUserAttr or
          // FromEntry.
          $memberships = $this->ldapGroupManager->groupMembershipsFromUser($username);
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

          $result = ($this->ldapGroupManager->groupIsMember($group_dn, $username)) ? 'Yes' : 'No';
          $this->resultsTables['group2'][] = [
            sprintf('groupIsMember from group DN "%s" for %s nested=%s', $group_dn, $username, $nested_display),
            $result,
          ];

          $groupUserMembershipsFromUserAttributes = [];
          if ($this->ldapServer->isGroupUserMembershipAttributeInUse()) {
            $entry = $this->ldapGroupManager->matchUsernameToExistingLdapEntry($username);
            $groupUserMembershipsFromUserAttributes = $this->ldapGroupManager->groupUserMembershipsFromUserAttr($entry);
            $settings = [
              '#theme' => 'item_list',
              '#items' => $groupUserMembershipsFromUserAttributes,
              '#list_type' => 'ul',
            ];
            $result = $this->renderer->render($settings);
          }
          else {
            $result = "'A user LDAP attribute such as memberOf exists that contains a list of their group' is not configured.";
          }
          $this->resultsTables['group2'][] = [
            'Group memberships from user attribute for ' . $username . ' (nested=' . $nested_display . ') (' . count($groupUserMembershipsFromUserAttributes) . ' found)',
            $result,
          ];

          $groupUserMembershipsFromEntry = [];
          if ($this->ldapGroupManager->groupGroupEntryMembershipsConfigured()) {
            $ldap_entry = $this->ldapGroupManager->matchUsernameToExistingLdapEntry($username);
            $groupUserMembershipsFromEntry = $this->ldapGroupManager->groupUserMembershipsFromEntry($ldap_entry);
            $settings = [
              '#theme' => 'item_list',
              '#items' => $groupUserMembershipsFromEntry,
              '#list_type' => 'ul',
            ];
            $result = $this->renderer->render($settings);
          }
          else {
            $result = 'Groups by entry not configured.';
          }

          $this->resultsTables['group2'][] = [
            sprintf(
              'Group memberships from entry for %s (nested=%s) (%s found)',
              $username,
              $nested_display,
              count($groupUserMembershipsFromEntry)
            ),
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
              'groupUserMembershipsFromEntry and FromUserAttr Diff)',
              $result1,
            ];
            $this->resultsTables['group2'][] = [
              'FromUserAttr and groupUserMembershipsFromEntry Diff)',
              $result2,
            ];
          }
        }
      }
    }

    if ($groups_from_dn = $this->ldapGroupManager->groupUserMembershipsFromDn($username)) {
      $settings = [
        '#theme' => 'item_list',
        '#items' => $groups_from_dn,
        '#list_type' => 'ul',
      ];
      $this->resultsTables['groupfromDN'][] = [
        $this->t('Groups from DN'),
        $this->renderer->render($settings),
      ];
    }

    $result = $this->ldapGroupManager->groupAllMembers($group_dn);
    if ($result) {
      $settings = [
        '#theme' => 'item_list',
        '#items' => $result,
        '#list_type' => 'ul',
      ];
      $this->resultsTables['group_direct'][] = [
        $this->t('Entries found on group DN directly'),
        $this->renderer->render($settings),
      ];
    }

    return $group_entry;
  }

  /**
   * Check if binary and escape if necessary.
   *
   * @param string $input
   *   Input string.
   *
   * @return string
   *   Escaped string.
   */
  public static function binaryCheck(string $input): string {
    if (preg_match('~[^\x20-\x7E\t\r\n]~', $input) > 0) {
      $truncatedString = Unicode::truncate($input, 120, FALSE, TRUE);
      return (string) t('Binary (excerpt): @excerpt', ['@excerpt' => $truncatedString]);
    }

    return $input;
  }

  /**
   * Test the user mappings.
   *
   * @param string $drupal_username
   *   The Drupal username.
   *
   * @return \Symfony\Component\Ldap\Entry|false
   *   Entry.
   */
  public function testUserMapping(string $drupal_username) {
    $this->ldapGroupManager->setServerById($this->ldapServer->id());
    $ldap_user = $this->ldapGroupManager->matchUsernameToExistingLdapEntry($drupal_username);

    if ($ldap_user) {
      $this->resultsTables['basic'][] = [
        'class' => 'color-success',
        'data' => [
          $this->t('Found test user %username by searching on  %user_attr = %username.',
            [
              '%username' => $drupal_username,
              '%user_attr' => $this->ldapServer->getAuthenticationNameAttribute(),
            ]
          ),
        ],
      ];
    }
    else {
      $this->resultsTables['basic'][] = [
        'class' => 'color-error',
        'data' => [
          $this->t('Failed to find test user %username by searching on %user_attr = %username.',
            [
              '%username' => $drupal_username,
              '%user_attr' => $this->ldapServer->getAuthenticationNameAttribute(),
            ]
          ),
        ],
      ];
      $this->exception = TRUE;
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
  private function booleanResult(bool $input): TranslatableMarkup {
    if ($input) {
      return $this->t('PASS');
    }

    return $this->t('FAIL');
  }

  /**
   * Test writable groups.
   *
   * @param string $new_group
   *   The CN of the group to test.
   * @param string $member
   *   The CN of the member to test.
   */
  private function testWritableGroup(string $new_group, string $member): void {
    if (!$this->ldapGroupManager->setServerById($this->ldapServer->id())) {
      return;
    }

    $writableGroupAttributes = [
      'objectClass' => [
        $this->ldapServer->get('grp_object_cat'),
        'top',
      ],
    ];
    $openLdap = FALSE;

    // This empty is needed for OpenLDAP, otherwise it won't get created.
    if (strtolower($this->ldapServer->get('grp_object_cat')) === 'groupofnames') {
      $openLdap = TRUE;
      $writableGroupAttributes['member'] = [''];
    }

    // Delete test group if it exists.
    if ($this->ldapGroupManager->checkDnExists($new_group)) {
      $this->ldapGroupManager->groupRemoveGroup($new_group, FALSE);
    }

    $this->resultsTables['group1'][] = [
      $this->t('Starting test without group (group was deleted if present): @group', ['@group' => $new_group]),
      $this->booleanResult((!$this->ldapGroupManager->checkDnExists($new_group))),
    ];

    // Make sure there are no entries being a member of it.
    $this->resultsTables['group1'][] = [
      $this->t('Are there no members in the writable group?', ['@group' => $new_group]),
      $this->booleanResult((!$this->ldapGroupManager->groupMembers($new_group))),
    ];

    // Add group.
    $attr = json_encode($writableGroupAttributes);
    $this->resultsTables['group1'][] = [
      $this->t('Add group @group with attributes @attributes', [
        '@group' => $new_group,
        '@attributes' => $attr,
      ]),
      $this->booleanResult($this->ldapGroupManager->groupAddGroup($new_group, $writableGroupAttributes)),
    ];

    // Call to all members in an empty group returns empty array, not FALSE.
    $result = $this->ldapGroupManager->groupMembers($new_group);
    if ($openLdap) {
      array_shift($result);
    }
    $this->resultsTables['group1'][] = [
      $this->t('Call to all members in an empty group returns an empty array for group', ['@group' => $new_group]),
      $this->booleanResult(($result === [])),
    ];

    // Add member to group.
    $this->ldapGroupManager->groupAddMember($new_group, $member);
    $result = $this->ldapGroupManager->groupMembers($new_group);
    if ($openLdap) {
      array_shift($result);
    }
    $this->resultsTables['group1'][] = [
      $this->t('Add member to group @group with DN @dn', [
        '@group' => $new_group,
        '@dn' => $member,
      ]),
      $this->booleanResult(is_array($result) && !empty($result)),
    ];

    // Try to remove group with member in it.
    $result = $this->ldapGroupManager->groupRemoveGroup($new_group);
    $this->resultsTables['group1'][] = [
      $this->t('Remove group @group with member in it (not allowed)', ['@group' => $new_group]),
      $this->booleanResult(!$result),
    ];

    // Remove group member.
    $this->ldapGroupManager->groupRemoveMember($new_group, $member);
    $result = $this->ldapGroupManager->groupMembers($new_group);
    if ($openLdap) {
      array_shift($result);
    }
    $this->resultsTables['group1'][] = [
      $this->t('Remove group member @dn from @group', [
        '@group' => $new_group,
        '@dn' => $member,
      ]),
      $this->booleanResult(($result === [])),
    ];

    if ($openLdap) {
      $this->ldapGroupManager->groupRemoveGroup($new_group, FALSE);
      $this->resultsTables['group1'][] = [
        $this->t('Forced group removal of @group because this OpenLDAP configuration does not allow for safe removal.', ['@group' => $new_group]),
        $this->booleanResult(!($this->ldapGroupManager->checkDnExists($new_group))),
      ];
    }
    else {
      $this->ldapGroupManager->groupRemoveGroup($new_group);
      $this->resultsTables['group1'][] = [
        $this->t('Remove group @group if empty', ['@group' => $new_group]),
        $this->booleanResult(!($this->ldapGroupManager->checkDnExists($new_group))),
      ];
    }
  }

  /**
   * Compute user data.
   *
   * @param \Symfony\Component\Ldap\Entry $ldap_entry
   *   Data to test on.
   *
   * @return array
   *   Computed data.
   */
  private function computeUserData(Entry $ldap_entry): array {
    $rows = [];
    foreach ($ldap_entry->getAttributes() as $attribute_key => $attribute_value) {
      foreach ($attribute_value as $item_key => $item_value) {
        $item_value = self::binaryCheck($item_value);
        $token = '';

        if (count($attribute_value) === 1) {
          $token = sprintf('[%s]', $attribute_key);
        }

        if (count($attribute_value) > 1) {
          if ($item_key === count($attribute_value) - 1) {
            $token = sprintf('[%s:last]', $attribute_key);
            $rows[] = [
              'data' => [$attribute_key, $item_key, $item_value, $token],
            ];
          }
          $token = sprintf('[%s:%s]', $attribute_key, $item_key);
        }
        $rows[] = ['data' => [$attribute_key, $item_key, $item_value, $token]];
      }
    }
    return $rows;
  }

  /**
   * Test the connection.
   */
  private function testConnection(): void {
    if (!$this->ldapBridge->bind()) {
      $this->resultsTables['basic'][] = [
        'class' => 'color-error',
        'data' => [$this->t('Failed to connect and bind to LDAP server, see logs for details.')],
      ];
      $this->exception = TRUE;
    }
  }

}
