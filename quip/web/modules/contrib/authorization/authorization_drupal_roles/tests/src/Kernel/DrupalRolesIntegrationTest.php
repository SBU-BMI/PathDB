<?php

declare(strict_types = 1);

namespace Drupal\Tests\authorization_drupal_roles\Kernel;

use Drupal\authorization\Entity\AuthorizationProfile;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Integration tests for authorization_drupal_roles.
 *
 * @group authorization
 */
class DrupalRolesIntegrationTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'authorization',
    'authorization_drupal_roles',
  ];

  /**
   * Tests granting new roles.
   */
  public function testGrant(): void {
    $profile = AuthorizationProfile::create([
      'status' => 'true',
      'description' => 'test',
      'id' => 'test',
      'provider' => 'dummy',
      'consumer' => 'authorization_drupal_roles',
    ]);
    $consumer = $profile->getConsumer();

    $account = User::create(['name' => 'hpotter']);
    $account->set('authorization_drupal_roles_roles', ['student', 'gryffindor']);
    $consumer->grantSingleAuthorization($account, 'student');
    $savedData = [0 => ['value' => 'student'], 1 => ['value' => 'gryffindor']];

    // No duplicate information in authorization record, only one role because
    // nothing else assigned, yet.
    self::assertEquals($savedData, $account->get('authorization_drupal_roles_roles')->getValue());
    self::assertEquals(['anonymous', 'student'], $account->getRoles());

    $consumer->grantSingleAuthorization($account, 'wizard');
    $savedData = [
      0 => ['value' => 'student'],
      1 => ['value' => 'gryffindor'],
      2 => ['value' => 'wizard'],
    ];
    self::assertEquals($account->get('authorization_drupal_roles_roles')->getValue(), $savedData);
  }

  /**
   * Previously granted roles are removed, when no longer applicable.
   */
  public function testRevocation(): void {
    $profile = AuthorizationProfile::create([
      'status' => 'true',
      'description' => 'test',
      'id' => 'test',
      'provider' => 'dummy',
      'consumer' => 'authorization_drupal_roles',
    ]);
    $consumer = $profile->getConsumer();

    $account = User::create(['name' => 'hpotter']);
    $roles_granted = ['student', 'gryffindor', 'staff'];
    $account->set('authorization_drupal_roles_roles', $roles_granted);
    $account->addRole('student');
    $account->addRole('gryffindor');
    $account->addRole('staff');

    // User was in student, gryffindor and is now only in staff, according to
    // the provider and removal is presumed enabled.
    $active_roles = ['staff'];
    $consumer->revokeGrants($account, $active_roles);
    self::assertEquals([0 => ['value' => 'staff']], $account->get('authorization_drupal_roles_roles')->getValue());
    self::assertEquals(['anonymous', 'staff'], $account->getRoles());
  }

  /**
   * Test form building.
   */
  public function testForm(): void {
    $profile = AuthorizationProfile::create([
      'status' => 'true',
      'description' => 'test',
      'id' => 'test',
      'provider' => 'dummy',
      'consumer' => 'authorization_drupal_roles',
    ]);
    $profile->setConsumerMappings(
      [
        ['role' => 'test0'],
        ['role' => 'test1'],
        ['role' => 'test2'],
      ]);
    $consumer = $profile->getConsumer();

    $form = [];
    $formState = new FormState();
    $form = $consumer->buildConfigurationForm($form, $formState);
    self::assertStringContainsString('no settings', (string) $form['description']['#markup']);
    $roleId = $this->drupalCreateRole([]);
    $form = [];
    $formState = new FormState();
    $row = $consumer->buildRowForm($form, $formState, 2);
    self::assertStringContainsString('test2', $row['role']['#default_value']);
    self::assertArrayHasKey($roleId, $row['role']['#options']);
  }

  /**
   * Test filterProposals and sanitization.
   */
  public function testFilterProposals(): void {
    $profile = AuthorizationProfile::create([
      'status' => 'true',
      'description' => 'test',
      'id' => 'test',
      'provider' => 'dummy',
      'consumer' => 'authorization_drupal_roles',
    ]);
    $consumer = $profile->getConsumer();

    $proposals = [
      'student' => 'student',
      'user' => 'user',
    ];

    // Wildcard (getWildcard() also covered with this).
    $consumerMapping = [
      'role' => 'source',
    ];
    $result = $consumer->filterProposals($proposals, $consumerMapping);
    $this->assertEquals($proposals, $result);

    // Match for single proposal.
    $consumerMapping = [
      'role' => 'staff',
    ];
    $result = $consumer->filterProposals($proposals, $consumerMapping);
    $this->assertEquals(['staff' => 'staff'], $result);

    // Invalid role.
    $consumerMapping = [
      'role' => 'none',
    ];
    $result = $consumer->filterProposals($proposals, $consumerMapping);
    $this->assertEquals([], $result);

    // No proposals.
    $proposals = [];
    $consumerMapping = [
      'role' => 'student',
    ];
    $result = $consumer->filterProposals($proposals, $consumerMapping);
    $this->assertEquals([], $result);

    $consumer->createConsumerTarget('Complex ?targêt');
    $role = Role::load('complex_target');
    self::assertEquals('Complex ?targêt', $role->label());
  }

}
