<?php

namespace Drupal\Tests\authorization\Unit\Service;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\authorization\AuthorizationProfileInterface;
use Drupal\authorization\AuthorizationResponse;
use Drupal\authorization\Service\AuthorizationService;
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
   * @covers ::processAuthorizations
   */
  public function testSetIndividualProfile(): void {
    $profile_id = 'example_profile_id';

    $profile_storage = $this->createMock(ConfigEntityStorageInterface::class);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('authorization_profile')
      ->willReturn($profile_storage);

    $authorization_profile = $this->createMock(AuthorizationProfileInterface::class);

    $profile_storage->expects($this->once())
      ->method('load')
      ->with($profile_id)
      ->willReturn($authorization_profile);

    $this->authorizationService->setIndividualProfile($profile_id);

    $processedAuthorizations = $this->authorizationService->getProcessedAuthorizations();

    $this->assertEquals([], $processedAuthorizations);
  }

  /**
   * Tests the setIndividualProfile() method when the profile is not found.
   *
   * @covers ::setIndividualProfile
   * @covers ::processAuthorizations
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

  /**
   * Tests the clearAuthorizations() method.
   *
   * @covers ::clearAuthorizations
   */
  public function testClearAuthorizations() {
    $this->authorizationService->clearAuthorizations();
    $processedAuthorizations = $this->authorizationService->getProcessedAuthorizations();
    $this->assertEquals([], $processedAuthorizations);
  }

  /**
   * Tests the setAllProfiles() method.
   *
   * @covers ::setAllProfiles
   */
  public function testSetAllProfiles() {
    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('accessCheck')
      ->with(FALSE)
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('execute')
      ->willReturn(['example_profile' => 'example_profile']);

    $authorization_profile = $this->createMock(AuthorizationProfileInterface::class);
    $authorization_profile->expects($this->once())
      ->method('checkConditions')
      ->willReturn(FALSE);
    $profile_storage = $this->createMock(ConfigEntityStorageInterface::class);
    $profile_storage->expects($this->once())
      ->method('getQuery')
      ->willReturn($query);
    $profile_storage->expects($this->once())
      ->method('load')
      ->with('example_profile')
      ->willReturn($authorization_profile);

    $this->entityTypeManager->expects($this->exactly(2))
      ->method('getStorage')
      ->with('authorization_profile')
      ->willReturn($profile_storage);

    $this->authorizationService->setAllProfiles();

    $processedAuthorizations = $this->authorizationService->getProcessedAuthorizations();
    $this->assertEquals([], $processedAuthorizations);
  }

  /**
   * Tests the queryAllProfiles() method.
   *
   * @covers ::queryAllProfiles
   */
  public function testQueryAllProfiles() {
    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('accessCheck')
      ->with(FALSE)
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('execute')
      ->willReturn([
        'example_profile' => 'example_profile',
        'example_profile_2' => 'example_profile_2',
      ]);

    $authorization_profile = $this->createMock(AuthorizationProfileInterface::class);
    $authorization_profile->expects($this->once())
      ->method('checkConditions')
      ->willReturn(FALSE);
    $authorization_profile_2 = $this->createMock(AuthorizationProfileInterface::class);
    $authorization_profile_2->expects($this->once())
      ->method('checkConditions')
      ->willReturn(FALSE);
    $profile_storage = $this->createMock(ConfigEntityStorageInterface::class);
    $profile_storage->expects($this->once())
      ->method('getQuery')
      ->willReturn($query);
    $profile_storage->expects($this->exactly(2))
      ->method('load')
      ->willReturnOnConsecutiveCalls($authorization_profile, $authorization_profile_2);

    $this->entityTypeManager->expects($this->exactly(3))
      ->method('getStorage')
      ->with('authorization_profile')
      ->willReturn($profile_storage);

    $this->authorizationService->queryAllProfiles();

    $processedAuthorizations = $this->authorizationService->getProcessedAuthorizations();
    $this->assertEquals([], $processedAuthorizations);
  }

  /**
   * Tests the queryIndividualProfile() method.
   *
   * @covers ::queryIndividualProfile
   * @covers ::processAuthorizations
   */
  public function testQueryIndividualProfile() {
    $user = $this->createMock(UserInterface::class);
    $this->authorizationService->setUser($user);

    $response = $this->createMock(AuthorizationResponse::class);
    $profile_id = 'example_profile_id';
    $profile_storage = $this->createMock(ConfigEntityStorageInterface::class);

    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('authorization_profile')
      ->willReturn($profile_storage);

    $authorization_profile = $this->createMock(AuthorizationProfileInterface::class);
    $authorization_profile->expects($this->once())
      ->method('checkConditions')
      ->willReturn(TRUE);
    $authorization_profile->expects($this->once())
      ->method('grantsAndRevokes')
      ->willReturn($response);

    $profile_storage->expects($this->once())
      ->method('load')
      ->with($profile_id)
      ->willReturn($authorization_profile);

    $this->authorizationService->queryIndividualProfile($profile_id);

    $processedAuthorizations = $this->authorizationService->getProcessedAuthorizations();
    $this->assertCount(1, $processedAuthorizations);
  }

  /**
   * Tests the queryIndividualProfile() method when the profile is not found.
   */
  public function testQueryIndividualProfileNotFound() {
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

    $this->authorizationService->queryIndividualProfile($profileId);

    $processedAuthorizations = $this->authorizationService->getProcessedAuthorizations();
    $this->assertEquals([], $processedAuthorizations);
  }

}
