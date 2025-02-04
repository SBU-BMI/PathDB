<?php

declare(strict_types=1);

namespace Drupal\Tests\authorization\Unit\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\authorization\AuthorizationSkipAuthorization;
use Drupal\authorization\Consumer\ConsumerInterface;
use Drupal\authorization\Entity\AuthorizationProfile;
use Drupal\authorization\Provider\ProviderInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the AuthorizationProfile entity.
 *
 * @coversDefaultClass \Drupal\authorization\Entity\AuthorizationProfile
 *
 * @group authorization
 */
class AuthorizationProfileTest extends UnitTestCase {

  /**
   * The provider plugin manager.
   *
   * @var \Drupal\authorization\Provider\ProviderPluginManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $providerPlugin;

  /**
   * The consumer plugin manager.
   *
   * @var \Drupal\authorization\Consumer\ConsumerPluginManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $consumerPlugin;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();
    $this->providerPlugin = $this->createMock('\Drupal\authorization\Provider\ProviderPluginManager');
    $this->consumerPlugin = $this->createMock('\Drupal\authorization\Consumer\ConsumerPluginManager');
    $entity_type_repository = $this->createMock('\Drupal\Core\Entity\EntityTypeRepositoryInterface');
    $this->entityTypeManager = $this->createMock('\Drupal\Core\Entity\EntityTypeManagerInterface');
    $logger = $this->createMock('\Psr\Log\LoggerInterface');
    $string_translation = $this->getStringTranslationStub();

    $container->set('plugin.manager.authorization.provider', $this->providerPlugin);
    $container->set('plugin.manager.authorization.consumer', $this->consumerPlugin);
    $container->set('entity_type.repository', $entity_type_repository);
    $container->set('entity_type.manager', $this->entityTypeManager);
    $container->set('logger.channel.authorization', $logger);
    $container->set('string_translation', $string_translation);

    \Drupal::setContainer($container);
  }

  /**
   * Tests the getName() and setName() methods.
   */
  public function testGetNameAndSetName(): void {
    $label = 'Example Profile Name';
    $profile = new AuthorizationProfile([
      'label' => $label,
    ], 'authorization_profile');

    $this->assertSame($label, $profile->get('label'));
  }

