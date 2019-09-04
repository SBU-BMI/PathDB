<?php

namespace Drupal\Tests\ldap_servers\Unit;

use Drupal\ldap_servers\Entity\Server;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\ldap_servers\Entity\Server
 * @group ldap
 */
class ServerTests extends UnitTestCase {

  /**
   * Tests searches across multiple DNs.
   */
  public function testSearchAllBaseDns() {

    $stub = $this->getMockBuilder(Server::class)
      ->disableOriginalConstructor()
      ->setMethods(['search', 'getBasedn'])
      ->getMock();

    $baseDn = 'ou=people,dc=example,dc=org';

    $validResult = [
      'count' => 1,
      0 => ['dn' => ['cn=hpotter,ou=people,dc=example,dc=org']],
    ];
    $valueMap = [
      [$baseDn, '(|(cn=hpotter))', ['dn'], 0, 0, 0, NULL, Server::SCOPE_SUBTREE],
      [$baseDn, '(cn=hpotter)', ['dn'], 0, 0, 0, NULL, Server::SCOPE_SUBTREE],
      [$baseDn, 'cn=hpotter', ['dn'], 0, 0, 0, NULL, Server::SCOPE_SUBTREE],
    ];

    $stub->method('getBasedn')
      ->willReturn([$baseDn]);
    $stub->method('search')
      ->will($this->returnCallback(function () use ($valueMap, $validResult) {
        $arguments = func_get_args();

        foreach ($valueMap as $map) {
          if (!is_array($map) || count($arguments) != count($map)) {
            continue;
          }

          if ($arguments === $map) {
            return $validResult;
          }
        }
        return ['count' => 0];
      }));

    $result = $stub->searchAllBaseDns('(|(cn=hpotter,ou=people,dc=example,dc=org))', ['dn']);
    $this->assertEquals(1, $result['count']);
    $result = $stub->searchAllBaseDns('(|(cn=invalid_cn,ou=people,dc=example,dc=org))', ['dn']);
    $this->assertEquals(0, $result['count']);
    $result = $stub->searchAllBaseDns('(|(cn=hpotter))', ['dn']);
    $this->assertEquals(1, $result['count']);
    $result = $stub->searchAllBaseDns('(cn=hpotter)', ['dn']);
    $this->assertEquals(1, $result['count']);
  }

  /**
   * Test removing unchanged attributes.
   */
  public function testRemoveUnchangedAttributes() {

    $existing_data = [
      'cn' => [0 => 'hpotter', 'count' => 1],
      'memberof' => [
        0 => 'cn=gryffindor,ou=groups,dc=hogwarts,dc=edu',
        1 => 'cn=students,ou=groups,dc=hogwarts,dc=edu',
        2 => 'cn=honors students,ou=groups,dc=hogwarts,dc=edu',
        'count' => 3,
      ],
      'count' => 2,
    ];

    $new_data = [
      'cn' => 'hpotter',
      'test_example_value' => 'Test1',
      'memberOf' => [
        'Group1',
        // TODO: This is not correctly supported.
        // 'cn=honors students,ou=groups,dc=hogwarts,dc=edu',.
      ],
    ];

    $result = Server::removeUnchangedAttributes($new_data, $existing_data);

    $result_expected = [
      'test_example_value' => 'Test1',
      'memberOf' => [
        'Group1',
      ],
    ];

    $this->assertEquals($result_expected, $result);

  }

  /**
   * Test getting username from LDAP entry.
   */
  public function testUserUsernameFromLdapEntry() {
    $stub = $this->getMockBuilder(Server::class)
      ->disableOriginalConstructor()
      ->setMethods(['get'])
      ->getMock();

    $map = [
      ['account_name_attr', ''],
      ['user_attr', 'cn'],
    ];
    $stub->method('get')
      ->willReturnMap($map);

    $username = $stub->userUsernameFromLdapEntry([]);
    $this->assertEquals(FALSE, $username);

    $userOpenLdap = [
      'cn' => [0 => 'hpotter', 'count' => 1],
      'mail' => [0 => 'hpotter@hogwarts.edu', 'count' => 1],
      'uid' => [0 => '1', 'count' => 1],
      'guid' => [0 => '101', 'count' => 1],
      'sn' => [0 => 'Potter', 'count' => 1],
      'givenname' => [0 => 'Harry', 'count' => 1],
      'house' => [0 => 'Gryffindor', 'count' => 1],
      'department' => [0 => '', 'count' => 1],
      'faculty' => [0 => 1, 'count' => 1],
      'staff' => [0 => 1, 'count' => 1],
      'student' => [0 => 1, 'count' => 1],
      'gpa' => [0 => '3.8', 'count' => 1],
      'probation' => [0 => 1, 'count' => 1],
      'password' => [0 => 'goodpwd', 'count' => 1],
      'count' => 14,
    ];

    $username = $stub->userUsernameFromLdapEntry($userOpenLdap);
    $this->assertEquals('hpotter', $username);

  }

