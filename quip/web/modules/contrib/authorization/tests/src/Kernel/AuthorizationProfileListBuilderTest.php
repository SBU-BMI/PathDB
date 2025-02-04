<?php

namespace Drupal\Tests\authorization\Kernel;

use Drupal\Core\Language\LanguageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\authorization\Entity\AuthorizationProfile;

/**
 * Tests the admin listing fallback when views is not enabled.
 *
 * @group authorization
 */
class AuthorizationProfileListBuilderTest extends KernelTestBase {

  /**
   * The list builder.
   *
   * @var \Drupal\Core\Entity\EntityListBuilderInterface
   */
  protected $listBuilder;

  /**
   * {@inheritdoc}
   */
  protected $container;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'authorization_test',
    'authorization',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->listBuilder = $this->container->get('entity_type.manager')
      ->getListBuilder('authorization_profile');
  }

  /**
   * Tests that the correct cache contexts are set.
   */
  public function testCacheContexts() {

    $build = $this->listBuilder->render();
    $this->container->get('renderer')->renderRoot($build);

    $this->assertEqualsCanonicalizing([
      'languages:' . LanguageInterface::TYPE_INTERFACE,
      'theme',
      'url.query_args.pagers:0',
      'user.permissions',
    ], $build['#cache']['contexts']);
  }

  /**
   * Tests buildRow().
   */
  public function testBuildRow() {

    $profile = new AuthorizationProfile([
      'id' => 'test',
      'label' => 'Test profile',
      'description' => 'Test profile',
      'status' => 'true',
      'provider' => 'dummy',
      'provider_config' => [],
      'consumer' => 'dummy',
      'consumer_config' => [],
      'synchronization_modes' => [
        'user_logon' => 'user_logon',
      ],
      'synchronization_actions' => [],
    ], 'authorization_profile');
    $profile->save();

    $row = $this->listBuilder->buildRow($profile);

    $this->assertCount(5, $row);
    $this->assertEquals('Test profile', $row['label']);
    $this->assertEquals('dummy', $row['provider']);
    $this->assertEquals('dummy', $row['consumer']);
    $this->assertEquals('Yes', $row['enabled']);

  }

}