  /**
   * Tests hasValidProvider().
   *
   * @covers ::hasValidProvider
   */
  public function testHasValidProviderFalse(): void {
    $provider_id = 'example_provider';
    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'provider' => $provider_id,
    ], 'authorization_profile');
    $this->assertFalse($profile->hasValidProvider());
  }

  /**
   * Tests hasValidProvider().
   *
   * @covers ::hasValidProvider
   */
  public function testHasValidProvider(): void {
    $provider_id = 'example_provider';
    $this->providerPlugin->expects($this->any())
      ->method('getDefinition')
      ->with($provider_id, FALSE)
      ->willReturn(TRUE);
    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'provider' => $provider_id,
    ], 'authorization_profile');
    $this->assertTrue($profile->hasValidProvider());
  }

  /**
   * Tests hasValidConsumer().
   *
   * @covers ::hasValidConsumer
   */
  public function testHasValidConsumerFalse(): void {
    $consumer_id = 'example_consumer';
    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'consumer' => $consumer_id,
    ], 'authorization_profile');
    $this->assertFalse($profile->hasValidConsumer());
  }

  /**
   * Tests hasValidConsumer().
   *
   * @covers ::hasValidConsumer
   */
  public function testHasValidConsumer(): void {
    $consumer_id = 'example_consumer';
    $this->consumerPlugin->expects($this->any())
      ->method('getDefinition')
      ->with($consumer_id, FALSE)
      ->willReturn(TRUE);

    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'consumer' => $consumer_id,
    ], 'authorization_profile');
    $this->assertTrue($profile->hasValidConsumer());
  }

  /**
   * Tests getProviderId().
   *
   * @covers ::getProviderId
   */
  public function testGetProviderId() {
    $provider_id = 'example_provider';
    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'provider' => $provider_id,
    ], 'authorization_profile');
    $this->assertSame($provider_id, $profile->getProviderId());
  }

  /**
   * Tests getConsumerId().
   *
   * @covers ::getConsumerId
   */
  public function testGetConsumerId() {
    $consumer_id = 'example_consumer';
    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'consumer' => $consumer_id,
    ], 'authorization_profile');
    $this->assertSame($consumer_id, $profile->getConsumerId());
  }

  /**
   * Tests getProvider().
   *
   * @covers ::getProvider
   * @covers ::loadProviderPlugin
   */
  public function testGetProvider() {
    $provider_id = 'example_provider';
    $provider = $this->createMock(ProviderInterface::class);
    $this->providerPlugin->expects($this->any())
      ->method('createInstance')
      ->with($provider_id)
      ->willReturn($provider);

    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'provider' => $provider_id,
    ], 'authorization_profile');
    $this->assertSame($provider, $profile->getProvider());
  }

  /**
   * Tests getProvider().
   *
   * @covers ::getProvider
   * @covers ::loadProviderPlugin
   */
  public function testGetProviderException() {
    $entity_type = $this->createMock(EntityTypeInterface::class);
    $this->entityTypeManager->expects($this->once())
      ->method('getDefinition')
      ->with('authorization_profile')
      ->willReturn($entity_type);

    $provider_id = 'example_provider';
    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'provider' => $provider_id,
    ], 'authorization_profile');

    $this->providerPlugin->expects($this->any())
      ->method('createInstance')
      ->with($provider_id, ['profile' => $profile])
      ->willThrowException(new \Exception());

    $profile->getProvider();
  }

  /**
   * Tests getConsumer().
   *
   * @covers ::getConsumer
   * @covers ::loadConsumerPlugin
   */
  public function testGetConsumer() {
    $consumer_id = 'example_consumer';
    $consumer = $this->createMock(ConsumerInterface::class);
    $this->consumerPlugin->expects($this->any())
      ->method('createInstance')
      ->with($consumer_id)
      ->willReturn($consumer);

    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'consumer' => $consumer_id,
    ], 'authorization_profile');
    $this->assertSame($consumer, $profile->getConsumer());
  }

  /**
   * Tests getConsumer() when the provider is not found.
   *
   * @covers ::getConsumer
   * @covers ::loadConsumerPlugin
   */
  public function testLoadConsumerPluginException() {
    $entity_type = $this->createMock(EntityTypeInterface::class);

    $this->entityTypeManager->expects($this->once())
      ->method('getDefinition')
      ->with('authorization_profile')
      ->willReturn($entity_type);
    $consumer_id = 'example_consumer';
    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'consumer' => $consumer_id,
      'consumer_config' => [
        // 'example' => 'config',
      ],
    ], 'authorization_profile');
    $this->consumerPlugin->expects($this->once())
      ->method('createInstance')
      ->with($consumer_id, ['profile' => $profile])
      ->willThrowException(new \Exception());

    $profile->getConsumer();
  }

  /**
   * Tests getProviderConfig().
   *
   * @covers ::getProviderConfig
   */
  public function testGetProviderConfig() {
    $provider_id = 'example_provider';

    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'provider' => $provider_id,
      'provider_config' => [
        'example' => 'config',
      ],
    ], 'authorization_profile');
    $this->assertSame(['example' => 'config'], $profile->getProviderConfig());
  }

  /**
   * Tests getConsumerConfig().
   *
   * @covers ::getConsumerConfig
   */
  public function testGetConsumerConfig() {
    $consumer_id = 'example_consumer';
    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'consumer' => $consumer_id,
      'consumer_config' => [
        'example' => 'config',
      ],
    ], 'authorization_profile');
    $this->assertSame(['example' => 'config'], $profile->getConsumerConfig());
  }

  /**
   * Tests getProviderMappings().
   *
   * @covers ::getProviderMappings
   */
  public function testGetProviderMappings() {
    $provider_id = 'example_provider';
    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'provider' => $provider_id,
      'provider_mappings' => [
        'example' => 'mapping',
      ],
    ], 'authorization_profile');
    $this->assertSame(['example' => 'mapping'], $profile->getProviderMappings());
  }

  /**
   * Tests getConsumerMappings().
   *
   * @covers ::getConsumerMappings
   */
  public function testGetConsumerMappings() {
    $consumer_id = 'example_consumer';
    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'consumer' => $consumer_id,
      'consumer_mappings' => [
        'example' => 'mapping',
      ],
    ], 'authorization_profile');
    $this->assertSame(['example' => 'mapping'], $profile->getConsumerMappings());
  }

  /**
   * Tests setProviderConfig().
   *
   * @covers ::setProviderConfig
   */
  public function testSetProviderConfig() {
    $provider_id = 'example_provider';
    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'provider' => $provider_id,
    ], 'authorization_profile');
    $profile->setProviderConfig(['example' => 'config']);
    $this->assertSame(['example' => 'config'], $profile->getProviderConfig());
  }

  /**
   * Tests setConsumerConfig().
   *
   * @covers ::setConsumerConfig
   */
  public function testSetConsumerConfig() {
    $consumer_id = 'example_consumer';
    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'consumer' => $consumer_id,
    ], 'authorization_profile');
    $profile->setConsumerConfig(['example' => 'config']);
    $this->assertSame(['example' => 'config'], $profile->getConsumerConfig());
  }

  /**
   * Tests setProviderMappings().
   *
   * @covers ::setProviderMappings
   */
  public function testSetProviderMappings() {
    $provider_id = 'example_provider';
    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'provider' => $provider_id,
    ], 'authorization_profile');
    $profile->setProviderMappings(['example' => 'mapping']);
    $this->assertSame(['example' => 'mapping'], $profile->getProviderMappings());
  }

  /**
   * Tests setConsumerMappings().
   *
   * @covers ::setConsumerMappings
   */
  public function testSetConsumerMappings() {
    $consumer_id = 'example_consumer';
    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'consumer' => $consumer_id,
    ], 'authorization_profile');
    $profile->setConsumerMappings(['example' => 'mapping']);
    $this->assertSame(['example' => 'mapping'], $profile->getConsumerMappings());
  }

  /**
   * Tests getTokens().
   *
   * @covers ::getTokens
   */
  public function testGetTokens() {
    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
    ], 'authorization_profile');
    $this->assertSame(['@profile_name' => 'Example Profile Name'], $profile->getTokens());
  }

  /**
   * Tests checkConditions() when status is FALSE.
   *
   * @covers ::checkConditions
   */
  public function testCheckConditionsStatus() {
    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'id' => 'example_profile_id',
      'status' => FALSE,
    ], 'authorization_profile');

    $this->assertFalse($profile->checkConditions());
  }

  /**
   * Tests checkConditions() when provider is not valid.
   *
   * @covers ::checkConditions
   */
  public function testCheckConditionsProvider() {
    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'id' => 'example_profile_id',
      'status' => TRUE,
      'provider' => NULL,
    ], 'authorization_profile');

    $this->assertFalse($profile->checkConditions());
  }

  /**
   * Tests checkConditions() when consumer is not valid.
   *
   * @covers ::checkConditions
   */
  public function testCheckConditionsConsumer() {
    $consumer_id = 'example_consumer';
    $provider_id = 'example_provider';
    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'status' => TRUE,
      'provider' => $provider_id,
      'provider_config' => [],
      'consumer' => $consumer_id,
    ], 'authorization_profile');
    $provider = $this->createMock(ProviderInterface::class);
    $this->providerPlugin->expects($this->once())
      ->method('createInstance')
      ->with($provider_id, ['profile' => $profile])
      ->willReturn($provider);

    $this->assertFalse($profile->checkConditions());
  }

  /**
   * Tests checkConditions() when provider and consumer are valid.
   *
   * @covers ::checkConditions
   */
  public function testCheckConditions() {
    $provider_id = 'example_provider';
    $consumer_id = 'example_consumer';
    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'status' => TRUE,
      'provider' => $provider_id,
      'provider_config' => [],
      'consumer' => $consumer_id,
      'consumer_config' => [],
    ], 'authorization_profile');

    $consumer = $this->createMock(ConsumerInterface::class);
    $provider = $this->createMock(ProviderInterface::class);

    $this->providerPlugin->expects($this->once())
      ->method('createInstance')
      ->with($provider_id, ['profile' => $profile])
      ->willReturn($provider);

    $this->consumerPlugin->expects($this->once())
      ->method('createInstance')
      ->with($consumer_id, ['profile' => $profile])
      ->willReturn($consumer);

    $this->assertTrue($profile->checkConditions());
  }

  /**
   * Tests grantsAndRevokes().
   *
   * @covers ::grantsAndRevokes
   */
  public function testGrantsAndRevokes() {
    $provider_id = 'example_provider';
    $consumer_id = 'example_consumer';
    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'id' => 'example_profile_id',
      'status' => TRUE,
      'provider' => $provider_id,
      'provider_config' => [],
      'provider_mappings' => [
        ['key' => 'value'],
      ],
      'consumer' => $consumer_id,
      'consumer_config' => [],
      'consumer_mappings' => [
        ['key' => 'value'],
      ],
      'synchronization_actions' => [
        'create_consumers' => TRUE,
        'revoke_provider_provisioned' => TRUE,
      ],
    ], 'authorization_profile');

    $provider = $this->createMock(ProviderInterface::class);
    $provider->expects($this->once())
      ->method('filterProposals')
      ->willReturn([]);

    $consumer = $this->createMock(ConsumerInterface::class);
    $consumer->expects($this->once())
      ->method('filterProposals')
      ->willReturn(['test']);

    $user = $this->createMock(UserInterface::class);

    $this->providerPlugin->expects($this->once())
      ->method('createInstance')
      ->with($provider_id, ['profile' => $profile])
      ->willReturn($provider);

    $this->consumerPlugin->expects($this->once())
      ->method('createInstance')
      ->with($consumer_id, ['profile' => $profile])
      ->willReturn($consumer);

    $response = $profile->grantsAndRevokes($user, TRUE);

    $this->assertSame('Example Profile Name', $response->getMessage());
  }

  /**
   * Tests grantsAndRevokes().
   *
   * @covers ::grantsAndRevokes
   */
  public function testGrantsAndRevokesException() {
    $provider_id = 'example_provider';
    $consumer_id = 'example_consumer';
    $profile = new AuthorizationProfile([
      'label' => 'Example Profile Name',
      'status' => TRUE,
      'provider' => $provider_id,
      'provider_config' => [],
      'provider_mappings' => [
        ['key' => 'value'],
      ],
      'consumer' => $consumer_id,
      'consumer_config' => [],
      'consumer_mappings' => [
        ['key' => 'value'],
      ],
      'synchronization_actions' => [
        'create_consumers' => TRUE,
        'revoke_provider_provisioned' => TRUE,
      ],
    ], 'authorization_profile');

    $provider = $this->createMock(ProviderInterface::class);
    $provider->expects($this->once())
      ->method('getProposals')
      ->willThrowException(new AuthorizationSkipAuthorization());

    $consumer = $this->createMock(ConsumerInterface::class);
    $user = $this->createMock(UserInterface::class);

    $this->providerPlugin->expects($this->once())
      ->method('createInstance')
      ->with($provider_id, ['profile' => $profile])
      ->willReturn($provider);

    $this->consumerPlugin->expects($this->once())
      ->method('createInstance')
      ->with($consumer_id, ['profile' => $profile])
      ->willReturn($consumer);

    $response = $profile->grantsAndRevokes($user, TRUE);
    $this->assertSame('Example Profile Name (skipped)', $response->getMessage());
  }

}
