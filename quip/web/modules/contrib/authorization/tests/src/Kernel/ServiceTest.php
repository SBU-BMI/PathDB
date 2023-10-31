<?php

declare(strict_types=1);

namespace Drupal\Tests\authorization\Kernel;

use Drupal\authorization\Entity\AuthorizationProfile;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Test description.
 *
 * @group authorization
 */
class ServiceTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'authorization',
    'authorization_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
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
    $profile->setProviderMappings([['query' => 'student']]);
    $profile->setConsumerMappings([['role' => 'student']]);
    $profile->save();
  }

  /**
   * Test processing authorizations.
   */
  public function testService(): void {
    $user = $this->createUser();
    $user->save();

    $user->proposals = ['student'];
    /** @var \Drupal\authorization\AuthorizationServiceInterface $service */
    $service = $this->container->get('authorization.manager');
    $service->setUser($user);
    $service->queryAllProfiles();
    $authorizations = $service->getProcessedAuthorizations();
    $authorization = reset($authorizations);
    self::assertArrayHasKey('student', $authorization->getAuthorizationsApplied());

    $service->clearAuthorizations();
    $user->proposals = ['exception'];
    $service->queryAllProfiles();
    $authorizations = $service->getProcessedAuthorizations();
    $authorization = reset($authorizations);
    self::assertEmpty($authorization->getAuthorizationsApplied());
    self::assertEquals(TRUE, $authorization->getSkipped());
  }

}
