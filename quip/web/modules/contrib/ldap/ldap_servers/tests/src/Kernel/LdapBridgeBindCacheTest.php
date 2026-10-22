<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_servers\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers\Helper\CredentialsStorage;
use Drupal\ldap_servers\LdapBridge;
use Drupal\ldap_servers_dummy\FakeLdap;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Ldap\Exception\ConnectionException;

/**
 * Tests that LdapBridge skips duplicate protocol binds.
 *
 * See issue “Prevent Unnecessary Duplicate Bind Attempts”.
 */
#[Group('ldap')]
#[RunTestsInSeparateProcesses]
class LdapBridgeBindCacheTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'externalauth',
    'ldap_servers',
    'ldap_servers_dummy',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('ldap_server');
    $this->resetBindState();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->resetBindState();
    parent::tearDown();
  }

  /**
   * Login uses one service-account bind and one user bind.
   */
  public function testDuplicateBindsWithSameCredentialsAreSkipped(): void {
    $bridge = $this->createBridge();
    $server = $this->createServiceAccountServer();

    $bridge->setServer($server);
    $this->assertTrue($bridge->bind());
    $this->assertSame(1, BindCountingLdap::$bindCount);

    $bridge->setServer($server);
    $this->assertTrue($bridge->bind());
    $this->assertSame(1, BindCountingLdap::$bindCount);

    CredentialsStorage::storeUserDn('cn=hpotter,ou=people,dc=hogwarts,dc=edu');
    CredentialsStorage::storeUserPassword('alohomora');
    CredentialsStorage::testCredentials(TRUE);
    $bridge->setServer($server);
    $this->assertTrue($bridge->bind());
    $this->assertSame(2, BindCountingLdap::$bindCount);

    CredentialsStorage::testCredentials(FALSE);
    $bridge->setServer($server);
    $this->assertTrue($bridge->bind());
    $this->assertSame(2, BindCountingLdap::$bindCount);
  }

  /**
   * A failed bind is cached for the rest of the request.
   */
  public function testFailedBindIsNotRetried(): void {
    BindCountingLdap::$throwOnBind = TRUE;
    $bridge = $this->createBridge();
    $server = $this->createServiceAccountServer();

    $bridge->setServer($server);
    $this->assertFalse($bridge->bind());
    $this->assertSame(1, BindCountingLdap::$bindCount);

    $bridge->setServer($server);
    $this->assertFalse($bridge->bind());
    $this->assertSame(1, BindCountingLdap::$bindCount);
  }

  /**
   * Builds a bridge that never opens a real LDAP connection.
   *
   * @return \Drupal\Tests\ldap_servers\Kernel\BindCountingLdapBridge
   *   The test bridge.
   */
  private function createBridge(): BindCountingLdapBridge {
    return new BindCountingLdapBridge(
      $this->container->get('logger.channel.ldap_servers'),
      $this->container->get('entity_type.manager')
    );
  }

  /**
   * Builds a service-account server used for bind credentials.
   *
   * @return \Drupal\ldap_servers\Entity\Server
   *   Server entity.
   */
  private function createServiceAccountServer(): Server {
    return Server::create([
      'id' => 'hogwarts',
      'bind_method' => 'service_account',
      'binddn' => 'cn=admin,dc=hogwarts,dc=edu',
      'bindpw' => 'secret',
    ]);
  }

  /**
   * Clears request-scoped bind cache and test counters.
   */
  private function resetBindState(): void {
    drupal_static_reset('bind');
    BindCountingLdap::$bindCount = 0;
    BindCountingLdap::$throwOnBind = FALSE;
    CredentialsStorage::storeUserDn(NULL);
    CredentialsStorage::storeUserPassword(NULL);
    CredentialsStorage::testCredentials(FALSE);
  }

}

/**
 * LdapBridge that recreates an in-memory client on setServer().
 */
class BindCountingLdapBridge extends LdapBridge {

  /**
   * {@inheritdoc}
   */
  public function setServer(Server $server): void {
    $this->bindMethod = $server->get('bind_method');
    $this->bindDn = $server->get('binddn');
    $this->bindPw = $server->get('bindpw');
    $this->ldap = new BindCountingLdap();
  }

}

/**
 * LDAP client that counts protocol bind() calls.
 */
class BindCountingLdap extends FakeLdap {

  /**
   * Number of bind() calls in this test.
   *
   * @var int
   */
  public static int $bindCount = 0;

  /**
   * Whether bind() should throw ConnectionException.
   *
   * @var bool
   */
  public static bool $throwOnBind = FALSE;

  /**
   * {@inheritdoc}
   */
  public function bind(?string $dn = NULL, ?string $password = NULL): void {
    self::$bindCount++;
    if (self::$throwOnBind) {
      throw new ConnectionException('Failed connection');
    }
  }

}
