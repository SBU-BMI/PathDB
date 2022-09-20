<?php

namespace Drupal\Tests\ldap_user\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers_dummy\FakeBridge;
use Drupal\ldap_servers_dummy\FakeCollection;
use Drupal\user\Entity\User;

/**
 * Orphan processor test.
 *
 * @group ldap_user
 */
class OrphanProcessorTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'externalauth',
    'ldap_query',
    'ldap_servers',
    'ldap_user',
  ];

  /**
   * LDAP server.
   *
   * @var \Drupal\ldap_servers\Entity\Server
   */
  private $server;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('ldap_server');
    $this->installSchema('user', 'users_data');
    $this->installSchema('externalauth', 'authmap');
    $this->installConfig('ldap_user');

    $this->server = Server::create([
      'id' => 'example',
      'status' => FALSE,
      'basedn' => ['ou=people,dc=hogwarts,dc=edu'],
      'user_attr' => 'cn',
      'mail_attr' => 'mail',
    ]);
    $this->server->save();

    $bridge = new FakeBridge(
      $this->container->get('logger.channel.ldap_servers'),
      $this->container->get('entity_type.manager')
    );
    $bridge->setServer($this->server);
    $collection = ['(cn=hpotter)' => new FakeCollection([])];
    $bridge->get()->setQueryResult($collection);
    $bridge->setBindResult(TRUE);
    $this->container->set('ldap.bridge', $bridge);
  }

  /**
   * Test missing server.
   */
  public function testMissingServer(): void {
    // Add a user 1 which we are skipping before our regular user.
    $this->createUser()->save();
    $missingUser = User::create([
      'name' => 'hpotter',
      'mail' => 'hpotter@hogwards.edu',
      'status' => 1,
      'ldap_user_current_dn' => 'cn=hpotter,ou=people,dc=hogwarts,dc=edu',
      'ldap_user_puid_property' => 'uid',
      'ldap_user_puid_sid' => 'example',
      'ldap_user_puid' => '20001',
    ]);
    $missingUser->save();
    $this->expectException(\Exception::class);
    $this->container->get('ldap.orphan_processor')->checkOrphans();
  }

  /**
   * Test orphan checking.
   *
   * This test is actually not ideal since it shows how with an incorrect setup
   * items can "fall through".
   */
  public function testCheckOrphans(): void {
    $this->server->setStatus(TRUE)->save();
    /** @var \Drupal\ldap_user\Processor\OrphanProcessor $processor */
    $processor = $this->container->get('ldap.orphan_processor');

    // Add a user 1 which we are skipping before our regular user.
    $this->createUser()->save();
    $missingUser = User::create([
      'name' => 'hpotter',
      'mail' => 'hpotter@hogwards.edu',
      'status' => 1,
      'ldap_user_current_dn' => 'cn=hpotter,ou=people,dc=hogwarts,dc=edu',
      'ldap_user_puid_property' => 'uid',
      'ldap_user_puid_sid' => 'example',
      'ldap_user_puid' => '20001',
    ]);
    $missingUser->save();
    $processor->checkOrphans();
    // Not deleted since by default we send an email.
    self::assertNotNull(User::load($missingUser->id()));

    $this->config('ldap_user.settings')
      ->set('orphanedAccountCheckInterval', 'weekly')
      ->set('orphanedDrupalAcctBehavior', 'user_cancel_delete')
      ->save();
    // Not deleted since we just checked.
    $processor->checkOrphans();
    self::assertNotNull(User::load($missingUser->id()));

    $this->config('ldap_user.settings')
      ->set('orphanedAccountCheckInterval', 'always')
      ->save();
    // Deleted.
    $processor->checkOrphans();
    self::assertEmpty(User::load($missingUser->id()));
  }

}
