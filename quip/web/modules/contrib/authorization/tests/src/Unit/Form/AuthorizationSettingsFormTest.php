<?php

declare(strict_types=1);

namespace Drupal\Tests\authorization\Unit\Form;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\authorization\Form\AuthorizationSettingsForm;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests AuthorizationSettingsForm.
 *
 * @group authorization
 */
class AuthorizationSettingsFormTest extends UnitTestCase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

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
   * The form.
   *
   * @var \Drupal\authorization\Form\AuthorizationSettingsForm
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

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->messenger = $this->createMock(Messenger::class);
    $this->currentUser = $this->createMock(AccountInterface::class);

    $typed_config = $this->createMock('\Drupal\Core\Config\TypedConfigManagerInterface');

    $container->set('config.factory', $this->configFactory);
    $container->set('messenger', $this->messenger);
    $container->set('current_user', $this->currentUser);
    $container->set('config.typed', $typed_config);

    \Drupal::setContainer($container);

    $this->form = AuthorizationSettingsForm::create($container);
  }

  /**
   * Test getFormId() method.
   */
  public function testGetFormId() {
    $this->assertEquals('authorization_settings', $this->form->getFormId());
  }

  /**
   * Test buildForm() method.
   */
  public function testBuildForm() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $settings = $this->createMock(Config::class);
    $settings->expects($this->once())
      ->method('get')
      ->with('authorization_message')
      ->willReturn(TRUE);
    $this->configFactory->expects($this->once())
      ->method('getEditable')
      ->with('authorization.settings')
      ->willReturn($settings);

    $this->form->buildForm($form, $form_state);
  }

  /**
   * Test submitForm() method.
   */
  public function testSubmitForm() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->expects($this->once())
      ->method('getValue')
      ->with('authorization_message')
      ->willReturn(TRUE);
    $settings = $this->createMock(Config::class);
    $settings->expects($this->once())
      ->method('set')
      ->with('authorization_message', TRUE)
      ->willReturnSelf();
    $settings->expects($this->once())
      ->method('save');

    $this->configFactory->expects($this->once())
      ->method('getEditable')
      ->with('authorization.settings')
      ->willReturn($settings);

    $this->form->submitForm($form, $form_state);
  }

}
