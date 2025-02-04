<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_authorization\Unit;

use Drupal\Core\Form\FormState;
use Drupal\ldap_authorization\Plugin\authorization\Provider\LDAPAuthorizationProvider;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Authorization provider tests.
 *
 * @group authorization
 */
class LdapAuthorizationProviderTest extends UnitTestCase {

  /**
   * Provider plugin.
   *
   * @var \Drupal\ldap_authorization\Plugin\authorization\Provider\LDAPAuthorizationProvider
   */
  protected $providerPlugin;

  /**
   * Profile.
   *
   * @var \Drupal\authorization\AuthorizationProfileInterface
   */
  protected $profile;

  /**
   * Storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $storage;

  /**
   * Messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * LDAP Drupal user processor.
   *
   * @var \Drupal\ldap_user\Processor\DrupalUserProcessor
   */
  protected $ldapDrupalUserProcessor;

  /**
   * LDAP user manager.
   *
   * @var \Drupal\ldap_servers\LdapUserManager
   */
  protected $ldapUserManager;

  /**
   * LDAP group manager.
   *
   * @var \Drupal\ldap_servers\LdapGroupManager
   */
  protected $ldapGroupManager;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $string_translation = $this->getStringTranslationStub();
    $container->set('string_translation', $string_translation);

    $this->storage = $this->createMock('Drupal\Core\Config\Entity\ConfigEntityStorageInterface');

    $entity_type_manager = $this->createMock('Drupal\Core\Entity\EntityTypeManagerInterface');
    $entity_type_manager->method('getStorage')
      ->with('ldap_server')
      ->willReturn($this->storage);
    $container->set('entity_type.manager', $entity_type_manager);

    $this->ldapDrupalUserProcessor = $this->createMock('Drupal\ldap_user\Processor\DrupalUserProcessor');
    $container->set('ldap.drupal_user_processor', $this->ldapDrupalUserProcessor);

    $this->ldapUserManager = $this->createMock('Drupal\ldap_servers\LdapUserManager');
    $container->set('ldap.user_manager', $this->ldapUserManager);

    $this->ldapGroupManager = $this->createMock('Drupal\ldap_servers\LdapGroupManager');

    $container->set('ldap.group_manager', $this->ldapGroupManager);

    $this->logger = $this->createMock('Psr\Log\LoggerInterface');
    $container->set('logger.channel.ldap_authorization', $this->logger);

    $ldap_detail_log = $this->createMock('Drupal\ldap_servers\Logger\LdapDetailLog');
    $container->set('ldap.detail_log', $ldap_detail_log);

    $this->messenger = $this->createMock('Drupal\Core\Messenger\MessengerInterface');
    $container->set('messenger', $this->messenger);

    \Drupal::setContainer($container);

    $this->profile = $this->createMock('\Drupal\authorization\AuthorizationProfileInterface');

