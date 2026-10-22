<?php

namespace Drupal\Tests\externalauth\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Verifies that binary-safe authmap fields preserve exact identity matching.
 *
 * The fix consists of two layers:
 *  1. Schema: authmap.authname and authmap.provider are declared with
 *     'binary' => TRUE so the database uses a byte-exact collation.
 *  2. Runtime: Authmap::getUid() performs a PHP === comparison on the stored
 *     values before returning a UID (defense-in-depth for sites not yet
 *     migrated).
 *
 * These tests exercise both layers on all supported database backends by
 * relying solely on the installed schema.
 *
 * @group externalauth
 *
 * @see externalauth_update_8104()
 * @see \Drupal\externalauth\Authmap::getUid()
 */
class ExternalauthCollationFixTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'externalauth',
  ];

  /**
   * Provider string used across all test methods.
   */
  private const PROVIDER = 'test_provider';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('externalauth', ['authmap']);
  }

  /**
   * Verifies getUid() keeps byte-distinct authnames separate.
   *
   * On all supported DB backends 'user1' and 'üser1' differ at the byte level
   * and must never compare equal.
   */
  public function testBinarySchemaRejectsCollationEquivalentAuthname(): void {
    /** @var \Drupal\externalauth\ExternalAuthInterface $externalAuth */
    $externalAuth = $this->container->get('externalauth.externalauth');
    /** @var \Drupal\externalauth\AuthmapInterface $authmap */
    $authmap = $this->container->get('externalauth.authmap');

    $originalAuthname = 'user1';
    // 'ü' (U+00FC) folds to 'u' under utf8mb4_unicode_ci / utf8mb4_general_ci
    // but must remain distinct under binary collation.
    $alternativeAuthname = 'üser1';

    $this->assertNotSame(
      $originalAuthname,
      $alternativeAuthname,
      'Pre-condition: authnames are byte-distinct.'
    );

    $originalAccount = $externalAuth->register(
      $originalAuthname,
      self::PROVIDER,
      ['name' => 'original_user_accent'],
    );
    $originalUid = (int) $originalAccount->id();
    $this->assertGreaterThan(0, $originalUid);

    // The original authname must resolve correctly.
    $this->assertSame(
      $originalUid,
      (int) $authmap->getUid($originalAuthname, self::PROVIDER),
      'Original authname resolves to correct UID.'
    );

    // A distinct accent-variant authname must not resolve to this mapping.
    $this->assertFalse(
      $authmap->getUid($alternativeAuthname, self::PROVIDER),
      'FIX VERIFIED: accent-variant authname resolves to FALSE.'
    );
  }

  /**
   * Verifies getUid() treats case variants as distinct authnames.
   *
   * Binary columns make authname lookups case-sensitive.
   */
  public function testBinarySchemaRejectsCaseEquivalentAuthname(): void {
    /** @var \Drupal\externalauth\ExternalAuthInterface $externalAuth */
    $externalAuth = $this->container->get('externalauth.externalauth');
    /** @var \Drupal\externalauth\AuthmapInterface $authmap */
    $authmap = $this->container->get('externalauth.authmap');

    $externalAuth->register(
      'admin_user',
      self::PROVIDER,
      ['name' => 'original_user_case'],
    );
    $originalUid = (int) $authmap->getUid('admin_user', self::PROVIDER);
    $this->assertGreaterThan(0, $originalUid);

    // A distinct case-variant authname must not match.
    $this->assertFalse(
      $authmap->getUid('Admin_User', self::PROVIDER),
      'FIX VERIFIED: case-variant authname resolves to FALSE under binary '
      . 'collation.'
    );
  }

  /**
   * Verifies loginRegister() creates a separate account.
   *
   * A byte-distinct authname should produce a separate account mapping.
   */
  public function testLoginRegisterCreatesNewAccountForDistinctAuthname(): void {
    /** @var \Drupal\externalauth\ExternalAuthInterface $externalAuth */
    $externalAuth = $this->container->get('externalauth.externalauth');
    /** @var \Drupal\externalauth\AuthmapInterface $authmap */
    $authmap = $this->container->get('externalauth.authmap');

    $originalAccount = $externalAuth->register(
      'user2',
      self::PROVIDER,
      ['name' => 'original_user_login_register'],
    );
    $originalUid = (int) $originalAccount->id();

    // loginRegister() with a byte-distinct authname must produce a new account.
    // Explicit Drupal usernames avoid unrelated normalization issues in user
    // storage when deriving local account names.
    $alternativeAccount = $externalAuth->loginRegister(
      'üser2',
      self::PROVIDER,
      ['name' => 'alternative_user_login_register'],
    );

    $this->assertNotFalse(
      $alternativeAccount,
      'A new account was created for the byte-distinct authname.'
    );
    $this->assertNotSame(
      $originalUid,
      (int) $alternativeAccount->id(),
      'FIX VERIFIED: byte-distinct authname produced a new account UID.'
    );

    // Both authnames must have independent authmap rows.
    $this->assertSame(
      $originalUid,
      (int) $authmap->getUid('user2', self::PROVIDER),
      'Original authname still maps to the original UID.'
    );
    $this->assertSame(
      (int) $alternativeAccount->id(),
      (int) $authmap->getUid('üser2', self::PROVIDER),
      'Byte-distinct authname maps to the new, separate account UID.'
    );
  }

}
