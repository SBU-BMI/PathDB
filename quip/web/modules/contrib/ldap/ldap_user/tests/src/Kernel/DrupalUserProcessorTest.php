<?php

declare(strict_types = 1);

namespace Drupal\Tests\ldap_user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ldap_servers\LdapUserAttributesInterface;
use Drupal\user\Entity\User;

/**
 * Tests for the DrupalUserProcessor.
 *
 * @coversDefaultClass \Drupal\ldap_user\Processor\DrupalUserProcessor
 * @group ldap
 */
class DrupalUserProcessorTest extends KernelTestBase implements LdapUserAttributesInterface {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'externalauth',
    'ldap_servers',
    'ldap_user',
    'ldap_query',
    'ldap_authentication',
    'user',
    'system',
  ];

  /**
   * Drupal User Processor.
   *
   * @var \Drupal\ldap_user\Processor\DrupalUserProcessor
   */
  private $drupalUserProcessor;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  private $entityTypeManager;

  /**
   * Setup of kernel tests.
   */
  public function setUp(): void {
    parent::setUp();

    $this->installConfig(['ldap_authentication']);
    $this->installConfig(['ldap_user']);
    $this->installConfig(['user']);
    $this->drupalUserProcessor = $this->container->get('ldap.drupal_user_processor');
    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Tests user exclusion for the authentication helper.
   */
  public function testUserExclusion(): void {

    // Skip administrators, if so configured.
    /** @var \Drupal\user\Entity\User $account */
    $account = $this->prophesize(User::class);
    $account->getRoles()->willReturn(['administrator']);
    $account->id()->willReturn(1);
    $value = new \stdClass();
    $value->value = '';
    $account->get('ldap_user_ldap_exclude')->willReturn($value);
    $this->entityTypeManager
      ->getStorage('user_role')
      ->create([
        'id' => 'administrator',
        'label' => 'Administrators',
        'is_admin' => TRUE,
      ])
      ->save();
    $admin_roles = $this->entityTypeManager
      ->getStorage('user_role')
      ->getQuery()
      ->condition('is_admin', TRUE)
      ->execute();
    self::assertNotEmpty($admin_roles);
    self::assertTrue($this->drupalUserProcessor->excludeUser($account->reveal()));
    $this->config('ldap_authentication.settings')->set('skipAdministrators', 0)->save();
    self::assertFalse($this->drupalUserProcessor->excludeUser($account->reveal()));

    // Disallow checkbox exclusion (everyone else allowed).
    $account = $this->prophesize(User::class);
    $account->getRoles()->willReturn(['']);
    $account->id()->willReturn(2);
    $value = new \stdClass();
    $value->value = 1;
    $account->get('ldap_user_ldap_exclude')->willReturn($value);
    self::assertTrue($this->drupalUserProcessor->excludeUser($account->reveal()));

    // Everyone else allowed.
    $account = $this->prophesize(User::class);
    $account->getRoles()->willReturn(['']);
    $account->id()->willReturn(2);
    $value = new \stdClass();
    $value->value = '';
    $account->get('ldap_user_ldap_exclude')->willReturn($value);
    self::assertFalse($this->drupalUserProcessor->excludeUser($account->reveal()));
  }

  /**
   * Test that creating users with createDrupalUserFromLdapEntry() works.
   */
  public function testProvisioning(): void {
    self::markTestIncomplete('Broken test');
    $result = $this->drupalUserProcessor->createDrupalUserFromLdapEntry(['name' => 'hpotter']);
    self::assertTrue($result);
    $user = $this->drupalUserProcessor->getUserAccount();
    // Override the server factory to provide a dummy server.
    self::assertInstanceOf(User::class, $user);
    // @todo Does not work since getUserDataFromServerByIdentifier() loads
    // live data and the server is missing.
    // @todo Amend test scenario to user update, user insert, user delete.
    // @todo Amend test scenario to log user in, i.e. drupalUserLogsIn().
  }

  // @todo Write test to show that syncing to existing Drupal users works.
  // @todo Write a test showing that a constant value gets passend on
  // correctly, i.e. ldap_attr is "Faculty" instead of [type].
  // @todo Write a test validating compound tokens, i.e. ldap_attr is
  // '[cn]@hogwarts.edu' or '[givenName] [sn]'.
  // @todo Write a test validating multiple mail properties, i.e. [mail]
  // returns the following and we get both:
  // [['mail' => 'hpotter@hogwarts.edu'], ['mail' => 'hpotter@owlmail.com']].
  // @todo Write a test validating non-integer values on the account status.
  // @todo Write a test for applyAttributes for binary fields.
  // @todo Write a test for applyAttributes for case sensitivity in tokens.
  // @todo Write a test for applyAttributes for user_attr in mappings.
  // @todo Write a test to prove puid update works, with and without binary mode
  // and including a conflicting account.
}
