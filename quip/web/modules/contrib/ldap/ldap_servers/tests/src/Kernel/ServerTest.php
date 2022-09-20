<?php

declare(strict_types = 1);

namespace Drupal\Tests\ldap_servers\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ldap_servers\Entity\Server;
use Symfony\Component\Ldap\Entry;

/**
 * Server tests.
 *
 * @group ldap
 */
class ServerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ldap_servers', 'externalauth'];

  /**
   * Server.
   *
   * @var \Drupal\ldap_servers\Entity\Server
   */
  protected $server;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('ldap_server');
    $this->server = Server::create(['id' => 'example']);
  }

  /**
   * Test derive user name.
   */
  public function testDeriveUserName(): void {
    $entry = new Entry('cn=hpotter,ou=people,dc=example,dc=org');
    $entry->setAttribute('samAccountName', ['hpotter']);
    $entry->setAttribute('username', ['harry']);

    // Default case, only user_attr set.
    $this->server->set('user_attr', 'samAccountName');
    self::assertEquals('hpotter', $this->server->deriveUsernameFromLdapResponse($entry));
    $this->server->set('account_name_attr', 'username');
    self::assertEquals('harry', $this->server->deriveUsernameFromLdapResponse($entry));
  }

  /**
   * Test the Base DN.
   */
  public function testGetBasedn(): void {
    $this->server->set('basedn', []);
    self::assertEquals([], $this->server->getBaseDn());
    $this->server->set('basedn', [
      'ou=people,dc=hogwarts,dc=edu',
      'ou=groups,dc=hogwarts,dc=edu',
    ]);
    self::assertEquals('ou=groups,dc=hogwarts,dc=edu', $this->server->getBaseDn()[1]);
    self::assertCount(2, $this->server->getBaseDn());
  }

  /**
   * Test getting username from LDAP entry.
   */
  public function testDeriveAttributesFromLdapResponse(): void {

    $this->server->set('account_name_attr', '');
    $this->server->set('user_attr', 'cn');
    $this->server->set('mail_attr', 'mail');
    $this->server->set('unique_persistent_attr', 'guid');

    $empty_entry = new Entry('undefined', []);
    self::assertEquals('', $this->server->deriveUsernameFromLdapResponse($empty_entry));
    self::assertEquals('', $this->server->deriveEmailFromLdapResponse($empty_entry));

    $userOpenLdap = new Entry('cn=hpotter,ou=people,dc=hogwarts,dc=edu', [
      'cn' => [0 => 'hpotter'],
      'mail' => [
        0 => 'hpotter@hogwarts.edu',
        1 => 'hpotter@students.hogwarts.edu',
      ],
      'uid' => [0 => '1'],
      'guid' => [0 => '101'],
      'sn' => [0 => 'Potter'],
      'givenname' => [0 => 'Harry'],
      'house' => [0 => 'Gryffindor'],
      'department' => [0 => ''],
      'faculty' => [0 => 1],
      'staff' => [0 => 1],
      'student' => [0 => 1],
      'gpa' => [0 => '3.8'],
      'probation' => [0 => 1],
      'password' => [0 => 'goodpwd'],
    ]);

    self::assertEquals('hpotter', $this->server->deriveUsernameFromLdapResponse($userOpenLdap));
    self::assertEquals('hpotter@hogwarts.edu', $this->server->deriveEmailFromLdapResponse($userOpenLdap));

    $userOpenLdap->removeAttribute('mail');
    $this->server->set('mail_template', '[cn]@template.com');
    self::assertEquals('hpotter@template.com', $this->server->deriveEmailFromLdapResponse($userOpenLdap));

    self::assertEquals('101', $this->server->derivePuidFromLdapResponse($userOpenLdap));

    $this->server->set('unique_persistent_attr_binary', TRUE);
    $userOpenLdap->setAttribute('guid', ['Rr0by/+kSEKzVGoWnkpQ4Q==']);
    self::assertEquals('52723062792f2b6b53454b7a56476f576e6b705134513d3d', $this->server->derivePuidFromLdapResponse($userOpenLdap));
  }

  /**
   * Test non-latin DN.
   */
  public function testNonLatinDn(): void {

    $this->server->set('account_name_attr', '');
    $this->server->set('user_attr', 'cn');
    $this->server->set('mail_attr', 'mail');
    $this->server->set('unique_persistent_attr', 'guid');

    $userOpenLdap = new Entry('cn=zażółćgęśląjaźń,ou=people,dc=hogwarts,dc=edu', [
      'cn' => [0 => 'zażółćgęśląjaźń'],
    ]);
    self::assertEquals('zażółćgęśląjaźń', $this->server->deriveUsernameFromLdapResponse($userOpenLdap));
  }

  /**
   * Test remaining getters.
   */
  public function testGetters(): void {
    $this->server->set('address', 'example.com');
    self::assertEquals('example.com', $this->server->getServerAddress());

    $this->server->set('bind_method', 'user');
    self::assertEquals('user', $this->server->getBindMethod());

    $this->server->set('binddn', '1');
    self::assertEquals('1', $this->server->getBindDn());

    $this->server->set('bindpw', '2');
    self::assertEquals('2', $this->server->getBindPassword());

    $this->server->set('grp_derive_from_dn_attr', '3');
    self::assertEquals('3', $this->server->getDerivedGroupFromDnAttribute());

    $this->server->set('grp_derive_from_dn', TRUE);
    self::assertEquals(TRUE, $this->server->isGroupDerivedFromDn());

    $this->server->set('grp_memb_attr_match_user_attr', '5');
    self::assertEquals('5', $this->server->getUserAttributeFromGroupMembershipEntryAttribute());

    $this->server->set('grp_memb_attr', '6');
    self::assertEquals('6', $this->server->getGroupMembershipAttribute());

    $this->server->set('grp_nested', FALSE);
    self::assertEquals(FALSE, $this->server->isGrouppNested());

    self::assertNull($this->server->getGroupObjectClass());
    $this->server->set('grp_object_cat', '7');
    self::assertEquals('7', $this->server->getGroupObjectClass());

    $this->server->set('grp_test_grp_dn_writeable', '8');
    self::assertEquals('8', $this->server->getGroupTestGroupDnWriteable());

    $this->server->set('grp_test_grp_dn', '9');
    self::assertEquals('9', $this->server->getGroupTestGroupDn());

    $this->server->set('grp_unused', TRUE);
    self::assertEquals(TRUE, $this->server->isGroupUnused());
  }

}
