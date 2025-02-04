<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_servers\Unit;

use Drupal\ldap_servers\Form\ServerTestForm;
use Drupal\ldap_servers\LdapBridge;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Ldap\Exception\LdapException;

/**
 * Class LdapBridgeTest.
 *
 * @group ldap_servers
 */
class ServerTestFormTest extends UnitTestCase {

  /**
   * The ldap bridge.
   *
   * @var \Drupal\ldap_servers\Form\ServerTestForm
   */
  protected $form;

  /**
   * The entity storage.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $entityStorage;

  /**
   * The server.
   *
   * @var \Drupal\ldap_servers\ServerInterface
   */
  protected $server;

  /**
   * The module handler.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $entityTypeManager;

  /**
   * The server storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $serverStorage;

  /**
   * The ldap bridge.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $ldapBridge;

  /**
   * The ldap group manager.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $ldapGroupManager;

  /**
   * The ldap token processor.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $ldapTokenProcessor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();

    $string_translation = $this->getStringTranslationStub();
    $container->set('string_translation', $string_translation);

    $this->entityTypeManager = $this->prophesize('Drupal\Core\Entity\EntityTypeManagerInterface');
    $container->set('entity_type.manager', $this->entityTypeManager->reveal());

    $this->entityStorage = $this->prophesize('Drupal\Core\Entity\EntityStorageInterface');
    $this->entityTypeManager->getStorage('ldap_server')->willReturn($this->entityStorage->reveal());

    $config_factory = $this->prophesize('Drupal\Core\Config\ConfigFactoryInterface');
    $container->set('config.factory', $config_factory->reveal());

    $this->moduleHandler = $this->createMock('Drupal\Core\Extension\ModuleHandlerInterface');
    $container->set('module_handler', $this->moduleHandler);

    $this->ldapTokenProcessor = $this->prophesize('Drupal\ldap_servers\Processor\TokenProcessor');
    $container->set('ldap.token_processor', $this->ldapTokenProcessor->reveal());

    $renderer = $this->prophesize('Drupal\Core\Render\Renderer');
    $container->set('renderer', $renderer->reveal());

    $this->ldapBridge = $this->prophesize(LdapBridge::class);
    $container->set('ldap.bridge', $this->ldapBridge->reveal());

    $this->ldapGroupManager = $this->prophesize('Drupal\ldap_servers\LdapGroupManager');
    $container->set('ldap.group_manager', $this->ldapGroupManager->reveal());

    $entity_type_repository = $this->prophesize('Drupal\Core\Entity\EntityTypeRepository');
    $container->set('entity_type.repository', $entity_type_repository->reveal());

    $this->serverStorage = $this->createMock('Drupal\Core\Config\Entity\ConfigEntityStorageInterface');
    $this->entityTypeManager->getStorage('ldap_server')->willReturn($this->serverStorage);

    \Drupal::setContainer($container);

    $this->form = ServerTestForm::create($container);
    $this->server = $this->createMock('Drupal\ldap_servers\Entity\Server');
  }

  /**
   * Test the getFormId method.
   */
  public function testGetFormId(): void {
    $this->assertEquals('ldap_servers_test_form', $this->form->getFormId());
  }

  /**
   * Test the buildForm method. The ldap_user module is not installed.
   */
  public function testBuildFormLdapUserModuleNotInstalled(): void {

    $this->server->expects($this->once())
      ->method('label')
      ->willReturn('Test Server');

    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form = $this->form->buildForm([], $form_state, $this->server);

    $this->assertIsArray($form);
    $this->assertCount(3, $form);
    $this->assertArrayHasKey('#title', $form);
    $this->assertArrayHasKey('#prefix', $form);
    $this->assertArrayHasKey('error', $form);
    $this->assertEquals('Test LDAP Server Configuration: Test Server', (string) $form['#title']);
    $this->assertStringContainsString('Enter identifiers here to query LDAP directly based on your server configuration. The only data this function will modify is the test LDAP group, which will be deleted and added', (string) $form['#prefix']);
    $this->assertEquals('<h3>This form requires ldap_user to function correctly, please enable it.</h3>', (string) $form['error']['#markup']);
  }

  /**
   * Test the buildForm method.
   *
   * The ldap_user module is installed. No test data is present.
   */
  public function testBuildFormNoTestData(): void {

    $this->server->expects($this->once())
      ->method('label')
      ->willReturn('Test Server');

    $this->server->expects($this->exactly(4))
      ->method('get')
      ->willReturn('user', 'hpotter', '', '');

    $this->moduleHandler->expects($this->once())
      ->method('moduleExists')
      ->with('ldap_user')
      ->willReturn(TRUE);

    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form = $this->form->buildForm([], $form_state, $this->server);

    $this->assertIsArray($form);
    $this->assertCount(10, $form);
    $this->assertArrayHasKey('#prefix', $form);
    $this->assertArrayHasKey('#title', $form);
    $this->assertArrayHasKey('grp_test_grp_dn_writeable', $form);
    $this->assertArrayHasKey('grp_test_grp_dn', $form);
    $this->assertArrayHasKey('id', $form);
    $this->assertArrayHasKey('server_variables', $form);
    $this->assertArrayHasKey('submit', $form);
    $this->assertArrayHasKey('testing_drupal_user_dn', $form);
    $this->assertArrayHasKey('testing_drupal_username', $form);
    $this->assertEquals('Test LDAP Server Configuration: Test Server', (string) $form['#title']);
    $this->assertStringContainsString('Enter identifiers here to query LDAP directly based on your server configuration. The only data this function will modify is the test LDAP group, which will be deleted and added', (string) $form['#prefix']);
  }

