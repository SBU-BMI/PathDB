<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_servers\Unit\Form;

use Drupal\ldap_servers\Form\LdapDebugSettings;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the Ldap debug form.
 */
class LdapDebugSettingsTest extends UnitTestCase {

  /**
   * The server.
   *
   * @var \Drupal\ldap_servers\ServerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $server;

  /**
   * The form under test.
   *
   * @var \Drupal\ldap_servers\Form\LdapDebugSettings
   */
  protected $form;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The config typed.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configTyped;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $string_translation = $this->createMock('Drupal\Core\StringTranslation\TranslationInterface');
    $container->set('string_translation', $string_translation);

    $logger = $this->createMock('Psr\Log\LoggerInterface');
    $container->set('logger.channel.ldap_servers', $logger);

    $this->configFactory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $container->set('config.factory', $this->configFactory);

    $this->configTyped = $this->createMock('Drupal\Core\Config\TypedConfigManagerInterface');
    $container->set('config.typed', $this->configTyped);

    \Drupal::setContainer($container);

    $this->form = LdapDebugSettings::create($container);
  }

  /**
   * Tests the getFormId method.
   */
  public function testGetFormId(): void {
    $this->assertEquals('ldap_servers_debug_settings', $this->form->getFormId());
  }

  /**
   * Tests the getEditableConfigNames method.
   */
  public function testBuildForm(): void {
    $config = $this->createMock('Drupal\Core\Config\Config');
    $config->expects($this->once())
      ->method('get')
      ->with('watchdog_detail')
      ->willReturn(TRUE);
    $this->configFactory->expects($this->once())
      ->method('getEditable')
      ->with('ldap_servers.settings')
      ->willReturn($config);

    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form = $this->form->buildForm([], $form_state);
    $this->assertArrayHasKey('watchdog_detail', $form);
  }

  /**
   * Tests the submitForm method.
   */
  public function testSubmitForm(): void {
    $config = $this->createMock('Drupal\Core\Config\Config');
    $config->expects($this->once())
      ->method('set')
      ->with('watchdog_detail', TRUE)
      ->willReturnSelf();
    $config->expects($this->once())
      ->method('save');
    $this->configFactory->expects($this->once())
      ->method('getEditable')
      ->with('ldap_servers.settings')
      ->willReturn($config);

    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->once())
      ->method('getValue')
      ->with('watchdog_detail')
      ->willReturn(TRUE);
    $this->form->submitForm($form, $form_state);
  }

}
