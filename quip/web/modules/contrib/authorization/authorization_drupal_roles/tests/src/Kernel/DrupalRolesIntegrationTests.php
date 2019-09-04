<?php

namespace Drupal\Tests\authorization_drupal_user\Kernel;

use Drupal\authorization_drupal_roles\Plugin\authorization\Consumer\DrupalRolesConsumer;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\user\Entity\User;

/**
 * Integration tests for authorization_drupal_roles.
 *
 * @group authorization
 */
class DrupalRolesIntegrationTests extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'user',
    'system',
    'field',
    'text',
    'filter',
    'entity_test',
    'authorization',
    'authorization_drupal_roles',
  ];

  /**
   * Consumer plugin.
   *
   * @var \Drupal\authorization_drupal_roles\Plugin\authorization\Consumer\DrupalRolesConsumer
   */
  protected $consumerPlugin;

  /**
   * Setup of kernel tests.
   */
  public function setUp() {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(['field', 'text']);

    $this->consumerPlugin = $this->getMockBuilder(DrupalRolesConsumer::class)
      ->disableOriginalConstructor()
      ->setMethods(NULL)
      ->getMock();
  }

  /**
   * Tests granting new roles.
   */
  public function testGrant() {
    $account = User::create(['name' => 'hpotter']);
    $account->set('authorization_drupal_roles_roles', ['student', 'gryffindor']);
    $this->consumerPlugin->grantSingleAuthorization($account, 'student');
    $savedData = [0 => ['value' => 'student'], 1 => ['value' => 'gryffindor']];

    // No duplicate information in authorization record, only one role because
    // nothing else assigned, yet.
    $this->assertEquals($account->get('authorization_drupal_roles_roles')->getValue(), $savedData);
    $this->assertEquals($account->getRoles(), ['anonymous', 'student']);

    $this->consumerPlugin->grantSingleAuthorization($account, 'wizard');
    $savedData = [
      0 => ['value' => 'student'],
      1 => ['value' => 'gryffindor'],
      2 => ['value' => 'wizard'],
    ];
    $this->assertEquals($account->get('authorization_drupal_roles_roles')->getValue(), $savedData);
  }

  /**
   * Previously granted roles are removed, when no longer applicable.
   */
  public function testRevocation() {
    $account = User::create(['name' => 'hpotter']);
    $roles_granted = ['student', 'gryffindor', 'staff'];
    $account->set('authorization_drupal_roles_roles', $roles_granted);
    $account->addRole('student');
    $account->addRole('gryffindor');
    $account->addRole('staff');

    // User was in student, gryffindor and is now only in staff, according to
    // the provider and removal is presumed enabled.
    $active_roles = ['staff'];
    $this->consumerPlugin->revokeGrants($account, $active_roles);
    $this->assertEquals($account->get('authorization_drupal_roles_roles')->getValue(), [0 => ['value' => 'staff']]);
    $this->assertEquals($account->getRoles(), ['anonymous', 'staff']);
  }

}
