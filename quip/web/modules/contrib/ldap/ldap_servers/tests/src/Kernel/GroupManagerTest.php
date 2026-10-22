<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_servers\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers_dummy\FakeBridge;
use Drupal\ldap_servers_dummy\FakeCollection;
use Drupal\ldap_servers_dummy\FakeEntryManager;
use Drupal\ldap_servers_dummy\FakeLdap;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Ldap\Entry;

/**
 * Group manager test.
 */
#[Group('ldap')]
#[RunTestsInSeparateProcesses]
class GroupManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'externalauth',
    'ldap_servers',
    'ldap_servers_dummy',
  ];

  /**
   * Server.
   *
   * @var \Drupal\ldap_servers\Entity\Server
   */
  protected $server;

  /**
   * Fake LDAP bridge used to inspect query counts.
   *
   * @var \Drupal\ldap_servers_dummy\FakeBridge
   */
  protected FakeBridge $bridge;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('ldap_server');
    $this->server = Server::create(['id' => 'example']);
    $this->server->set('grp_user_memb_attr', 'Users (external)');
    $this->server->set('grp_user_memb_attr_exists', TRUE);

    $this->bridge = new FakeBridge(
      $this->container->get('logger.channel.ldap_servers'),
      $this->container->get('entity_type.manager')
    );
    $this->bridge->setBindResult(TRUE);
    $this->container->set('ldap.bridge', $this->bridge);
  }

  /**
   * Test group users membership from user attribute.
   */
  public function testGroupUserMembershipsFromUserAttr(): void {
    /** @var \Drupal\ldap_servers\LdapGroupManager $group_manager */
    $group_manager = $this->container->get('ldap.group_manager');
    $memberships = [
      'cn=group1,ou=people,dc=hogwarts,dc=edu',
      'cn=group2,ou=people,dc=hogwarts,dc=edu',
    ];
    $entry = new Entry('cn=hpotter,ou=people,dc=hogwarts,dc=edu', [
      'cn' => [0 => 'hpotter'],
      'mail' => [
        0 => 'hpotter@hogwarts.edu',
        1 => 'hpotter@students.hogwarts.edu',
      ],
      'Users (external)' => $memberships,
    ]
    );
    $group_manager->setServer($this->server);
    $result = $group_manager->groupUserMembershipsFromUserAttr($entry);
    self::assertEquals($memberships, $result);

    $this->server->set('grp_user_memb_attr', 'invalidAttribute');
    $result = $group_manager->groupUserMembershipsFromUserAttr($entry);
    self::assertEmpty($result);

    $this->server->set('grp_user_memb_attr_exists', FALSE);
    $result = $group_manager->groupUserMembershipsFromUserAttr($entry);
    self::assertEmpty($result);
  }

  /**
   * Direct groups are returned without a parent-group search.
   */
  public function testGroupUserMembershipsFromEntrySkipsNestedQueryWhenNotNested(): void {
    $group_dn = 'cn=gryffindor,ou=groups,dc=hogwarts,dc=edu';
    $user = new Entry('cn=hpotter,ou=people,dc=hogwarts,dc=edu');
    $ldap = $this->prepareEntryMembershipLdap(FALSE, $user, $group_dn);

    /** @var \Drupal\ldap_servers\LdapGroupManager $group_manager */
    $group_manager = $this->container->get('ldap.group_manager');
    $result = $group_manager->groupUserMembershipsFromEntry($user);

    self::assertEquals([$group_dn], $result);
    self::assertSame(1, $ldap->getQueryCount());
  }

  /**
   * Nested groups still search for parent groups.
   */
  public function testGroupUserMembershipsFromEntryQueriesParentsWhenNested(): void {
    $group_dn = 'cn=gryffindor,ou=groups,dc=hogwarts,dc=edu';
    $user = new Entry('cn=hpotter,ou=people,dc=hogwarts,dc=edu');
    $ldap = $this->prepareEntryMembershipLdap(TRUE, $user, $group_dn);

    /** @var \Drupal\ldap_servers\LdapGroupManager $group_manager */
    $group_manager = $this->container->get('ldap.group_manager');
    $result = $group_manager->groupUserMembershipsFromEntry($user);

    self::assertEquals([$group_dn], $result);
    self::assertGreaterThan(1, $ldap->getQueryCount());
  }

  /**
   * Configures entry-based group lookups and stubs the membership search.
   *
   * @param bool $nested
   *   Whether nested group searches are enabled.
   * @param \Symfony\Component\Ldap\Entry $user
   *   LDAP user whose groups are requested.
   * @param string $group_dn
   *   Direct group DN returned by the membership search.
   *
   * @return \Drupal\ldap_servers_dummy\FakeLdap
   *   LDAP client used to assert query counts.
   */
  private function prepareEntryMembershipLdap(bool $nested, Entry $user, string $group_dn): FakeLdap {
    $this->server->set('basedn', ['dc=hogwarts,dc=edu']);
    $this->server->set('grp_object_cat', 'groupOfNames');
    $this->server->set('grp_memb_attr', 'member');
    $this->server->set('grp_memb_attr_match_user_attr', 'dn');
    $this->server->set('grp_nested', $nested);

    /** @var \Drupal\ldap_servers\LdapGroupManager $group_manager */
    $group_manager = $this->container->get('ldap.group_manager');
    $group_manager->setServer($this->server);

    $ldap = $this->bridge->get();
    self::assertInstanceOf(FakeLdap::class, $ldap);
    $membership_filter = sprintf(
      '(&(objectClass=groupOfNames)(member=%s))',
      $user->getDn()
    );
    $ldap->setQueryResult([
      $membership_filter => new FakeCollection([new Entry($group_dn)]),
    ]);
    $ldap->resetQueryCount();

    return $ldap;
  }

  /**
   * Nested memberOf groups query parent groups.
   */
  public function testNestedUserAttributeMembershipsQueryParents(): void {
    $this->server->set('basedn', ['dc=hogwarts,dc=edu']);
    $this->server->set('grp_object_cat', 'groupOfNames');
    $this->server->set('grp_memb_attr', 'member');
    $this->server->set('grp_memb_attr_match_user_attr', 'dn');
    $this->server->set('grp_nested', TRUE);

    $child = 'cn=gryffindor,ou=groups,dc=hogwarts,dc=edu';
    $parent = 'cn=students,ou=groups,dc=hogwarts,dc=edu';
    $entry = new Entry('cn=hpotter,ou=people,dc=hogwarts,dc=edu', [
      'Users (external)' => [$child],
    ]);

    /** @var \Drupal\ldap_servers\LdapGroupManager $group_manager */
    $group_manager = $this->container->get('ldap.group_manager');
    $group_manager->setServer($this->server);
    $ldap = $this->bridge->get();
    $escaped = \function_exists('ldap_escape')
      ? ldap_escape($child, '', LDAP_ESCAPE_FILTER)
      : $group_manager::php56PolyfillLdapEscape($child, '', 1);
    $parent_filter = sprintf(
      '(&(objectClass=groupOfNames)(|(member=%s)))',
      $escaped
    );
    $ldap->setQueryResult([
      $parent_filter => new FakeCollection([new Entry($parent)]),
    ]);

    $result = $group_manager->groupUserMembershipsFromUserAttr($entry);
    self::assertContains($child, $result);
    self::assertContains($parent, $result);
  }

  /**
   * Direct group members are read from the group entry.
   */
  public function testGroupMembersAndIsMember(): void {
    $this->server->set('basedn', ['ou=people,dc=hogwarts,dc=edu']);
    $this->server->set('user_attr', 'cn');
    $this->server->set('grp_object_cat', 'groupOfNames');
    $this->server->set('grp_memb_attr', 'member');
    $this->server->set('grp_memb_attr_match_user_attr', 'dn');
    $this->server->set('grp_user_memb_attr_exists', TRUE);
    $this->server->set('grp_user_memb_attr', 'memberof');

    $group_dn = 'cn=gryffindor,ou=groups,dc=hogwarts,dc=edu';
    $user_dn = 'cn=hpotter,ou=people,dc=hogwarts,dc=edu';
    $group = new Entry($group_dn, [
      'cn' => ['gryffindor'],
      'objectclass' => ['groupOfNames'],
      'member' => [$user_dn],
    ]);
    $user = new Entry($user_dn, [
      'cn' => ['hpotter'],
      'memberof' => [$group_dn],
    ]);

    /** @var \Drupal\ldap_servers\LdapGroupManager $group_manager */
    $group_manager = $this->container->get('ldap.group_manager');
    $group_manager->setServer($this->server);
    $ldap = $this->bridge->get();
    $ldap->setQueryResult([
      '(objectclass=*)' => new FakeCollection([$group]),
      '(cn=hpotter)' => new FakeCollection([$user]),
    ]);

    self::assertEquals([$user_dn], $group_manager->groupMembers($group_dn));
    self::assertContains($group_dn, $group_manager->groupUserMembershipsFromUserAttr($user));
  }

  /**
   * Nested groupAllMembers includes child group DNs.
   */
  public function testGroupAllMembersRecursive(): void {
    $this->server->set('basedn', ['dc=hogwarts,dc=edu']);
    $this->server->set('grp_object_cat', 'groupOfNames');
    $this->server->set('grp_memb_attr', 'member');
    $this->server->set('grp_memb_attr_match_user_attr', 'dn');

    $parent_dn = 'cn=students,ou=groups,dc=hogwarts,dc=edu';
    $child_dn = 'cn=gryffindor,ou=groups,dc=hogwarts,dc=edu';
    $user_dn = 'cn=hpotter,ou=people,dc=hogwarts,dc=edu';
    $parent = new Entry($parent_dn, [
      'cn' => ['students'],
      'objectclass' => ['groupOfNames'],
      'member' => [$child_dn, $user_dn],
    ]);
    $child = new Entry($child_dn, [
      'cn' => ['gryffindor'],
      'objectclass' => ['groupOfNames'],
      'member' => [$user_dn],
    ]);

    /** @var \Drupal\ldap_servers\LdapGroupManager $group_manager */
    $group_manager = $this->container->get('ldap.group_manager');
    $group_manager->setServer($this->server);
    $ldap = $this->bridge->get();
    $ldap->setQueryResult([
      '(objectclass=*)' => new FakeCollection([$parent]),
      '(|(cn=gryffindor,ou=groups)(cn=hpotter,ou=people))' => new FakeCollection([$child]),
    ]);

    $members = $group_manager->groupAllMembers($parent_dn);
    self::assertContains($child_dn, $members);
    self::assertContains($user_dn, $members);
  }

  /**
   * Group CRUD operations use the entry manager.
   */
  public function testGroupCrud(): void {
    $this->server->set('grp_object_cat', 'groupOfNames');
    $this->server->set('grp_memb_attr', 'member');
    $this->server->set('grp_memb_attr_match_user_attr', 'dn');

    $manager = new FakeEntryManager();
    /** @var \Drupal\ldap_servers\LdapGroupManager $group_manager */
    $group_manager = $this->container->get('ldap.group_manager');
    $group_manager->setServer($this->server);
    $ldap = $this->bridge->get();
    $ldap->setEntryManagerResponse($manager);
    $ldap->setQueryResult([
      '(objectclass=*)' => new FakeCollection([]),
    ]);

    $dn = 'cn=new_group,ou=groups,dc=hogwarts,dc=edu';
    self::assertTrue($group_manager->groupAddGroup($dn, [
      'description' => ['test'],
      'objectclass' => ['groupOfNames'],
    ]));
    self::assertCount(1, $manager->added);

    self::assertTrue($group_manager->groupAddMember($dn, 'cn=hpotter,ou=people,dc=hogwarts,dc=edu'));
    self::assertCount(1, $manager->addedAttributeValues);

    self::assertTrue($group_manager->groupRemoveMember($dn, 'cn=hpotter,ou=people,dc=hogwarts,dc=edu'));
    self::assertCount(1, $manager->removedAttributeValues);

    self::assertTrue($group_manager->groupRemoveGroup($dn, FALSE));
    self::assertSame([$dn], $manager->removed);
  }

  /**
   * RDN helpers extract values from DNs.
   */
  public function testRdnHelpersAndDnMemberships(): void {
    $this->server->set('basedn', ['ou=people,dc=hogwarts,dc=edu']);
    $this->server->set('user_attr', 'cn');
    $this->server->set('grp_derive_from_dn', TRUE);
    $this->server->set('grp_derive_from_dn_attr', 'ou');

    $user = new Entry('cn=hpotter,ou=people,dc=hogwarts,dc=edu', [
      'cn' => ['hpotter'],
    ]);
    /** @var \Drupal\ldap_servers\LdapGroupManager $group_manager */
    $group_manager = $this->container->get('ldap.group_manager');
    $group_manager->setServer($this->server);
    $ldap = $this->bridge->get();
    $ldap->setQueryResult([
      '(cn=hpotter)' => new FakeCollection([$user]),
    ]);

    self::assertEquals(['people'], $group_manager->groupUserMembershipsFromDn('hpotter'));
    self::assertEquals(
      ['people'],
      $group_manager->getAllRdnValuesFromDn($user->getDn(), 'ou')
    );
  }

}
