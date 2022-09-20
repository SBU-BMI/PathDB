<?php

declare(strict_types = 1);

namespace Drupal\Tests\authorization\Kernel;

use Drupal\authorization\Entity\AuthorizationProfile;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Test description.
 *
 * @group authorization
 */
class ControllerTest extends EntityKernelTestBase {

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
    ]);
    $profile->setProviderMappings([['query' => 'student']]);
    $profile->setConsumerMappings([['role' => 'student']]);
    $profile->set('synchronization_actions', []);
    $profile->set('synchronization_modes', ['user_logon', 'user_logon']);
    $profile->save();
  }

  /**
   * Test processing authorizations.
   */
  public function testController(): void {
    $user = $this->createUser();
    $user->save();

    $user->proposals = ['student'];
    /** @var \Drupal\authorization\AuthorizationController $controller */
    $controller = $this->container->get('authorization.manager');
    $controller->setUser($user);
    $controller->queryAllProfiles();
    $authorizations = $controller->getProcessedAuthorizations();
    $authorization = reset($authorizations);
    self::assertArrayHasKey('student', $authorization->getAuthorizationsApplied());

    $controller->clearAuthorizations();
    $user->proposals = ['exception'];
    $controller->queryAllProfiles();
    $authorizations = $controller->getProcessedAuthorizations();
    $authorization = reset($authorizations);
    self::assertEmpty($authorization->getAuthorizationsApplied());
    self::assertEquals(TRUE, $authorization->getSkipped());
  }

}
