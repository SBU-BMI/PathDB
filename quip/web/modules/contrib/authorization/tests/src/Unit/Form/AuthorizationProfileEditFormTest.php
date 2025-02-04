<?php

declare(strict_types=1);

namespace Drupal\Tests\authorization\Unit\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use Drupal\authorization\AuthorizationProfileInterface;
use Drupal\authorization\Consumer\ConsumerInterface;
use Drupal\authorization\Consumer\ConsumerPluginManager;
use Drupal\authorization\Form\AuthorizationProfileEditForm;
use Drupal\authorization\Provider\ProviderInterface;
use Drupal\authorization\Provider\ProviderPluginManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests AuthorizationProfileEditForm.
 *
 * @group authorization
 */
class AuthorizationProfileEditFormTest extends UnitTestCase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\Messenger|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $messenger;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $currentUser;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The provider plugin manager.
   *
   * @var \Drupal\authorization\Provider\ProviderPluginManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $providerPluginManager;

  /**
   * The consumer plugin manager.
   *
   * @var \Drupal\authorization\Consumer\ConsumerPluginManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $consumerPluginManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $renderer;

  /**
   * The profile.
   *
   * @var \Drupal\authorization\AuthorizationProfileInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $profile;

  /**
   * The form.
   *
   * @var \Drupal\authorization\Form\AuthorizationProfileEditForm
   */
  protected $form;

  /**
   * {@inheritdoc}
   */
  protected function setup(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $string_translation = $this->getStringTranslationStub();
    $container->set('string_translation', $string_translation);

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->messenger = $this->createMock(Messenger::class);
    $this->currentUser = $this->createMock(AccountInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->providerPluginManager = $this->createMock(ProviderPluginManager::class);
    $this->consumerPluginManager = $this->createMock(ConsumerPluginManager::class);
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->renderer = $this->createMock(RendererInterface::class);

    $container->set('entity_type.manager', $this->entityTypeManager);
    $container->set('messenger', $this->messenger);
    $container->set('current_user', $this->currentUser);
    $container->set('config.factory', $this->configFactory);
    $container->set('plugin.manager.authorization.provider', $this->providerPluginManager);
    $container->set('plugin.manager.authorization.consumer', $this->consumerPluginManager);
    $container->set('module_handler', $this->moduleHandler);
    $container->set('renderer', $this->renderer);

    \Drupal::setContainer($container);

    $this->profile = $this->createMock(AuthorizationProfileInterface::class);
    $this->form = AuthorizationProfileEditForm::create($container);
    $this->form->setEntity($this->profile);
  }

  /**
   * Test getFormId() method.
   */
  public function testGetFormId() {
    $this->assertEquals('authorization_profile_edit_form', $this->form->getFormId());
  }

  /**
   * Test buildForm() method.
   */
  public function testBuildForm() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);

    $this->providerPluginManager->expects($this->once())
      ->method('getDefinitions')
      ->willReturn([
        'provider1' => ['label' => t('provider1 label')],
      ]);

    $this->consumerPluginManager->expects($this->once())
      ->method('getDefinitions')
      ->willReturn([
        'consumer1' => ['label' => t('consumer1 label')],
      ]);

    $this->profile->expects($this->once())
      ->method('label')
      ->willReturn('profile label');
    $this->profile->expects($this->once())
      ->method('id')
      ->willReturn('profile_label');
    $this->profile->expects($this->exactly(3))
      ->method('isNew')
      ->willReturn(FALSE);

    $this->profile->expects($this->once())
      ->method('getProviderId')
      ->willReturn('provider1');

    $this->profile->expects($this->once())
      ->method('getConsumerId')
      ->willReturn('consumer1');

    $this->form->setModuleHandler($this->moduleHandler);
    $form = $this->form->buildForm($form, $form_state);

    $this->assertCount(12, $form);
    $this->assertArrayHasKey('label', $form);
    $this->assertArrayHasKey('id', $form);
    $this->assertArrayHasKey('status', $form);
    $this->assertArrayHasKey('actions', $form);
    $this->assertEquals('Save', $form['actions']['submit']['#value']);
  }

  /**
   * Test form() method.
   */
  public function testForm() {

    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);

    $this->providerPluginManager->expects($this->once())
      ->method('getDefinitions')
      ->willReturn([
        'provider1' => ['label' => t('provider1 label')],
        'provider2' => ['label' => t('provider2 label')],
      ]);

    $this->consumerPluginManager->expects($this->once())
      ->method('getDefinitions')
      ->willReturn([
        'consumer1' => ['label' => t('consumer1 label')],
        'consumer2' => ['label' => t('consumer2 label')],
      ]);

    $provider = $this->createMock(ProviderInterface::class);
    $provider->expects($this->once())
      ->method('buildConfigurationForm')
      ->willReturn([
        'test' => 'test',
      ]);
    $provider->expects($this->once())
      ->method('isSyncOnLogonSupported')
      ->willReturn(TRUE);
    $provider->expects($this->once())
      ->method('revocationSupported')
      ->willReturn(TRUE);

    $consumer = $this->createMock(ConsumerInterface::class);
    $consumer->expects($this->once())
      ->method('buildConfigurationForm')
      ->willReturn([
        'test' => 'test',
      ]);
    $consumer->expects($this->once())
      ->method('consumerTargetCreationAllowed')
      ->willReturn(TRUE);

    $this->profile->expects($this->once())
      ->method('label')
      ->willReturn('profile label');
    $this->profile->expects($this->once())
      ->method('id')
      ->willReturn('profile_label');
    $this->profile->expects($this->once())
      ->method('isNew')
      ->willReturn(FALSE);
    $this->profile->expects($this->once())
      ->method('getProviderId')
      ->willReturn('provider1');
    $this->profile->expects($this->exactly(4))
      ->method('getProvider')
      ->willReturn($provider);
    $this->profile->expects($this->exactly(3))
      ->method('hasValidProvider')
      ->willReturn(TRUE);
    $this->profile->expects($this->once())
      ->method('getConsumerId')
      ->willReturn('consumer1');
    $this->profile->expects($this->exactly(3))
      ->method('hasValidConsumer')
      ->willReturn(TRUE);
    $this->profile->expects($this->exactly(4))
      ->method('getConsumer')
      ->willReturn($consumer);

    $this->form->setModuleHandler($this->moduleHandler);
    $form = $this->form->form($form, $form_state);

    $this->assertCount(15, $form);
    $this->assertArrayHasKey('label', $form);
    $this->assertArrayHasKey('id', $form);
    $this->assertArrayHasKey('status', $form);
  }

  /**
   * Test save() method.
   */
  public function testSaveForm() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);

    $url = $this->createMock(Url::class);
    $this->profile->expects($this->once())
      ->method('save')
      ->willReturn(2);
    $this->profile->expects($this->once())
      ->method('toUrl')
      ->with('collection')
      ->willReturn($url);

    $form_state->expects($this->once())
      ->method('setRedirectUrl')
      ->with($url);

    $this->form->save($form, $form_state);
  }

  /**
   * Test validateForm() method.
   */
  public function testValidateForm() {
    $form = [];
    $form['provider_config']['#type'] = 'details';
    $form['consumer_config']['#type'] = 'details';
    $form['mappings'][0]['provider_mappings'] = [];
    $form['mappings'][0]['consumer_mappings'] = [];

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->exactly(3))
      ->method('get')
      ->with('sub_states')
      ->willReturn([]);
    $form_state->expects($this->exactly(3))
      ->method('getValues')
      ->willReturn([]);
    $form_state->expects($this->exactly(3))
      ->method('getUserInput')
      ->willReturn([]);
    $form_state->expects($this->once())
      ->method('getValue')
      ->with('mappings')
      ->willReturn(TRUE);

    $provider = $this->createMock(ProviderInterface::class);
    $provider->expects($this->once())
      ->method('validateConfigurationForm');
    $provider->expects($this->once())
      ->method('validateRowForm');

    $consumer = $this->createMock(ConsumerInterface::class);
    $consumer->expects($this->once())
      ->method('validateConfigurationForm');
    $consumer->expects($this->once())
      ->method('validateRowForm');

    $this->profile->expects($this->once())
      ->method('hasValidProvider')
      ->willReturn(TRUE);
    $this->profile->expects($this->exactly(2))
      ->method('getProvider')
      ->willReturn($provider);
    $this->profile->expects($this->once())
      ->method('hasValidConsumer')
      ->willReturn(TRUE);
    $this->profile->expects($this->exactly(2))
      ->method('getConsumer')
      ->willReturn($consumer);

    $this->form->validateForm($form, $form_state);
  }

  /**
   * Test submitForm() method.
   */
  public function testSubmitForm() {
    $form = [];
    $form['provider_config']['#type'] = 'details';
    $form['consumer_config']['#type'] = 'details';
    $form['mappings']['provider_mappings'] = [];
    $form['mappings']['consumer_mappings'] = [];

    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->exactly(3))
      ->method('get')
      ->with('sub_states')
      ->willReturn([]);
    $form_state->expects($this->exactly(5))
      ->method('getValues')
      ->willReturn([
        'provider_config' => [],
        'consumer_config' => [],
        'mappings' => [
          [
            'provider_mappings' => [],
            'consumer_mappings' => [],
            'delete' => 0,
          ],
          [
            'provider_mappings' => [],
            'consumer_mappings' => [],
            'delete' => 1,
          ],
        ],
      ]);
    $form_state->expects($this->exactly(3))
      ->method('getUserInput')
      ->willReturn([]);

    $provider = $this->createMock(ProviderInterface::class);
    $provider->expects($this->once())
      ->method('submitConfigurationForm');

    $consumer = $this->createMock(ConsumerInterface::class);
    $consumer->expects($this->once())
      ->method('submitConfigurationForm');

    $this->profile->expects($this->once())
      ->method('hasValidProvider')
      ->willReturn(TRUE);
    $this->profile->expects($this->exactly(2))
      ->method('getProvider')
      ->willReturn($provider);
    $this->profile->expects($this->once())
      ->method('hasValidConsumer')
      ->willReturn(TRUE);
    $this->profile->expects($this->exactly(2))
      ->method('getConsumer')
      ->willReturn($consumer);

    $this->form->submitForm($form, $form_state);
  }

  /**
   * Test mappingsAjaxCallback() method.
   */
  public function testMappingsAjaxCallback() {
    $form = [
      'mappings' => [
        'provider_mappings' => [],
        'consumer_mappings' => [],
        'delete' => '0',
      ],
    ];
    $form_state = $this->createMock(FormStateInterface::class);

    $result = $this->form->mappingsAjaxCallback($form, $form_state);
    $this->assertEquals($form['mappings'], $result);
  }

  /**
   * Test mappingsAddAnother() method.
   */
  public function testMappingsAddAnother() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('get')
      ->with('mappings_fields')
      ->willReturn(1);
    $form_state->expects($this->once())
      ->method('set')
      ->with('mappings_fields', 2);

    $form_state->expects($this->once())
      ->method('setRebuild')
      ->with(TRUE);

    $this->form->mappingsAddAnother($form, $form_state);
  }

  /**
   * Test buildAjaxProviderRowForm() method.
   */
  public function testBuildAjaxProviderRowForm() {
    $form = [
      'provider_mappings' => ['test' => 'test'],
    ];
    $form_state = $this->createMock(FormStateInterface::class);

    $result = $this->form::buildAjaxProviderRowForm($form, $form_state);
    $this->assertEquals($form['provider_mappings'], $result);
  }

  /**
   * Test buildAjaxConsumerRowForm() method.
   */
  public function testBuildAjaxConsumerRowForm() {
    $form = [
      'consumer_mappings' => ['test' => 'test'],
    ];
    $form_state = $this->createMock(FormStateInterface::class);

    $result = $this->form::buildAjaxConsumerRowForm($form, $form_state);
    $this->assertEquals($form['consumer_mappings'], $result);
  }

}