  /**
   * Test getting the user name from AD via account_name_attr.
   */
  public function testUserUsernameActiveDirectory() {
    $stub = $this->getMockBuilder(Server::class)
      ->disableOriginalConstructor()
      ->setMethods(['get'])
      ->getMock();

    $map = [
      ['account_name_attr', ''],
      ['user_attr', 'samaccountname'],
    ];

    // TODO: this does not cover the case sAMAccountName, verify if that's
    // normalized at an earlier place.
    $stub->method('get')
      ->willReturnMap($map);

    $username = $stub->userUsernameFromLdapEntry([]);
    $this->assertEquals(FALSE, $username);

    $userActiveDirectory = [
      'cn' => [0 => 'hpotter', 'count' => 1],
      'mail' => [0 => 'hpotter@hogwarts.edu', 'count' => 1],
      'uid' => [0 => '1', 'count' => 1],
      'guid' => [0 => '101', 'count' => 1],
      'sn' => [0 => 'Potter', 'count' => 1],
      'givenname' => [0 => 'Harry', 'count' => 1],
      'house' => [0 => 'Gryffindor', 'count' => 1],
      'department' => [0 => '', 'count' => 1],
      'faculty' => [0 => 1, 'count' => 1],
      'staff' => [0 => 1, 'count' => 1],
      'student' => [0 => 1, 'count' => 1],
      'gpa' => [0 => '3.8', 'count' => 1],
      'probation' => [0 => 1, 'count' => 1],
      'password' => [0 => 'goodpwd', 'count' => 1],
      // Divergent data for AD below.
      'samaccountname' => [0 => 'hpotter', 'count' => 1],
      'distinguishedname' => [
        0 => 'cn=hpotter,ou=people,dc=hogwarts,dc=edu',
        'count' => 1,
      ],
      'memberof' => [
        0 => 'cn=gryffindor,ou=groups,dc=hogwarts,dc=edu',
        1 => 'cn=students,ou=groups,dc=hogwarts,dc=edu',
        2 => 'cn=honors students,ou=groups,dc=hogwarts,dc=edu',
        'count' => 3,
      ],
      'count' => 16,
    ];

    $username = $stub->userUsernameFromLdapEntry($userActiveDirectory);
    $this->assertEquals('hpotter', $username);

  }

  /**
   * Test the group membership of the user from an entry.
   */
  public function testGroupUserMembershipsFromEntry() {
    // TODO: Unported.
    $this->assertTrue(TRUE);

    $user_dn = 'cn=hpotter,ou=people,dc=hogwarts,dc=edu';
    $user_ldap_entry = [
      'cn' => [0 => 'hpotter', 'count' => 1],
      'mail' => [0 => 'hpotter@hogwarts.edu', 'count' => 1],
      'uid' => [0 => '1', 'count' => 1],
      'guid' => [0 => '101', 'count' => 1],
      'sn' => [0 => 'Potter', 'count' => 1],
      'givenname' => [0 => 'Harry', 'count' => 1],
      'house' => [0 => 'Gryffindor', 'count' => 1],
      'department' => [0 => '', 'count' => 1],
      'faculty' => [0 => 1, 'count' => 1],
      'staff' => [0 => 1, 'count' => 1],
      'student' => [0 => 1, 'count' => 1],
      'gpa' => [0 => '3.8', 'count' => 1],
      'probation' => [0 => 1, 'count' => 1],
      'password' => [0 => 'goodpwd', 'count' => 1],
      // Divergent data for AD below.
      'samaccountname' => [0 => 'hpotter', 'count' => 1],
      'distinguishedname' => [
        0 => 'cn=hpotter,ou=people,dc=hogwarts,dc=edu',
        'count' => 1,
      ],
      'memberof' => [
        0 => 'cn=gryffindor,ou=groups,dc=hogwarts,dc=edu',
        1 => 'cn=students,ou=groups,dc=hogwarts,dc=edu',
        2 => 'cn=honors students,ou=groups,dc=hogwarts,dc=edu',
        'count' => 3,
      ],
      'count' => 16,
    ];

    $desired = [];
    $desired[0] = [
      0 => 'cn=gryffindor,ou=groups,dc=hogwarts,dc=edu',
      1 => 'cn=students,ou=groups,dc=hogwarts,dc=edu',
      2 => 'cn=honors students,ou=groups,dc=hogwarts,dc=edu',
    ];
    $desired[1] = array_merge($desired[0], ['cn=users,ou=groups,dc=hogwarts,dc=edu']);

    foreach ([0, 1] as $nested) {

      // TODO: Before porting this test, consider splitting nested and
      // not-nested functions up, since this is a mess.
      $nested_display = ($nested) ? 'nested' : 'not nested';
      $desired_count = ($nested) ? 4 : 3;
      $ldap_module_user_entry = ['attr' => $user_ldap_entry, 'dn' => $user_dn];
      $groups_desired = $desired[$nested];

      /** @var \Drupal\ldap_servers\Entity\Server $ldap_server */
      // Test parent function groupMembershipsFromUser.
      // TODO: Comment out / remove placeholder.
      // $groups = $ldap_server->
      // groupMembershipsFromUser($ldap_module_user_entry, $nested);.
      $groups = $groups_desired;
      $count = count($groups);
      $diff1 = array_diff($groups_desired, $groups);
      $diff2 = array_diff($groups, $groups_desired);
      $pass = (count($diff1) == 0 && count($diff2) == 0 && $count == $desired_count);
      $this->assertTrue($pass);

      // Test parent groupUserMembershipsFromUserAttr, for openldap should be
      // false, for ad should work.
      // TODO: Comment out.
      // $groups = $ldap_server->
      // groupUserMembershipsFromUserAttr($ldap_module_user_entry, $nested);.
      $count = is_array($groups) ? count($groups) : $count;
      $pass = (count($diff1) == 0 && count($diff2) == 0 && $count == $desired_count);
      $this->assertTrue($pass);

      // TODO: Comment out.
      // $groups = $ldap_server->
      // groupUserMembershipsFromEntry($ldap_module_user_entry, $nested);.
      $count = count($groups);
      $diff1 = array_diff($groups_desired, $groups);
      $diff2 = array_diff($groups, $groups_desired);
      $pass = (count($diff1) == 0 && count($diff2) == 0 && $count == $desired_count);
      $this->assertTrue($pass);

    }
  }

}
