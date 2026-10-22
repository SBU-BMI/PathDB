<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_query\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ldap_query\Controller\QueryController;
use Drupal\ldap_query\Entity\QueryEntity;
use Drupal\ldap_query\Form\QueryEntityDeleteForm;
use Drupal\ldap_query\Form\QueryTestForm;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers_dummy\FakeBridge;
use Drupal\ldap_servers_dummy\FakeCollection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Ldap\Entry;

/**
 * Tests ldap_query forms, hooks, and list builder.
 */
#[Group('ldap')]
#[RunTestsInSeparateProcesses]
class QuerySupportTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'externalauth',
    'ldap_query',
    'ldap_servers',
    'ldap_servers_dummy',
    'system',
    'user',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('ldap_server');
    $this->installEntitySchema('user');
    $this->installConfig(['user']);

    Server::create([
      'id' => 'example',
      'label' => 'Hogwarts',
      'status' => TRUE,
      'basedn' => ['ou=people,dc=hogwarts,dc=edu'],
      'user_attr' => 'cn',
      'mail_attr' => 'mail',
    ])->save();

    QueryEntity::create([
      'id' => 'students',
      'label' => 'Students',
      'server_id' => 'example',
      'status' => TRUE,
      'base_dn' => 'ou=people,dc=hogwarts,dc=edu',
      'filter' => '(objectClass=person)',
      'attributes' => 'cn,mail',
      'size_limit' => 0,
      'time_limit' => 0,
      'scope' => 'sub',
      'dereference' => 0,
    ])->save();
  }

  /**
   * Entity getters and help hook.
   */
  public function testEntityGettersAndHelp(): void {
    $query = QueryEntity::load('students');
    self::assertEquals('example', $query->getServerId());
    self::assertTrue($query->isActive());
    self::assertEquals(0, $query->getSizeLimit());
    self::assertEquals('sub', $query->getScope());

    $help = ldap_query_help(
      'ldap_query.admin_form',
      $this->createMock(RouteMatchInterface::class)
    );
    self::assertStringContainsString('LDAP Query Module', (string) $help);
    self::assertNull(ldap_query_help(
      'other.route',
      $this->createMock(RouteMatchInterface::class)
    ));
  }

  /**
   * Entity operations include a test link for admins.
   */
  public function testEntityOperation(): void {
    $account = $this->container->get('entity_type.manager')
      ->getStorage('user')
      ->create(['name' => 'admin', 'status' => 1]);
    $account->save();
    $role = $this->container->get('entity_type.manager')
      ->getStorage('user_role')
      ->create(['id' => 'ldap_admin', 'label' => 'LDAP admin']);
    $role->grantPermission('administer ldap');
    $role->save();
    $account->addRole('ldap_admin');
    $account->save();
    $this->container->get('current_user')->setAccount($account);

    $query = QueryEntity::load('students');
    $ops = ldap_query_entity_operation($query);
    self::assertArrayHasKey('query_test', $ops);

    $cacheability = new CacheableMetadata();
    ldap_query_entity_operation($query, $cacheability);
    self::assertContains('user.permissions', $cacheability->getCacheContexts());
  }

  /**
   * List builder shows server label and status.
   */
  public function testListBuilder(): void {
    $list = $this->container->get('entity_type.manager')
      ->getListBuilder('ldap_query_entity');
    $query = QueryEntity::load('students');
    $row = $list->buildRow($query);
    self::assertEquals('Hogwarts', $row['server_id']);
    self::assertEquals('Enabled', (string) $row['status']);
    self::assertArrayHasKey('server_id', $list->buildHeader());
  }

  /**
   * Delete form removes the entity.
   */
  public function testDeleteForm(): void {
    $query = QueryEntity::load('students');
    $form_object = QueryEntityDeleteForm::create($this->container);
    $form_object->setEntity($query);
    $form_state = new FormState();
    $form = [];
    self::assertStringContainsString('Students', (string) $form_object->getQuestion());
    $form_object->submitForm($form, $form_state);
    self::assertNull(QueryEntity::load('students'));
  }

  /**
   * Query test form renders LDAP results.
   */
  public function testQueryTestForm(): void {
    $bridge = new FakeBridge(
      $this->container->get('logger.channel.ldap_servers'),
      $this->container->get('entity_type.manager')
    );
    $bridge->setServer(
      $this->container->get('entity_type.manager')
        ->getStorage('ldap_server')
        ->load('example')
    );
    $bridge->setBindResult(TRUE);
    $bridge->get()->setQueryResult([
      '(objectClass=person)' => new FakeCollection([
        new Entry('cn=hpotter,ou=people,dc=hogwarts,dc=edu', [
          'cn' => ['hpotter'],
          'mail' => ['hpotter@example.com'],
        ]),
      ]),
    ]);
    $this->container->set('ldap.bridge', $bridge);
    $controller = new QueryController(
      $this->container->get('entity_type.manager'),
      $bridge,
      $this->container->get('logger.channel.ldap_query')
    );
    $form_object = new QueryTestForm($controller);
    $form = $form_object->buildForm([], new FormState(), 'students');
    self::assertEquals('ldap_query_test_form', $form_object->getFormId());
    self::assertArrayHasKey('result', $form);
    $form_object->validateForm($form, new FormState());
    $form_object->submitForm($form, new FormState());
  }

  /**
   * Query entity form builds and saves attributes without spaces.
   */
  public function testQueryEntityFormSave(): void {
    $query = QueryEntity::create([
      'id' => 'staff',
      'label' => 'Staff',
      'server_id' => 'example',
      'status' => TRUE,
      'base_dn' => 'ou=people,dc=hogwarts,dc=edu',
      'filter' => '(objectClass=person)',
      'attributes' => 'cn, mail',
      'size_limit' => 0,
      'time_limit' => 0,
      'scope' => 'sub',
      'dereference' => 0,
    ]);
    $form_object = $this->container->get('entity_type.manager')
      ->getFormObject('ldap_query_entity', 'add');
    $form_object->setEntity($query);
    $form_state = new FormState();
    $form = $form_object->form([], $form_state);
    self::assertArrayHasKey('filter', $form);
    $form_object->save($form, $form_state);
    $saved = QueryEntity::load('staff');
    self::assertEquals('cn,mail', $saved->get('attributes'));
  }

}
