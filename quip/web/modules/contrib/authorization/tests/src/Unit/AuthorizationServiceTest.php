<?php

namespace Drupal\Tests\authorization\Unit\Service;

use Drupal\authorization\Entity\AuthorizationProfile;
use Drupal\authorization\Service\AuthorizationService;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests AuthorizationService.
 *
 * @coversDefaultClass \Drupal\authorization\Service\AuthorizationService
 *
 * @group authorization
 */
class AuthorizationServiceTest extends TestCase {

  /**
   * The Authorization service.
   *
   * @var \Drupal\authorization\Service\AuthorizationService
   */
  protected $authorizationService;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    $this->authorizationService = new AuthorizationService($this->entityTypeManager, $this->logger);
  }

  /**
   * Tests setUser() and getUser() methods.
   *
   * @covers ::setUser
   * @covers ::getUser
   */
  public function testSetUserAndGetUser(): void {
    $user = $this->createMock(UserInterface::class);
    $this->authorizationService->setUser($user);

    $this->assertSame($user, $this->authorizationService->getUser());
  }

  /**
   * Tests setIndividualProfile() method.
   *
   * @covers ::setIndividualProfile
   */
  public function testSetIndividualProfile(): void {
    $profileId = 'example_profile_id';

    $profile_storage = $this->createMock(ConfigEntityStorageInterface::class);

    $profile_storage->expects($this->once())
      ->method('load')
      ->with($profileId)
      ->willReturn(NULL);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('authorization_profile')
      ->willReturn($profile_storage);

    $authorization_profile = $this->createMock(AuthorizationProfile::class);

    $profile_storage->expects($this->once())
      ->method('load')
      ->with($profileId)
      ->willReturn($authorization_profile);

    $this->authorizationService->setIndividualProfile($profileId);

    $processedAuthorizations = $this->authorizationService->getProcessedAuthorizations();

    $this->assertEquals([], $processedAuthorizations);
  }

  /**
   * Tests the setIndividualProfile() method when the profile is not found.
   */
  public function testSetIndividualProfileNotFound(): void {
    $user = $this->createMock(UserInterface::class);
    $this->authorizationService->setUser($user);

    $profileId = 'example_profile_id';
    $profile_storage = $this->createMock(ConfigEntityStorageInterface::class);

    $profile_storage->expects($this->once())
      ->method('load')
      ->with($profileId)
      ->willReturn(NULL);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('authorization_profile')
      ->willReturn($profile_storage);

    $this->logger->expects($this->once())
      ->method('error')
      ->with('Profile @profile could not be loaded.', ['@profile' => $profileId]);

    $this->authorizationService->setIndividualProfile($profileId);

    $processedAuthorizations = $this->authorizationService->getProcessedAuthorizations();
    $this->assertEquals([], $processedAuthorizations);
  }

}