  /**
   * Test the buildForm method.
   *
   * The ldap_user module is installed and test data is present.
   */
  public function testBuildFormWithTestData(): void {
    $user = $this->createMock('Drupal\user\Entity\User');

    $user_storage = $this->createMock('Drupal\user\UserStorageInterface');
    $user_storage->expects($this->once())
      ->method('loadByProperties')
      ->with(['name' => 'hpotter'])
      ->willReturn([$user]);
    $this->entityTypeManager->getStorage('user')
      ->willReturn($user_storage)
      ->shouldBeCalled($this->once());

    $ldap_user = $this->createMock('Symfony\Component\Ldap\Entry');
    $ldap_user->expects($this->once())
      ->method('getAttributes')
      ->willReturn([
        'attribute1' => [
          'ou=people,dc=hogwarts,dc=edu',
          'ou=students,dc=hogwarts,dc=edu',
          'ou=gryffindor,dc=hogwarts,dc=edu',
        ],
      ]);
    $ldap_user->expects($this->once())
      ->method('getDn')
      ->willReturn('cn=hpotter,ou=people,dc=example,dc=com');

    $this->server->expects($this->once())
      ->method('label')
      ->willReturn('Test Server');

    $this->server->expects($this->exactly(4))
      ->method('get')
      ->willReturn('user', 'hpotter', '', '');

    $this->moduleHandler->expects($this->once())
      ->method('moduleExists')
      ->with('ldap_user')
      ->willReturn(TRUE);

    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->exactly(2))
      ->method('get')
      ->with(['ldap_server_test_data'])
      ->willReturn([
        'username' => 'hpotter',
        'group_entry' => [
          'ou=people,dc=hogwarts,dc=edu',
          'ou=students,dc=hogwarts,dc=edu',
          'ou=gryffindor,ou=students,dc=hogwarts,dc=edu',
        ],
        'ldap_user' => $ldap_user,
        'results_tables' => [
          'basic' => [
            'dn' => 'cn=hpotter,ou=people,dc=example,dc=com',
            'cn' => 'hpotter',
            'description' => 'Harry Potter',
          ],
        ],
      ]);

    $form = $this->form->buildForm([], $form_state, $this->server);