    $label = $this->createMock('\Drupal\Core\StringTranslation\TranslatableMarkup');
    $label->method('render')->willReturn('LDAP Authorization');
    $configuration = [
      'profile' => $this->profile,
      'status' => [
        'only_ldap_authenticated' => FALSE,
      ],
    ];
    $plugin_id = 'ldap_provider';
    $definition = [
      'id' => 'ldap_provider',
      'label' => $label,
    ];
    $this->providerPlugin = LDAPAuthorizationProvider::create($container, $configuration, $plugin_id, $definition);
  }

  /**
   * Test regex validation().
   */
  public function testValidateRowForm(): void {

    $form_state = $this->createMock(FormState::class);
    $form_state->expects($this->exactly(2))
      ->method('setErrorByName');
    $form_state->expects($this->once())
      ->method('getValues')
      ->willReturn([
        0 => [
          'provider_mappings' => [
            'is_regex' => 1,
            'query' => 'example',
          ],
        ],
        1 => [
          'provider_mappings' => [
            'is_regex' => 1,
            'query' => '/.*/',
          ],
        ],
        2 => [
          'provider_mappings' => [
            'is_regex' => 0,
            'query' => '/.*/',
          ],
        ],
        3 => [
          'provider_mappings' => [
            'is_regex' => 1,
            'query' => '',
          ],
        ],
      ]);

    $form = [];
    $this->providerPlugin->validateRowForm($form, $form_state);
  }

  /**
   * Test the filter proposal.
   */
  public function testFilterProposal(): void {

    // Example of groups defined in.
    $input = [
      'cn=students',
    ];

    $this->assertCount(
      1,
      $this->providerPlugin->filterProposals($input, [
        'query' => 'cn=students',
        'is_regex' => FALSE,
      ])
    );

    $input = [
      'cn=students,ou=groups,dc=hogwarts,dc=edu',
      'cn=gryffindor,ou=groups,dc=hogwarts,dc=edu',
      'cn=users,ou=groups,dc=hogwarts,dc=edu',
    ];

    $this->assertCount(
      0,
      $this->providerPlugin->filterProposals($input, [
        'query' => 'cn=students',
        'is_regex' => FALSE,
      ])
    );

    $this->assertCount(
      1,
      $this->providerPlugin->filterProposals($input, [
        'query' => 'cn=students,ou=groups,dc=hogwarts,dc=edu',
        'is_regex' => FALSE,
      ])
    );
    $this->assertCount(
      1,
      $this->providerPlugin->filterProposals($input, [
        'query' => 'CN=students,ou=groups,dc=hogwarts,dc=edu',
        'is_regex' => FALSE,
      ])
    );
    $this->assertCount(
      1,
      $this->providerPlugin->filterProposals($input, [
        'query' => '/cn=students/i',
        'is_regex' => TRUE,
      ])
    );
    $this->assertCount(
      1,
      $this->providerPlugin->filterProposals($input, [
        'query' => '/CN=students/i',
        'is_regex' => TRUE,
      ])
    );

    // Get only one of the values.
    $input = [
      'cn=users,ou=gryffindor,ou=slytherin,ou=ravenclaw,dc=hogwarts,dc=edu',
    ];
    $output = $this->providerPlugin->filterProposals($input, [
      'query' => '/(?<=ou=).+?[^\,]*/i',
      'is_regex' => TRUE,
    ]);
    $this->assertEquals('gryffindor', $output[0]);
    $this->assertCount(1, $output);

    $input = [
      'memberOf=students,ou=groups,dc=hogwarts,dc=edu',
    ];
    $this->assertCount(
      1,
      $this->providerPlugin->filterProposals($input, [
        'query' => 'memberOf=students,ou=groups,dc=hogwarts,dc=edu',
        'is_regex' => FALSE,
      ])
    );
    $this->assertCount(
      1,
      $this->providerPlugin->filterProposals($input, [
        'query' => 'memberof=students,ou=groups,dc=hogwarts,dc=edu',
        'is_regex' => FALSE,
      ])
    );
    $this->assertCount(
      1,
      $this->providerPlugin->filterProposals($input, [
        'query' => '/^memberof=students/i',
        'is_regex' => TRUE,
      ])
    );
    $this->assertCount(
      1,
      $this->providerPlugin->filterProposals($input, [
        'query' => '/^memberOf=students/i',
        'is_regex' => TRUE,
      ])
    );
    $this->assertCount(
      0,
      $this->providerPlugin->filterProposals($input, [
        'query' => '/^emberOf=students/i',
        'is_regex' => TRUE,
      ])
    );
  }

  /**
   * Test the filter proposal. Multiple matches.
   */
  public function testFilterProposalMultipleMatches(): void {

    $input = [
      'cn=students,ou=groups,dc=hogwarts,dc=edu',
      'cn=gryffindor,ou=groups,dc=hogwarts,dc=edu',
      'cn=users,ou=groups,dc=hogwarts,dc=edu',
    ];
    $proposals = $this->providerPlugin->filterProposals($input, [
      'query' => '/ou=(.)*,/i',
      'is_regex' => TRUE,
    ]);

    $this->assertCount(3, $proposals);
  }

  /**
   * Test the filter proposal. Regex is invalid. Exception.
   */
  public function testFilterProposalSaysRegexButIsNot(): void {

    $input = [
      'cn=students,ou=groups,dc=hogwarts,dc=edu',
      'cn=gryffindor,ou=groups,dc=hogwarts,dc=edu',
      'cn=users,ou=groups,dc=hogwarts,dc=edu',
    ];
    $proposals = $this->providerPlugin->filterProposals($input, [
      'query' => 'gryffindor',
      'is_regex' => TRUE,
    ]);

    $this->assertCount(0, $proposals);
  }

  /**
   * Test the filter proposal.
   */
  public function testFilterProposalNoMatches(): void {

    $input = [
      'cn=students,ou=groups,dc=hogwarts,dc=edu',
      'cn=gryffindor,ou=groups,dc=hogwarts,dc=edu',
      'cn=users,ou=groups,dc=hogwarts,dc=edu',
    ];
    $proposals = $this->providerPlugin->filterProposals($input, [
      'query' => '/cn=slytherin,/i',
      'is_regex' => TRUE,
    ]);

    $this->assertCount(0, $proposals);
  }

  /**
   * Test the buildConfigurationForm(). No ldap servers.
   */
  public function testBuildConfigurationFormWithoutServers(): void {
    $consumer = $this->createMock('Drupal\authorization\Consumer\ConsumerInterface');
    $this->profile->expects($this->once())
      ->method('hasValidConsumer')
      ->willReturn(TRUE);
    $this->profile->expects($this->exactly(2))
      ->method('getConsumer')
      ->willReturn($consumer);
    $this->storage->expects($this->once())
      ->method('loadMultiple')
      ->willReturn([]);
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form = $this->providerPlugin->buildConfigurationForm([], $form_state);

    $this->assertIsArray($form);
    $this->assertCount(2, $form);
    $this->assertArrayHasKey('status', $form);
    $this->assertArrayHasKey('filter_and_mappings', $form);
  }

  /**
   * Test the buildConfigurationForm(). Single ldap server.
   */
  public function testBuildConfigurationFormWithSingleServer(): void {
    $consumer = $this->createMock('Drupal\authorization\Consumer\ConsumerInterface');
    $this->profile->expects($this->once())
      ->method('hasValidConsumer')
      ->willReturn(TRUE);
    $this->profile->expects($this->exactly(2))
      ->method('getConsumer')
      ->willReturn($consumer);
    $server = $this->createMock('Drupal\ldap_servers\Entity\Server');
    $this->storage->expects($this->once())
      ->method('loadMultiple')
      ->willReturn([$server]);
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form = $this->providerPlugin->buildConfigurationForm([], $form_state);

    $this->assertIsArray($form);
    $this->assertCount(2, $form);
    $this->assertArrayHasKey('status', $form);
    $this->assertArrayHasKey('filter_and_mappings', $form);
  }

  /**
   * Test the buildConfigurationForm(). Multiple ldap servers.
   */
  public function testBuildConfigurationFormWithMultipleServers(): void {
    $consumer = $this->createMock('Drupal\authorization\Consumer\ConsumerInterface');
    $this->profile->expects($this->once())
      ->method('hasValidConsumer')
      ->willReturn(TRUE);
    $this->profile->expects($this->exactly(2))
      ->method('getConsumer')
      ->willReturn($consumer);
    $server_a = $this->createMock('Drupal\ldap_servers\Entity\Server');
    $server_b = $this->createMock('Drupal\ldap_servers\Entity\Server');
    $this->storage->expects($this->once())
      ->method('loadMultiple')
      ->willReturn([$server_a, $server_b]);
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form = $this->providerPlugin->buildConfigurationForm([], $form_state);

    $this->assertIsArray($form);
    $this->assertCount(2, $form);
    $this->assertArrayHasKey('status', $form);
    $this->assertArrayHasKey('filter_and_mappings', $form);
  }

  /**
   * Test the buildConfigurationForm(). Default server.
   */
  public function testBuildConfigurationFormDefaultServer(): void {
    $consumer = $this->createMock('Drupal\authorization\Consumer\ConsumerInterface');
    $this->profile->expects($this->once())
      ->method('hasValidConsumer')
      ->willReturn(TRUE);
    $this->profile->expects($this->exactly(2))
      ->method('getConsumer')
      ->willReturn($consumer);
    $this->profile->expects($this->once())
      ->method('getProviderConfig')
      ->willReturn(['status' => ['server' => 'server_a']]);
    $server_a = $this->createMock('Drupal\ldap_servers\Entity\Server');
    $server_b = $this->createMock('Drupal\ldap_servers\Entity\Server');
    $this->storage->expects($this->once())
      ->method('loadMultiple')
      ->willReturn(['server_a' => $server_a, 'server_b' => $server_b]);
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form = $this->providerPlugin->buildConfigurationForm([], $form_state);

    $this->assertIsArray($form);
    $this->assertCount(2, $form);
    $this->assertArrayHasKey('status', $form);
    $this->assertArrayHasKey('filter_and_mappings', $form);
  }

  /**
   * Test the buildRowForm().
   */
  public function testBuildRowForm(): void {
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');

    $form = $this->providerPlugin->buildRowForm([], $form_state, 0);

    $this->assertIsArray($form);
    $this->assertCount(2, $form);
    $this->assertArrayHasKey('query', $form);
    $this->assertArrayHasKey('is_regex', $form);
  }

  /**
   * Tests the getProposals() method.
   */
  public function testGetProposalsUserExcluded(): void {

    $user = $this->createMock('Drupal\user\UserInterface');
    $this->ldapDrupalUserProcessor->expects($this->once())
      ->method('excludeUser')
      ->with($user)
      ->willReturn(TRUE);
    $this->expectException(
      'Drupal\authorization\AuthorizationSkipAuthorization',
      'User excluded from LDAP authorization.'
    );

    $this->providerPlugin->getProposals($user);
  }

  /**
   * Tests the getProposals() method.
   */
  public function testGetProposalsUserServerDisabled(): void {
    $provider_config = [
      'status' => [
        'server' => 'server_a',
      ],
      'filter_and_mappings' => [
        0 => [
          'provider_mappings' => [
            'is_regex' => 0,
            'query' => 'cn=students',
          ],
        ],
      ],
    ];
    $server_a = $this->createMock('Drupal\ldap_servers\Entity\Server');
    $server_a->expects($this->once())
      ->method('status')
      ->willReturn(FALSE);
    $this->storage->expects($this->once())
      ->method('load')
      ->with('server_a')
      ->willReturn($server_a);
    $this->profile->expects($this->once())
      ->method('getProviderConfig')
      ->willReturn($provider_config);
    $user = $this->createMock('Drupal\user\UserInterface');
    $this->ldapDrupalUserProcessor->expects($this->once())
      ->method('excludeUser')
      ->with($user)
      ->willReturn(FALSE);

    $result = $this->providerPlugin->getProposals($user);

    $this->assertIsArray($result);
    $this->assertCount(0, $result);
  }

  /**
   * Tests the getProposals() method.
   */
  public function testGetProposalsUserNewUser(): void {
    $this->ldapUserManager->expects($this->once())
      ->method('getUserDataByAccount')
      ->willReturn(FALSE);
    $provider_config = [
      'status' => [
        'server' => 'server_a',
        'only_ldap_authenticated' => TRUE,
      ],
    ];
    $server_a = $this->createMock('Drupal\ldap_servers\Entity\Server');
    $server_a->expects($this->once())
      ->method('status')
      ->willReturn(TRUE);
    $this->storage->expects($this->once())
      ->method('load')
      ->with('server_a')
      ->willReturn($server_a);
    $this->profile->expects($this->once())
      ->method('getProviderConfig')
      ->willReturn($provider_config);
    $user = $this->createMock('Drupal\user\UserInterface');
    $user->expects($this->exactly(3))
      ->method('getAccountName')
      ->willReturn('hpotter');
    $user->expects($this->once())
      ->method('isNew')
      ->willReturn(TRUE);
    $this->ldapDrupalUserProcessor->expects($this->once())
      ->method('excludeUser')
      ->with($user)
      ->willReturn(FALSE);

    $this->ldapGroupManager->expects($this->once())
      ->method('groupMembershipsFromUser')
      ->with('hpotter')
      ->willReturn(['students']);

    $result = $this->providerPlugin->getProposals($user);

    $this->assertIsArray($result);
    $this->assertCount(1, $result);
  }

  /**
   * Tests the getProposals() method.
   */
  public function testGetProposalsUserNoProposals(): void {
    $this->ldapUserManager->expects($this->once())
      ->method('getUserDataByAccount')
      ->willReturn(FALSE);
    $provider_config = [
      'status' => [
        'server' => 'server_a',
        'only_ldap_authenticated' => TRUE,
      ],
    ];
    $server_a = $this->createMock('Drupal\ldap_servers\Entity\Server');
    $server_a->expects($this->once())
      ->method('status')
      ->willReturn(TRUE);
    $this->storage->expects($this->once())
      ->method('load')
      ->with('server_a')
      ->willReturn($server_a);
    $this->profile->expects($this->once())
      ->method('getProviderConfig')
      ->willReturn($provider_config);
    $user = $this->createMock('Drupal\user\UserInterface');
    $user->expects($this->exactly(3))
      ->method('getAccountName')
      ->willReturn('hpotter');
    $user->expects($this->once())
      ->method('isNew')
      ->willReturn(TRUE);
    $this->ldapDrupalUserProcessor->expects($this->once())
      ->method('excludeUser')
      ->with($user)
      ->willReturn(FALSE);

    $this->ldapGroupManager->expects($this->once())
      ->method('groupMembershipsFromUser')
      ->with('hpotter')
      ->willReturn([]);

    $result = $this->providerPlugin->getProposals($user);

    $this->assertIsArray($result);
    $this->assertCount(0, $result);
  }

  /**
   * Tests the getProposals() method.
   */
  public function testGetProposalsUserOnlyLdapAuthentication(): void {
    $label = $this->createMock('\Drupal\Core\StringTranslation\TranslatableMarkup');
    $label->method('render')->willReturn('LDAP Authorization');
    $configuration = [
      'profile' => $this->profile,
      'status' => [
        'only_ldap_authenticated' => TRUE,
      ],
    ];
    $plugin_id = 'ldap_provider';
    $definition = [
      'id' => 'ldap_provider',
      'label' => $label,
    ];
    $container = \Drupal::getContainer();
    $provider_plugin = LDAPAuthorizationProvider::create($container, $configuration, $plugin_id, $definition);

    $this->ldapUserManager->expects($this->once())
      ->method('getUserDataByAccount')
      ->willReturn(FALSE);
    $provider_config = [
      'status' => [
        'server' => 'server_a',
        'only_ldap_authenticated' => TRUE,
      ],
    ];
    $server_a = $this->createMock('Drupal\ldap_servers\Entity\Server');
    $server_a->expects($this->once())
      ->method('status')
      ->willReturn(TRUE);
    $this->storage->expects($this->once())
      ->method('load')
      ->with('server_a')
      ->willReturn($server_a);
    $this->profile->expects($this->once())
      ->method('getProviderConfig')
      ->willReturn($provider_config);
    $user = $this->createMock('Drupal\user\UserInterface');
    $user->expects($this->once())
      ->method('getAccountName')
      ->willReturn('hpotter');
    $user->expects($this->once())
      ->method('isNew')
      ->willReturn(TRUE);
    $this->ldapDrupalUserProcessor->expects($this->once())
      ->method('excludeUser')
      ->with($user)
      ->willReturn(FALSE);

    $this->expectException(
      'Drupal\authorization\AuthorizationSkipAuthorization',
      'Not LDAP authenticated'
    );

    $provider_plugin->getProposals($user);
  }

  /**
   * Tests the getProposals() method.
   */
  public function testSanitizeProposals(): void {
    $input = [
      'students' => 'cn=students,ou=groups,dc=hogwarts,dc=edu',
      'users' => 'cn=users,ou=groups,dc=hogwarts,dc=edu',
    ];
    $provider_config = [
      'status' => [
        'server' => 'server_a',
      ],
      'filter_and_mappings' => [
        'use_first_attr_as_groupid' => TRUE,
      ],
    ];

    $this->profile->expects($this->once())
      ->method('getProviderConfig')
      ->willReturn($provider_config);

    $result = $this->providerPlugin->sanitizeProposals($input);

    $this->assertIsArray($result);
    $this->assertCount(2, $result);
    $this->assertArrayHasKey('students', $result);
    $this->assertEquals('students', $result['students']);
    $this->assertArrayHasKey('users', $result);
    $this->assertEquals('users', $result['users']);
  }

}
