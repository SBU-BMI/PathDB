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
use Drupal\authorization\Consumer\ConsumerPluginManager;
use Drupal\authorization\Form\AuthorizationProfileAddForm;
use Drupal\authorization\Provider\ProviderPluginManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests AuthorizationProfileAddForm.
 *
 * @group authorization
 */
class AuthorizationProfileAddFormTest extends UnitTestCase {

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
   * @var \Drupal\authorization\Form\AuthorizationProfileAddForm
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
    $this->form = AuthorizationProfileAddForm::create($container);
    $this->form->setEntity($this->profile);
  }

  /**
   * Test getFormId() method.
   */
  public function testGetFormId() {
    $this->assertEquals('authorization_profile_add_form', $this->form->getFormId());
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
      ->willReturn(TRUE);

    $this->profile->expects($this->once())
      ->method('getProviderId')
      ->willReturn('provider1');

    $this->profile->expects($this->once())
      ->method('getConsumerId')
      ->willReturn('consumer1');

    $this->form->setModuleHandler($this->moduleHandler);
    $form = $this->form->buildForm($form, $form_state);

    $this->assertCount(8, $form);
    $this->assertArrayHasKey('label', $form);
    $this->assertArrayHasKey('id', $form);
    $this->assertArrayHasKey('provider', $form);
    $this->assertArrayHasKey('consumer', $form);
    $this->assertArrayHasKey('status', $form);
    $this->assertArrayHasKey('actions', $form);
    $this->assertEquals('Save', $form['actions']['submit']['#value']);
  }

  /**
   * Test buildForm() method with no plugins.
   */
  public function testBuildFormNoPlugins() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);

    $this->providerPluginManager->expects($this->once())
      ->method('getDefinitions')
      ->willReturn([]);

    $this->consumerPluginManager->expects($this->once())
      ->method('getDefinitions')
      ->willReturn([]);

    $this->profile->expects($this->once())
      ->method('label')
      ->willReturn('profile label');
    $this->profile->expects($this->once())
      ->method('id')
      ->willReturn('profile_label');
    $this->profile->expects($this->exactly(3))
      ->method('isNew')
      ->willReturn(TRUE);

    $this->form->setModuleHandler($this->moduleHandler);
    $form = $this->form->buildForm($form, $form_state);
    $this->assertArrayHasKey('label', $form);
    $this->assertArrayHasKey('id', $form);
    $this->assertArrayNotHasKey('provider', $form);
    $this->assertArrayNotHasKey('consumer', $form);
    $this->assertArrayHasKey('status', $form);
    $this->assertArrayHasKey('actions', $form);
    $this->assertArrayHasKey('#markup', $form);
    $this->assertEquals('Authorization profile cannot be created.', $form['#markup']);
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
      ->willReturn(1);
    $this->profile->expects($this->once())
      ->method('toUrl')
      ->with('edit-form')
      ->willReturn($url);

    $form_state->expects($this->once())
      ->method('setRedirectUrl')
      ->with($url);

    $this->form->save($form, $form_state);
  }

  /**
   * Test save() method.
   */
  public function testSubmitForm() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('getValues')
      ->willReturn([
        'label' => 'label',
        'id' => 'id',
        'provider' => 'provider',
        'consumer' => 'consumer',
        'status' => 1,
      ]);

    $this->form->submitForm($form, $form_state);
  }

}
