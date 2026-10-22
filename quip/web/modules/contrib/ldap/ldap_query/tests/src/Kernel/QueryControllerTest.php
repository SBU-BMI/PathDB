<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_query\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ldap_query\Controller\QueryController;
use Drupal\ldap_query\Entity\QueryEntity;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers_dummy\FakeBridge;
use Drupal\ldap_servers_dummy\FakeCollection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\LdapException;

/**
 * Tests LDAP query execution through QueryController.
 */
#[Group('ldap')]
#[RunTestsInSeparateProcesses]
class QueryControllerTest extends KernelTestBase {

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
   * Fake LDAP bridge.
   *
   * @var \Drupal\ldap_servers_dummy\FakeBridge
   */
  protected FakeBridge $bridge;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('ldap_server');

    Server::create([
      'id' => 'example',
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
      'base_dn' => "ou=people,dc=hogwarts,dc=edu",
      'filter' => '(objectClass=person)',
      'attributes' => 'cn,mail',
      'size_limit' => 0,
      'time_limit' => 0,
      'scope' => 'sub',
      'dereference' => 0,
    ])->save();

    $this->bridge = new FakeBridge(
      $this->container->get('logger.channel.ldap_servers'),
      $this->container->get('entity_type.manager')
    );
    $this->bridge->setServer(
      $this->container->get('entity_type.manager')
        ->getStorage('ldap_server')
        ->load('example')
    );
    $this->bridge->setBindResult(TRUE);
    $this->container->set('ldap.bridge', $this->bridge);
  }

  /**
   * Builds a controller that uses the fake bridge.
   */
  private function controller(?LoggerInterface $logger = NULL): QueryController {
    return new QueryController(
      $this->container->get('entity_type.manager'),
      $this->bridge,
      $logger ?? $this->container->get('logger.channel.ldap_query')
    );
  }

  /**
   * Successful queries return entries and available fields.
   */
  public function testExecuteReturnsResultsAndFields(): void {
    $entry = new Entry('cn=hpotter,ou=people,dc=hogwarts,dc=edu', [
      'cn' => ['hpotter'],
      'mail' => ['hpotter@example.com'],
    ]);
    /** @var \Drupal\ldap_servers_dummy\FakeLdap $ldap */
    $ldap = $this->bridge->get();
    $ldap->setQueryResult([
      '(objectClass=person)' => new FakeCollection([$entry]),
    ]);

    $controller = $this->controller();
    $controller->load('students');
    self::assertEquals('(objectClass=person)', $controller->getFilter());
    $controller->execute();

    $results = $controller->getRawResults();
    self::assertCount(1, $results);
    self::assertEquals('cn=hpotter,ou=people,dc=hogwarts,dc=edu', $results[0]->getDn());
    self::assertEquals(
      ['cn' => 'cn', 'mail' => 'mail'],
      $controller->availableFields()
    );
  }

  /**
   * An override filter is used instead of the entity filter.
   */
  public function testExecuteUsesOverrideFilter(): void {
    $entry = new Entry('cn=hgranger,ou=people,dc=hogwarts,dc=edu', [
      'cn' => ['hgranger'],
    ]);
    /** @var \Drupal\ldap_servers_dummy\FakeLdap $ldap */
    $ldap = $this->bridge->get();
    $ldap->setQueryResult([
      '(cn=hgranger)' => new FakeCollection([$entry]),
    ]);

    $controller = $this->controller();
    $controller->load('students');
    $controller->execute('(cn=hgranger)');
    self::assertCount(1, $controller->getRawResults());
  }

  /**
   * Bind failure leaves results empty.
   */
  public function testExecuteBindFailureLeavesEmptyResults(): void {
    $this->bridge->setBindResult(FALSE);
    $controller = $this->controller();
    $controller->load('students');
    $controller->execute();
    self::assertSame([], $controller->getRawResults());
  }

  /**
   * Missing query entities log a warning.
   */
  public function testExecuteMissingQueryLogsWarning(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('warning')
      ->with('Could not load query @query', ['@query' => 'missing']);

    $controller = $this->controller($logger);
    $controller->load('missing');
    $controller->execute();
    self::assertSame([], $controller->getRawResults());
  }

  /**
   * LDAP exceptions during execute are logged.
   */
  public function testExecuteLogsLdapException(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('warning')
      ->with('LDAP query exception @message', $this->anything());

    $throwing = new class ([]) extends FakeCollection {

      /**
       * {@inheritdoc}
       */
      public function toArray(): array {
        throw new LdapException('query failed');
      }

    };
    /** @var \Drupal\ldap_servers_dummy\FakeLdap $ldap */
    $ldap = $this->bridge->get();
    $ldap->setQueryResult([
      '(objectClass=person)' => $throwing,
    ]);

    $controller = $this->controller($logger);
    $controller->load('students');
    $controller->execute();
    self::assertSame([], $controller->getRawResults());
  }

}