    $this->assertIsArray($form);
    $this->assertCount(11, $form);
    $this->assertArrayHasKey('#prefix', $form);
    $this->assertArrayHasKey('#suffix', $form);
    $this->assertArrayHasKey('#title', $form);
    $this->assertArrayHasKey('grp_test_grp_dn_writeable', $form);
    $this->assertArrayHasKey('grp_test_grp_dn', $form);
    $this->assertArrayHasKey('id', $form);
    $this->assertArrayHasKey('server_variables', $form);
    $this->assertArrayHasKey('submit', $form);
    $this->assertArrayHasKey('testing_drupal_user_dn', $form);
    $this->assertArrayHasKey('testing_drupal_username', $form);
    $this->assertEquals('Test LDAP Server Configuration: Test Server', (string) $form['#title']);
    $this->assertStringContainsString('Enter identifiers here to query LDAP directly based on your server configuration. The only data this function will modify is the test LDAP group, which will be deleted and added', (string) $form['#prefix']);
  }

  /**
   * Test the buildForm method. One attribute.
   */
  public function testBuildFormWithOneAttribute(): void {
    $user = $this->createMock('Drupal\user\Entity\User');

    $user_storage = $this->createMock('Drupal\user\UserStorageInterface');
    $user_storage->expects($this->once())
      ->method('loadByProperties')
      ->with(['name' => 'hpotter'])
      ->willReturn([$user]);
    $this->entityTypeManager->getStorage('user')
      ->willReturn($user_storage)
      ->shouldBeCalled($this->once());

    $ldap_user = $this->createMock('Symfony\Component\Ldap\Entry');
    $ldap_user->expects($this->once())
      ->method('getAttributes')
      ->willReturn([
        'attribute1' => [
          'membership' => 'ou=people,dc=hogwarts,dc=edu',
        ],
      ]);
    $ldap_user->expects($this->once())
      ->method('getDn')
      ->willReturn('cn=hpotter,ou=people,dc=example,dc=com');

    $this->server->expects($this->once())
      ->method('label')
      ->willReturn('Test Server');

    $this->server->expects($this->exactly(4))
      ->method('get')
      ->willReturn('user', 'hpotter', '', '');

    $this->moduleHandler->expects($this->once())
      ->method('moduleExists')
      ->with('ldap_user')
      ->willReturn(TRUE);

    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->exactly(2))
      ->method('get')
      ->with(['ldap_server_test_data'])
      ->willReturn([
        'username' => 'hpotter',
        'group_entry' => [
          'ou=people,dc=hogwarts,dc=edu',
          'ou=students,dc=hogwarts,dc=edu',
          'ou=gryffindor,ou=students,dc=hogwarts,dc=edu',
        ],
        'ldap_user' => $ldap_user,
        'results_tables' => [
          'basic' => [
            'dn' => 'cn=hpotter,ou=people,dc=example,dc=com',
            'cn' => 'hpotter',
            'description' => 'Harry Potter',
          ],
        ],
      ]);

    $form = $this->form->buildForm([], $form_state, $this->server);

    $this->assertIsArray($form);
    $this->assertCount(11, $form);
    $this->assertArrayHasKey('#prefix', $form);
    $this->assertArrayHasKey('#suffix', $form);
    $this->assertArrayHasKey('#title', $form);
    $this->assertArrayHasKey('grp_test_grp_dn_writeable', $form);
    $this->assertArrayHasKey('grp_test_grp_dn', $form);
    $this->assertArrayHasKey('id', $form);
    $this->assertArrayHasKey('server_variables', $form);
    $this->assertArrayHasKey('submit', $form);
    $this->assertArrayHasKey('testing_drupal_user_dn', $form);
    $this->assertArrayHasKey('testing_drupal_username', $form);
    $this->assertEquals('Test LDAP Server Configuration: Test Server', (string) $form['#title']);
    $this->assertStringContainsString('Enter identifiers here to query LDAP directly based on your server configuration. The only data this function will modify is the test LDAP group, which will be deleted and added', (string) $form['#prefix']);
  }

  /**
   * Test the buildForm method. Multiple attributes.
   */
  public function testBuildFormWithMultipleAttribute(): void {
    $user = $this->createMock('Drupal\user\Entity\User');

    $user_storage = $this->createMock('Drupal\user\UserStorageInterface');
    $user_storage->expects($this->once())
      ->method('loadByProperties')
      ->with(['name' => 'hpotter'])
      ->willReturn([$user]);
    $this->entityTypeManager->getStorage('user')
      ->willReturn($user_storage)
      ->shouldBeCalled($this->once());

    $ldap_user = $this->createMock('Symfony\Component\Ldap\Entry');
    $ldap_user->expects($this->once())
      ->method('getAttributes')
      ->willReturn([
        'attribute1' => [
          'ou=people,dc=hogwarts,dc=edu',
          'ou=students,dc=hogwarts,dc=edu',
          'ou=gryffindor,dc=hogwarts,dc=edu',
        ],
      ]);
    $ldap_user->expects($this->once())
      ->method('getDn')
      ->willReturn('cn=hpotter,ou=people,dc=example,dc=com');

    $this->server->expects($this->once())
      ->method('label')
      ->willReturn('Test Server');

    $this->server->expects($this->exactly(4))
      ->method('get')
      ->willReturn('user', 'hpotter', '', '');

    $this->moduleHandler->expects($this->once())
      ->method('moduleExists')
      ->with('ldap_user')
      ->willReturn(TRUE);

    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->exactly(2))
      ->method('get')
      ->with(['ldap_server_test_data'])
      ->willReturn([
        'username' => 'hpotter',
        'group_entry' => [
          'ou=people,dc=hogwarts,dc=edu',
          'ou=students,dc=hogwarts,dc=edu',
          'ou=gryffindor,ou=students,dc=hogwarts,dc=edu',
        ],
        'ldap_user' => $ldap_user,
        'results_tables' => [
          'basic' => [
            'dn' => 'cn=hpotter,ou=people,dc=example,dc=com',
            'cn' => 'hpotter',
            'description' => 'Harry Potter',
          ],
        ],
      ]);

    $form = $this->form->buildForm([], $form_state, $this->server);

    $this->assertIsArray($form);
    $this->assertCount(11, $form);
    $this->assertArrayHasKey('#prefix', $form);
    $this->assertArrayHasKey('#suffix', $form);
    $this->assertArrayHasKey('#title', $form);
    $this->assertArrayHasKey('grp_test_grp_dn_writeable', $form);
    $this->assertArrayHasKey('grp_test_grp_dn', $form);
    $this->assertArrayHasKey('id', $form);
    $this->assertArrayHasKey('server_variables', $form);
    $this->assertArrayHasKey('submit', $form);
    $this->assertArrayHasKey('testing_drupal_user_dn', $form);
    $this->assertArrayHasKey('testing_drupal_username', $form);
    $this->assertEquals('Test LDAP Server Configuration: Test Server', (string) $form['#title']);
    $this->assertStringContainsString('Enter identifiers here to query LDAP directly based on your server configuration. The only data this function will modify is the test LDAP group, which will be deleted and added', (string) $form['#prefix']);
  }

  /**
   * Test the validateForm method when the server is invalid.
   */
  public function testValidateFormWithoutServerId(): void {
    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->once())
      ->method('getValues')
      ->willReturn(['id' => '']);
    $form_state->expects($this->once())
      ->method('setErrorByName');
    $this->form->validateForm($form, $form_state);
  }

  /**
   * Tests validateForm when the server is invalid.
   */
  public function testValidateFormServerIsInvalid(): void {
    $this->serverStorage->expects($this->once())
      ->method('load')
      ->with('example_server')
      ->willReturn(NULL);
    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->once())
      ->method('getValues')
      ->willReturn(['id' => 'example_server']);
    $form_state->expects($this->once())
      ->method('setErrorByName');
    $this->form->validateForm($form, $form_state);
  }

  /**
   * Test the submitForm method. Bind success.
   */
  public function testSubmitFormBindSuccess(): void {
    $this->server->expects($this->exactly(2))
      ->method('id')
      ->willReturn('example_server');
    $this->server->expects($this->exactly(2))
      ->method('get')
      ->with('grp_object_cat')
      ->willReturn('NotOpenLDAP');
    $this->serverStorage->expects($this->once())
      ->method('load')
      ->with('example_server')
      ->willReturn($this->server);

    $this->ldapBridge->setServer($this->server)
      ->shouldBeCalled($this->once());
    $this->ldapBridge->bind()
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());

    $group_entry = $this->createMock('Symfony\Component\Ldap\Adapter\CollectionInterface');
    $group_entry->expects($this->once())
      ->method('toArray')
      ->willReturn([]);

    $ldap_query = $this->createMock('Symfony\Component\Ldap\Adapter\QueryInterface');
    $ldap_query->expects($this->once())
      ->method('execute')
      ->willReturn($group_entry);

    $ldap = $this->createMock('Symfony\Component\Ldap\LdapInterface');
    $ldap->expects($this->once())
      ->method('query')
      ->willReturn($ldap_query);

    $this->ldapBridge->get()
      ->willReturn($ldap)
      ->shouldBeCalled($this->once());
    $ldap_entry = $this->createMock('Symfony\Component\Ldap\Entry');
    $ldap_entry->expects($this->once())
      ->method('getAttributes')
      ->willReturn([
        'attribute1',
        'attribute2',
        'attribute3',
      ]);
    $ldap_entry->expects($this->once())
      ->method('getDn')
      ->willReturn('cn=hpotter,ou=people,dc=example,dc=com');

    $this->ldapGroupManager->setServerById('example_server')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->matchUsernameToExistingLdapEntry('hpotter')
      ->willReturn($ldap_entry)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupUserMembershipsFromDn('hpotter')
      ->willReturn([
        'ou=people,dc=hogwarts,dc=edu',
        'ou=students,dc=hogwarts,dc=edu',
        'ou=gryffindor,ou=students,dc=hogwarts,dc=edu',
      ])
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupAllMembers('ou=people,dc=hogwarts,dc=edu')
      ->willReturn([
        'hpotter',
        'hgranger',
      ])
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->checkDnExists('ou=room of requirements,dc=hogwarts,dc=edu')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupRemoveGroup('ou=room of requirements,dc=hogwarts,dc=edu', FALSE)
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupRemoveGroup('ou=room of requirements,dc=hogwarts,dc=edu')
      ->willReturn(FALSE, TRUE)
      ->shouldBeCalled($this->exactly(2));

    $this->ldapGroupManager->groupAddGroup('ou=room of requirements,dc=hogwarts,dc=edu', Argument::type('array'))
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupMembers('ou=room of requirements,dc=hogwarts,dc=edu')
      ->willReturn([])
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupAddMember('ou=room of requirements,dc=hogwarts,dc=edu', 'cn=hpotter,ou=people,dc=example,dc=com')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupRemoveMember('ou=room of requirements,dc=hogwarts,dc=edu', 'cn=hpotter,ou=people,dc=example,dc=com')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());

    $this->ldapTokenProcessor->getTokens()
      ->willReturn([
        'testing_drupal_username' => 'hpotter',
      ])
      ->shouldBeCalled($this->once());
    $this->ldapTokenProcessor->ldapEntryReplacementsForDrupalAccount($ldap_entry, '[0][1][2]')
      ->shouldBeCalled($this->once());

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->once())
      ->method('setRebuild');
    $form_state->expects($this->once())
      ->method('getValues')
      ->willReturn([
        'id' => 'example_server',
        'testing_drupal_username' => 'hpotter',
        'grp_test_grp_dn' => 'ou=people,dc=hogwarts,dc=edu',
        'grp_test_grp_dn_writeable' => 'ou=room of requirements,dc=hogwarts,dc=edu',
      ]);

    $this->form->submitForm($form, $form_state);
  }

  /**
   * Test the submitForm method. Bind failure.
   */
  public function testSubmitFormBindFailure(): void {
    $this->server->expects($this->exactly(2))
      ->method('id')
      ->willReturn('example_server');
    $this->server->expects($this->exactly(2))
      ->method('get')
      ->with('grp_object_cat')
      ->willReturn('NotOpenLDAP');
    $this->serverStorage->expects($this->once())
      ->method('load')
      ->with('example_server')
      ->willReturn($this->server);

    $this->ldapBridge->setServer($this->server)
      ->shouldBeCalled($this->once());
    $this->ldapBridge->bind()
      ->willReturn(FALSE)
      ->shouldBeCalled($this->once());

    $ldap_entry = $this->createMock('Symfony\Component\Ldap\Entry');
    $ldap_entry->expects($this->once())
      ->method('getAttributes')
      ->willReturn([
        'attribute1',
        'attribute2',
        'attribute3',
      ]);
    $ldap_entry->expects($this->once())
      ->method('getDn')
      ->willReturn('cn=hpotter,ou=people,dc=example,dc=com');

    $this->ldapGroupManager->setServerById('example_server')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->matchUsernameToExistingLdapEntry('hpotter')
      ->willReturn($ldap_entry)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->checkDnExists('ou=room of requirements,dc=hogwarts,dc=edu')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupRemoveGroup('ou=room of requirements,dc=hogwarts,dc=edu', FALSE)
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupRemoveGroup('ou=room of requirements,dc=hogwarts,dc=edu')
      ->willReturn(FALSE, TRUE)
      ->shouldBeCalled($this->exactly(2));

    $this->ldapGroupManager->groupAddGroup('ou=room of requirements,dc=hogwarts,dc=edu', Argument::type('array'))
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupMembers('ou=room of requirements,dc=hogwarts,dc=edu')
      ->willReturn([])
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupAddMember('ou=room of requirements,dc=hogwarts,dc=edu', 'cn=hpotter,ou=people,dc=example,dc=com')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupRemoveMember('ou=room of requirements,dc=hogwarts,dc=edu', 'cn=hpotter,ou=people,dc=example,dc=com')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());

    $this->ldapTokenProcessor->getTokens()
      ->willReturn([
        'testing_drupal_username' => 'hpotter',
      ])
      ->shouldBeCalled($this->once());
    $this->ldapTokenProcessor->ldapEntryReplacementsForDrupalAccount($ldap_entry, '[0][1][2]')
      ->shouldBeCalled($this->once());

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->once())
      ->method('setRebuild');
    $form_state->expects($this->once())
      ->method('getValues')
      ->willReturn([
        'id' => 'example_server',
        'testing_drupal_username' => 'hpotter',
        'grp_test_grp_dn' => 'ou=people,dc=hogwarts,dc=edu',
        'grp_test_grp_dn_writeable' => 'ou=room of requirements,dc=hogwarts,dc=edu',
      ]);

    $this->form->submitForm($form, $form_state);
  }

  /**
   * Test the submitForm method. OpenLDAP.
   */
  public function testSubmitFormOpenLdap(): void {
    $this->server->expects($this->exactly(2))
      ->method('id')
      ->willReturn('example_server');
    $this->server->expects($this->exactly(2))
      ->method('get')
      ->with('grp_object_cat')
      ->willReturn('groupofnames');
    $this->serverStorage->expects($this->once())
      ->method('load')
      ->with('example_server')
      ->willReturn($this->server);

    $this->ldapBridge->setServer($this->server)
      ->shouldBeCalled($this->once());
    $this->ldapBridge->bind()
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());

    $group_entry = $this->createMock('Symfony\Component\Ldap\Adapter\CollectionInterface');
    $group_entry->expects($this->once())
      ->method('toArray')
      ->willReturn([]);

    $ldap_query = $this->createMock('Symfony\Component\Ldap\Adapter\QueryInterface');
    $ldap_query->expects($this->once())
      ->method('execute')
      ->willReturn($group_entry);

    $ldap = $this->createMock('Symfony\Component\Ldap\LdapInterface');
    $ldap->expects($this->once())
      ->method('query')
      ->willReturn($ldap_query);
    $this->ldapBridge->get()
      ->willReturn($ldap)
      ->shouldBeCalled($this->once());
    $ldap_entry = $this->createMock('Symfony\Component\Ldap\Entry');
    $ldap_entry->expects($this->once())
      ->method('getAttributes')
      ->willReturn([
        'attribute1',
        'attribute2',
        'attribute3',
      ]);
    $ldap_entry->expects($this->once())
      ->method('getDn')
      ->willReturn('cn=hpotter,ou=people,dc=example,dc=com');
    $this->ldapGroupManager->setServerById('example_server')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->matchUsernameToExistingLdapEntry('hpotter')
      ->willReturn($ldap_entry)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupUserMembershipsFromDn('hpotter')
      ->willReturn([
        'ou=people,dc=hogwarts,dc=edu',
        'ou=students,dc=hogwarts,dc=edu',
        'ou=gryffindor,ou=students,dc=hogwarts,dc=edu',
      ])
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupAllMembers('ou=people,dc=hogwarts,dc=edu')
      ->willReturn([
        'hpotter',
        'hgranger',
      ])
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->checkDnExists('ou=room of requirements,dc=hogwarts,dc=edu')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupRemoveGroup('ou=room of requirements,dc=hogwarts,dc=edu', FALSE)
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupRemoveGroup('ou=room of requirements,dc=hogwarts,dc=edu')
      ->willReturn(FALSE, TRUE)
      ->shouldBeCalled($this->exactly(2));

    $this->ldapGroupManager->groupAddGroup('ou=room of requirements,dc=hogwarts,dc=edu', Argument::type('array'))
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupMembers('ou=room of requirements,dc=hogwarts,dc=edu')
      ->willReturn([])
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupAddMember('ou=room of requirements,dc=hogwarts,dc=edu', 'cn=hpotter,ou=people,dc=example,dc=com')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupRemoveMember('ou=room of requirements,dc=hogwarts,dc=edu', 'cn=hpotter,ou=people,dc=example,dc=com')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());

    $this->ldapTokenProcessor->getTokens()
      ->willReturn([
        'testing_drupal_username' => 'hpotter',
      ])
      ->shouldBeCalled($this->once());
    $this->ldapTokenProcessor->ldapEntryReplacementsForDrupalAccount($ldap_entry, '[0][1][2]')
      ->shouldBeCalled($this->once());

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->once())
      ->method('setRebuild');
    $form_state->expects($this->once())
      ->method('getValues')
      ->willReturn([
        'id' => 'example_server',
        'testing_drupal_username' => 'hpotter',
        'grp_test_grp_dn' => 'ou=people,dc=hogwarts,dc=edu',
        'grp_test_grp_dn_writeable' => 'ou=room of requirements,dc=hogwarts,dc=edu',
      ]);

    $this->form->submitForm($form, $form_state);
  }

  /**
   * Test the submitForm method. OpenLDAP.
   */
  public function testSubmitFormUnableToSetServerById(): void {
    $this->server->expects($this->exactly(2))
      ->method('id')
      ->willReturn('example_server');

    $this->serverStorage->expects($this->once())
      ->method('load')
      ->with('example_server')
      ->willReturn($this->server);

    $this->ldapBridge->setServer($this->server)
      ->shouldBeCalled($this->once());
    $this->ldapBridge->bind()
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());

    $group_entry = $this->createMock('Symfony\Component\Ldap\Adapter\CollectionInterface');
    $group_entry->expects($this->once())
      ->method('toArray')
      ->willReturn([]);

    $ldap_query = $this->createMock('Symfony\Component\Ldap\Adapter\QueryInterface');
    $ldap_query->expects($this->once())
      ->method('execute')
      ->willReturn($group_entry);

    $ldap = $this->createMock('Symfony\Component\Ldap\LdapInterface');
    $ldap->expects($this->once())
      ->method('query')
      ->willReturn($ldap_query);
    $this->ldapBridge->get()
      ->willReturn($ldap)
      ->shouldBeCalled($this->once());
    $ldap_entry = $this->createMock('Symfony\Component\Ldap\Entry');
    $ldap_entry->expects($this->once())
      ->method('getDn')
      ->willReturn('cn=hpotter,ou=people,dc=example,dc=com');
    $ldap_entry->expects($this->once())
      ->method('getAttributes')
      ->willReturn([
        'attribute1' => 'attribute1',
        'attribute2' => 'attribute2',
        'attribute3' => 'attribute3',
      ]);
    $this->ldapGroupManager->setServerById('example_server')
      ->willReturn(FALSE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->matchUsernameToExistingLdapEntry('hpotter')
      ->willReturn($ldap_entry)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupUserMembershipsFromDn('hpotter')
      ->willReturn([
        'ou=people,dc=hogwarts,dc=edu',
        'ou=students,dc=hogwarts,dc=edu',
        'ou=gryffindor,ou=students,dc=hogwarts,dc=edu',
      ])
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupAllMembers('ou=people,dc=hogwarts,dc=edu')
      ->willReturn([
        'hpotter',
        'hgranger',
      ])
      ->shouldBeCalled($this->once());

    $this->ldapTokenProcessor->getTokens()
      ->willReturn([
        'testing_drupal_username' => 'hpotter',
      ])
      ->shouldBeCalled($this->once());
    $this->ldapTokenProcessor->ldapEntryReplacementsForDrupalAccount($ldap_entry, '[attribute1][attribute2][attribute3]')
      ->shouldBeCalled($this->once());

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->once())
      ->method('setRebuild');
    $form_state->expects($this->once())
      ->method('getValues')
      ->willReturn([
        'id' => 'example_server',
        'testing_drupal_username' => 'hpotter',
        'grp_test_grp_dn' => 'ou=people,dc=hogwarts,dc=edu',
        'grp_test_grp_dn_writeable' => 'ou=room of requirements,dc=hogwarts,dc=edu',
      ]);

    $this->form->submitForm($form, $form_state);
  }

  /**
   * Test the submitForm method. Unable to match username to existing entry.
   */
  public function testSubmitFormNoExistingLdapEntry(): void {
    $this->server->expects($this->once())
      ->method('id')
      ->willReturn('example_server');

    $this->serverStorage->expects($this->once())
      ->method('load')
      ->with('example_server')
      ->willReturn($this->server);

    $this->ldapBridge->setServer($this->server)
      ->shouldBeCalled($this->once());
    $this->ldapBridge->bind()
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());

    $ldap = $this->createMock('Symfony\Component\Ldap\LdapInterface');

    $ldap_entry = $this->createMock('Symfony\Component\Ldap\Entry');

    $this->ldapGroupManager->setServerById('example_server')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->matchUsernameToExistingLdapEntry('hpotter')
      ->willReturn(FALSE)
      ->shouldBeCalled($this->once());

    $this->ldapTokenProcessor->getTokens()
      ->willReturn([
        'testing_drupal_username' => 'hpotter',
      ])
      ->shouldBeCalled($this->once());

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->once())
      ->method('setRebuild');
    $form_state->expects($this->once())
      ->method('getValues')
      ->willReturn([
        'id' => 'example_server',
        'testing_drupal_username' => 'hpotter',
        'grp_test_grp_dn' => 'ou=people,dc=hogwarts,dc=edu',
        'grp_test_grp_dn_writeable' => 'ou=room of requirements,dc=hogwarts,dc=edu',
      ]);

    $this->form->submitForm($form, $form_state);
  }

  /**
   * Test the  method. groupDn throws an exception.
   */
  public function testSubmitFormGroupDnException(): void {
    $this->server->expects($this->exactly(2))
      ->method('id')
      ->willReturn('example_server');
    $this->server->expects($this->exactly(2))
      ->method('get')
      ->with('grp_object_cat')
      ->willReturn('NotOpenLDAP');
    $this->serverStorage->expects($this->once())
      ->method('load')
      ->with('example_server')
      ->willReturn($this->server);

    $this->ldapBridge->setServer($this->server)
      ->shouldBeCalled($this->once());
    $this->ldapBridge->bind()
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());

    $ldap = $this->createMock('Symfony\Component\Ldap\LdapInterface');
    $ldap->expects($this->once())
      ->method('query')
      ->willThrowException(new LdapException());
    $this->ldapBridge->get()
      ->willReturn($ldap)
      ->shouldBeCalled($this->once());
    $ldap_entry = $this->createMock('Symfony\Component\Ldap\Entry');
    $ldap_entry->expects($this->once())
      ->method('getAttributes')
      ->willReturn([
        'attribute1',
        'attribute2',
        'attribute3',
      ]);
    $ldap_entry->expects($this->once())
      ->method('getDn')
      ->willReturn('cn=hpotter,ou=people,dc=example,dc=com');

    $this->ldapGroupManager->setServerById('example_server')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->matchUsernameToExistingLdapEntry('hpotter')
      ->willReturn($ldap_entry)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupUserMembershipsFromDn('hpotter')
      ->willReturn([
        'ou=people,dc=hogwarts,dc=edu',
        'ou=students,dc=hogwarts,dc=edu',
        'ou=gryffindor,ou=students,dc=hogwarts,dc=edu',
      ])
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupAllMembers('ou=people,dc=hogwarts,dc=edu')
      ->willReturn([
        'hpotter',
        'hgranger',
      ])
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->checkDnExists('ou=room of requirements,dc=hogwarts,dc=edu')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupRemoveGroup('ou=room of requirements,dc=hogwarts,dc=edu', FALSE)
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupRemoveGroup('ou=room of requirements,dc=hogwarts,dc=edu')
      ->willReturn(FALSE, TRUE)
      ->shouldBeCalled($this->exactly(2));

    $this->ldapGroupManager->groupAddGroup('ou=room of requirements,dc=hogwarts,dc=edu', Argument::type('array'))
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupMembers('ou=room of requirements,dc=hogwarts,dc=edu')
      ->willReturn([])
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupAddMember('ou=room of requirements,dc=hogwarts,dc=edu', 'cn=hpotter,ou=people,dc=example,dc=com')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupRemoveMember('ou=room of requirements,dc=hogwarts,dc=edu', 'cn=hpotter,ou=people,dc=example,dc=com')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());

    $this->ldapTokenProcessor->getTokens()
      ->willReturn([
        'testing_drupal_username' => 'hpotter',
      ])
      ->shouldBeCalled($this->once());
    $this->ldapTokenProcessor->ldapEntryReplacementsForDrupalAccount($ldap_entry, '[0][1][2]')
      ->shouldBeCalled($this->once());

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->once())
      ->method('setRebuild');
    $form_state->expects($this->once())
      ->method('getValues')
      ->willReturn([
        'id' => 'example_server',
        'testing_drupal_username' => 'hpotter',
        'grp_test_grp_dn' => 'ou=people,dc=hogwarts,dc=edu',
        'grp_test_grp_dn_writeable' => 'ou=room of requirements,dc=hogwarts,dc=edu',
      ]);

    $this->form->submitForm($form, $form_state);
  }

  /**
   * Test the submitForm method. testGroupEntry has an entry.
   */
  public function testSubmitFormGroupEntry(): void {
    $this->server->expects($this->exactly(4))
      ->method('id')
      ->willReturn('example_server');
    $this->server->expects($this->exactly(2))
      ->method('get')
      ->with('grp_object_cat')
      ->willReturn('NotOpenLDAP');
    $this->serverStorage->expects($this->once())
      ->method('load')
      ->with('example_server')
      ->willReturn($this->server);

    $this->ldapBridge->setServer($this->server)
      ->shouldBeCalled($this->once());
    $this->ldapBridge->bind()
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $group_entry = $this->createMock('Symfony\Component\Ldap\Adapter\CollectionInterface');
    $group_entry->expects($this->once())
      ->method('toArray')
      ->willReturn([
        'dn' => 'ou=room of requirements,dc=hogwarts,dc=edu',
        'cn' => 'Room of Requirements',
        'description' => 'A room that only appears when someone is in need.',
        'member' => [
          'hpotter',
          'hgranger',
        ],
      ]);

    $ldap_query = $this->createMock('Symfony\Component\Ldap\Adapter\QueryInterface');
    $ldap_query->expects($this->once())
      ->method('execute')
      ->willReturn($group_entry);

    $ldap = $this->createMock('Symfony\Component\Ldap\LdapInterface');
    $ldap->expects($this->once())
      ->method('query')
      ->willReturn($ldap_query);

    $this->ldapBridge->get()
      ->willReturn($ldap)
      ->shouldBeCalled($this->once());
    $ldap_entry = $this->createMock('Symfony\Component\Ldap\Entry');
    $ldap_entry->expects($this->once())
      ->method('getAttributes')
      ->willReturn([
        'attribute1',
        'attribute2',
        'attribute3',
      ]);
    $ldap_entry->expects($this->once())
      ->method('getDn')
      ->willReturn('cn=hpotter,ou=people,dc=example,dc=com');
    $this->ldapGroupManager->setServerById('example_server')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->matchUsernameToExistingLdapEntry('hpotter')
      ->willReturn($ldap_entry)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupUserMembershipsFromDn('hpotter')
      ->willReturn([
        'ou=people,dc=hogwarts,dc=edu',
        'ou=students,dc=hogwarts,dc=edu',
        'ou=gryffindor,ou=students,dc=hogwarts,dc=edu',
      ])
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupAllMembers('ou=people,dc=hogwarts,dc=edu')
      ->willReturn([
        'hpotter',
        'hgranger',
      ])
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->checkDnExists('ou=room of requirements,dc=hogwarts,dc=edu')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupRemoveGroup('ou=room of requirements,dc=hogwarts,dc=edu', FALSE)
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupRemoveGroup('ou=room of requirements,dc=hogwarts,dc=edu')
      ->willReturn(FALSE, TRUE)
      ->shouldBeCalled($this->exactly(2));

    $this->ldapGroupManager->groupAddGroup('ou=room of requirements,dc=hogwarts,dc=edu', Argument::type('array'))
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupMembers('ou=room of requirements,dc=hogwarts,dc=edu')
      ->willReturn([])
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupAddMember('ou=room of requirements,dc=hogwarts,dc=edu', 'cn=hpotter,ou=people,dc=example,dc=com')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupRemoveMember('ou=room of requirements,dc=hogwarts,dc=edu', 'cn=hpotter,ou=people,dc=example,dc=com')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupMembershipsFromUser('hpotter')
      ->willReturn([
        'ou=people,dc=hogwarts,dc=edu',
        'ou=students,dc=hogwarts,dc=edu',
        'ou=gryffindor,ou=students,dc=hogwarts,dc=edu',
      ])
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupIsMember('ou=people,dc=hogwarts,dc=edu', 'hpotter')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupGroupEntryMembershipsConfigured()
      ->willReturn(FALSE);

    $this->ldapTokenProcessor->getTokens()
      ->willReturn([
        'testing_drupal_username' => 'hpotter',
      ])
      ->shouldBeCalled($this->once());
    $this->ldapTokenProcessor->ldapEntryReplacementsForDrupalAccount($ldap_entry, '[0][1][2]')
      ->shouldBeCalled($this->once());

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->once())
      ->method('setRebuild');
    $form_state->expects($this->once())
      ->method('getValues')
      ->willReturn([
        'id' => 'example_server',
        'testing_drupal_username' => 'hpotter',
        'grp_test_grp_dn' => 'ou=people,dc=hogwarts,dc=edu',
        'grp_test_grp_dn_writeable' => 'ou=room of requirements,dc=hogwarts,dc=edu',
      ]);

    $this->form->submitForm($form, $form_state);
  }

  /**
   * Test the submitForm method. has multiple memberships.
   */
  public function testSubmitFormGroupEntryMultipleMemberships(): void {
    $this->server->expects($this->exactly(4))
      ->method('id')
      ->willReturn('example_server');
    $this->server->expects($this->exactly(2))
      ->method('get')
      ->with('grp_object_cat')
      ->willReturn('NotOpenLDAP');
    $this->server->expects($this->exactly(2))
      ->method('isGroupUserMembershipAttributeInUse')
      ->willReturn(TRUE);
    $this->serverStorage->expects($this->once())
      ->method('load')
      ->with('example_server')
      ->willReturn($this->server);

    $this->ldapBridge->setServer($this->server)
      ->shouldBeCalled($this->once());
    $this->ldapBridge->bind()
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $group_entry = $this->createMock('Symfony\Component\Ldap\Adapter\CollectionInterface');
    $group_entry->expects($this->once())
      ->method('toArray')
      ->willReturn([
        'dn' => 'ou=room of requirements,dc=hogwarts,dc=edu',
        'cn' => 'Room of Requirements',
        'description' => 'A room that only appears when someone is in need.',
        'member' => [
          'hpotter',
          'hgranger',
        ],
      ]);

    $ldap_query = $this->createMock('Symfony\Component\Ldap\Adapter\QueryInterface');
    $ldap = $this->createMock('Symfony\Component\Ldap\LdapInterface');
    $ldap->expects($this->once())
      ->method('query')
      ->willReturn($ldap_query);
    $ldap_query->expects($this->once())
      ->method('execute')
      ->willReturn($group_entry);
    $this->ldapBridge->get()
      ->willReturn($ldap)
      ->shouldBeCalled($this->once());
    $ldap_entry = $this->createMock('Symfony\Component\Ldap\Entry');
    $ldap_entry->expects($this->once())
      ->method('getAttributes')
      ->willReturn([
        'attribute1',
        'attribute2',
        'attribute3',
      ]);
    $ldap_entry->expects($this->once())
      ->method('getDn')
      ->willReturn('cn=hpotter,ou=people,dc=example,dc=com');

    $this->ldapGroupManager->setServerById('example_server')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->matchUsernameToExistingLdapEntry('hpotter')
      ->willReturn($ldap_entry)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupUserMembershipsFromDn('hpotter')
      ->willReturn([
        'ou=people,dc=hogwarts,dc=edu',
        'ou=students,dc=hogwarts,dc=edu',
        'ou=gryffindor,ou=students,dc=hogwarts,dc=edu',
      ])
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupAllMembers('ou=people,dc=hogwarts,dc=edu')
      ->willReturn([
        'hpotter',
        'hgranger',
      ])
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->checkDnExists('ou=room of requirements,dc=hogwarts,dc=edu')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupRemoveGroup('ou=room of requirements,dc=hogwarts,dc=edu', FALSE)
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupRemoveGroup('ou=room of requirements,dc=hogwarts,dc=edu')
      ->willReturn(FALSE, TRUE)
      ->shouldBeCalled($this->exactly(2));
    $this->ldapGroupManager->groupAddGroup('ou=room of requirements,dc=hogwarts,dc=edu', Argument::type('array'))
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupMembers('ou=room of requirements,dc=hogwarts,dc=edu')
      ->willReturn([])
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupAddMember('ou=room of requirements,dc=hogwarts,dc=edu', 'cn=hpotter,ou=people,dc=example,dc=com')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupRemoveMember('ou=room of requirements,dc=hogwarts,dc=edu', 'cn=hpotter,ou=people,dc=example,dc=com')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupMembershipsFromUser('hpotter')
      ->willReturn([
        'ou=people,dc=hogwarts,dc=edu',
        'ou=students,dc=hogwarts,dc=edu',
        'ou=gryffindor,ou=students,dc=hogwarts,dc=edu',
      ])
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupIsMember('ou=people,dc=hogwarts,dc=edu', 'hpotter')
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupGroupEntryMembershipsConfigured()
      ->willReturn(FALSE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupUserMembershipsFromUserAttr($ldap_entry)
      ->willReturn([
        'ou=people,dc=hogwarts,dc=edu',
        'ou=students,dc=hogwarts,dc=edu',
        'ou=gryffindor,ou=students,dc=hogwarts,dc=edu',
      ])
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupGroupEntryMembershipsConfigured()
      ->willReturn(TRUE)
      ->shouldBeCalled($this->once());
    $this->ldapGroupManager->groupUserMembershipsFromEntry($ldap_entry)
      ->willReturn([
        'ou=people,dc=hogwarts,dc=edu',
        'ou=students,dc=hogwarts,dc=edu',
        'ou=gryffindor,ou=students,dc=hogwarts,dc=edu',
      ])
      ->shouldBeCalled($this->once());

    $this->ldapTokenProcessor->getTokens()
      ->willReturn([
        'testing_drupal_username' => 'hpotter',
      ])
      ->shouldBeCalled($this->once());
    $this->ldapTokenProcessor->ldapEntryReplacementsForDrupalAccount($ldap_entry, '[0][1][2]')
      ->shouldBeCalled($this->once());

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->once())
      ->method('setRebuild');
    $form_state->expects($this->once())
      ->method('getValues')
      ->willReturn([
        'id' => 'example_server',
        'testing_drupal_username' => 'hpotter',
        'grp_test_grp_dn' => 'ou=people,dc=hogwarts,dc=edu',
        'grp_test_grp_dn_writeable' => 'ou=room of requirements,dc=hogwarts,dc=edu',
      ]);

    $this->form->submitForm($form, $form_state);
  }

  /**
   * Test the binaryCheck method.
   */
  public function testBinaryCheckHasBinary(): void {
    $input = 'Hello, World!🌍';
    $this->assertEquals('Binary (excerpt): Hello, World!🌍', ServerTestForm::binaryCheck($input));
  }

  /**
   * Test the binaryCheck method.
   */
  public function testBinaryCheckHasString(): void {
    $input = 'Hello, World!';
    $this->assertEquals('Hello, World!', ServerTestForm::binaryCheck($input));
  }

}
