<?php

declare(strict_types=1);

namespace Drupal\Tests\authorization\Kernel\Entity;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\authorization\Entity\AuthorizationProfile;

/**
 * Test Authorization Profile.
 *
 * @group authorization
 */
class AuthorizationProfileTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'authorization',
    'authorization_test',
  ];

  /**
   * Test Profile getDescription().
   */
  public function testProfileProperties() {
    $profile = AuthorizationProfile::create([
      'id' => 'test',
      'label' => 'Test profile',
      'status' => 'true',
      'provider' => 'dummy',
      'consumer' => 'dummy',
      'synchronization_modes' => [
        'user_logon' => 'user_logon',
      ],
      'synchronization_actions' => [],
    ]);

    $this->assertTrue($profile->hasValidProvider());
    $this->assertTrue($profile->hasValidConsumer());

    $profile->setProviderConfig(['test' => 'test']);
    $this->assertEquals(['test' => 'test'], $profile->getProviderConfig());

    $profile->setConsumerConfig(['test' => 'test']);
    $this->assertEquals(['test' => 'test'], $profile->getConsumerConfig());
  }

  /**
   * Test invalid plugins.
   */
  public function testInvalidPlugins() {
    $profile = AuthorizationProfile::create([
      'id' => 'test',
      'label' => 'Test profile',
      'description' => 'Test profile',
      'status' => 'true',
      'provider' => 'invalid',
      'consumer' => 'invalid',
      'synchronization_modes' => [
        'user_logon' => 'user_logon',
      ],
      'synchronization_actions' => [],
    ]);

    $this->assertFalse($profile->hasValidProvider());
    $this->assertFalse($profile->hasValidConsumer());
  }

  /**
   * Test checkConditions().
   */
  public function testCheckConditions() {
    $profile = AuthorizationProfile::create([
      'id' => 'test',
      'label' => 'Test profile',
      'description' => 'Test profile',
      'status' => FALSE,
      'provider' => NULL,
      'consumer' => NULL,
      'synchronization_modes' => [
        'user_logon' => 'user_logon',
      ],
      'synchronization_actions' => [],
    ]);

    $this->assertFalse($profile->checkConditions());
    $profile->set('status', TRUE);
    $this->assertFalse($profile->checkConditions());
    $profile->set('provider', 'dummy');
    $this->assertFalse($profile->checkConditions());
    $profile->set('consumer', 'dummy');
    $this->assertTrue($profile->checkConditions());
  }

  /**
   * Test getTokens().
   */
  public function testGetTokens() {
    $profile = AuthorizationProfile::create([
      'id' => 'test',
      'label' => 'Test profile',
      'description' => 'Test profile',
      'status' => 'true',
      'provider' => 'dummy',
      'consumer' => 'dummy',
      'synchronization_modes' => [
        'user_logon' => 'user_logon',
      ],
      'synchronization_actions' => [],
    ]);

    $this->assertEquals(['@profile_name' => 'Test profile'], $profile->getTokens());
  }

}
