<?php

declare(strict_types=1);

namespace Drupal\Tests\authorization\Kernel\Service;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\authorization\Entity\AuthorizationProfile;

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
      'synchronization_actions' => [
        'create_consumers' => TRUE,
        'revoke_provider_provisioned' => TRUE,
      ],
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
    $gotten_user = $service->getUser();
    $this->assertEquals($user->id(), $gotten_user->id());
    $service->queryAllProfiles();
    $authorizations = $service->getProcessedAuthorizations();
    $authorization = reset($authorizations);
    $this->assertArrayHasKey('student', $authorization->getAuthorizationsApplied());

    $service->clearAuthorizations();
    $user->proposals = ['exception'];
    $service->queryAllProfiles();
    $authorizations = $service->getProcessedAuthorizations();
    $authorization = reset($authorizations);
    $this->assertEmpty($authorization->getAuthorizationsApplied());
    $this->assertEquals(TRUE, $authorization->getSkipped());
    $this->assertEquals('Test profile (skipped)', $authorization->getMessage());
  }

  /**
   * Test queryIndividualProfile.
   */
  public function testQueryIndividualProfileMissing() {
    $user = $this->createUser();
    $user->save();

    /** @var \Drupal\authorization\AuthorizationServiceInterface $service */
    $service = $this->container->get('authorization.manager');
    $service->setUser($user);
    $service->queryIndividualProfile('missing');
    $authorizations = $service->getProcessedAuthorizations();
    $authorization = reset($authorizations);
    $this->assertEmpty($authorization);
  }

  /**
   * Test setIndividualProfile. Profile is missing.
   */
  public function testSetIndividualProfileMissing() {
    $user = $this->createUser();
    $user->save();

    /** @var \Drupal\authorization\AuthorizationServiceInterface $service */
    $service = $this->container->get('authorization.manager');
    $service->setUser($user);
    $service->setIndividualProfile('missing');
    $authorizations = $service->getProcessedAuthorizations();
    $authorization = reset($authorizations);
    $this->assertEmpty($authorization);
  }

  /**
   * Test setAllProfiles.
   */
  public function testSetAllProfiles() {
    $user = $this->createUser();
    $user->save();

    $user->proposals = ['student'];
    /** @var \Drupal\authorization\AuthorizationServiceInterface $service */
    $service = $this->container->get('authorization.manager');
    $service->setUser($user);
    $service->setAllProfiles();
    $authorizations = $service->getProcessedAuthorizations();
    $authorization = reset($authorizations);
    $this->assertArrayHasKey('student', $authorization->getAuthorizationsApplied());
  }

}
