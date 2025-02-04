<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_servers\Unit\Form;

use Drupal\ldap_servers\Form\ServerForm;
use Drupal\ldap_servers\ServerInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @coversDefaultClass \Drupal\ldap_servers\Form\ServerForm
 * @group ldap_servers
 */
class ServerFormTest extends UnitTestCase {

  /**
   * The server.
   *
   * @var \Drupal\ldap_servers\ServerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $server;

  /**
   * The form under test.
   *
   * @var \Drupal\ldap_servers\Form\ServerForm
   */
  protected $form;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $messenger;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

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

    $string_translation = $this->createMock('Drupal\Core\StringTranslation\TranslationInterface');
    $container->set('string_translation', $string_translation);

    $logger = $this->createMock('Psr\Log\LoggerInterface');
    $container->set('logger.channel.ldap_servers', $logger);

    $this->moduleHandler = $this->createMock('Drupal\Core\Extension\ModuleHandlerInterface');
    $container->set('module_handler', $this->moduleHandler);

    $this->messenger = $this->createMock('Drupal\Core\Messenger\MessengerInterface');
    $container->set('messenger', $this->messenger);

    $renderer = $this->createMock('Drupal\Core\Render\RendererInterface');
    $container->set('renderer', $renderer);

    $this->configFactory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $container->set('config.factory', $this->configFactory);

    $this->entityTypeManager = $this->createMock('Drupal\Core\Entity\EntityTypeManagerInterface');
    $container->set('entity_type.manager', $this->entityTypeManager);

    \Drupal::setContainer($container);

    $this->server = $this->createMock(ServerInterface::class);
    $this->form = ServerForm::create($container);
    $this->form->setEntity($this->server);
  }

  /**
   * @covers ::getFormId
   */
  public function testGetFormId(): void {
    $this->server->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('ldap_server');
    $entity_type = $this->createMock('Drupal\Core\Entity\EntityTypeInterface');
    $entity_type->expects($this->once())
      ->method('hasKey')
      ->with('bundle')
      ->willReturn(FALSE);
    $this->server->expects($this->once())
      ->method('getEntityType')
      ->willReturn($entity_type);
    $this->assertSame('ldap_server__form', $this->form->getFormId());
  }

  /**
   * @covers ::form
   */
  public function testForm(): void {
    $this->server->expects($this->exactly(2))
      ->method('id')
      ->willReturn('test');
    $config = $this->createMock('Drupal\Core\Config\Config');
    $config->expects($this->exactly(2))
      ->method('hasOverrides')
      ->willReturn(FALSE);
    $this->configFactory->expects($this->once())
      ->method('get')
      ->with('ldap_servers.server.test')
      ->willReturn($config);
    $module_handler = $this->createMock('Drupal\Core\Extension\ModuleHandlerInterface');
    $this->form->setModuleHandler($module_handler);
    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $this->assertIsArray($this->form->form($form, $form_state));
  }

  /**
   * Tests form with overrides.
   */
  public function testFormOverride(): void {
    $this->server->expects($this->exactly(2))
      ->method('id')
      ->willReturn('test');
    $this->server->expects($this->exactly(22))
      ->method('get')
      ->willReturn(
        1,
        'bindpw',
        'localhost',
        389,
        30,
        'none',
        'service_account',
        ['ou=people,dc=hogwarts,dc=edu', 'ou=groups,dc=hogwarts,dc=edu'],
        'password',
        ['ou=people,dc=hogwarts,dc=edu', 'ou=groups,dc=hogwarts,dc=edu'],
        ['ou=people,dc=hogwarts,dc=edu', 'ou=groups,dc=hogwarts,dc=edu'],
        'mail',
        'uid',
        'hpotter',
        FALSE,
        TRUE,
        'member',
        'groupofnames',
        'dn',
        FALSE,
        '',
        ''
      );

    $config = $this->createMock('Drupal\Core\Config\Config');
    $config->expects($this->exactly(2))
      ->method('hasOverrides')
      ->willReturn(TRUE);
    $this->configFactory->expects($this->once())
      ->method('get')
      ->with('ldap_servers.server.test')
      ->willReturn($config);
    $module_handler = $this->createMock('Drupal\Core\Extension\ModuleHandlerInterface');
    $this->form->setModuleHandler($module_handler);
    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $this->assertIsArray($this->form->form($form, $form_state));
  }

  /**
   * Tests saves a new server.
   */
  public function testSaveNew(): void {
    $this->server->expects($this->exactly(16))
      ->method('set')
      ->willReturnSelf();
    $this->server->expects($this->exactly(12))
      ->method('get')
      ->willReturn(
        'anon',
        '',
        'mail',
        '',
        'jpegPhoto',
        'uid',
        '',
        'member',
        'groupofnames',
        '',
        '',
        ''
      );
    $this->server->expects($this->once())
      ->method('save')
      ->willReturn(1);
    $test_form = $this->createMock('Drupal\Core\Url');
    $this->server->expects($this->once())
      ->method('toUrl')
      ->with('test-form')
      ->willReturn($test_form);

    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state
      ->method('getValue')
      ->willReturn('anon', 'ou=people,dc=hogwarts,dc=edu\r\nou=groups,dc=hogwarts,dc=edu', 'localhost');

    $form_state->expects($this->once())
      ->method('setRedirectUrl')
      ->with($test_form);

    $this->form->save([], $form_state);
  }

  /**
   * Tests save with existing server.
   */
  public function testSaveUpdate(): void {
    $this->server->expects($this->exactly(16))
      ->method('set')
      ->willReturnSelf();
    $this->server->expects($this->exactly(12))
      ->method('get')
      ->willReturn(
        'anon',
        '',
        'mail',
        '',
        'jpegPhoto',
        'uid',
        '',
        'member',
        'groupofnames',
        '',
        '',
        ''
      );
    $this->server->expects($this->once())
      ->method('save')
      ->willReturn(2);
    $collection = $this->createMock('Drupal\Core\Url');
    $this->server->expects($this->once())
      ->method('toUrl')
      ->with('collection')
      ->willReturn($collection);

    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state
      ->method('getValue')
      ->willReturn('anon', 'ou=people,dc=hogwarts,dc=edu\r\nou=groups,dc=hogwarts,dc=edu', 'localhost');

    $form_state->expects($this->once())
      ->method('setRedirectUrl')
      ->with($collection);

    $this->form->save([], $form_state);
  }

  /**
   * Tests save with a service account.
   */
  public function testSaveServiceAccountPassword(): void {
    $this->server->expects($this->exactly(15))
      ->method('set')
      ->willReturnSelf();
    $this->server->expects($this->exactly(12))
      ->method('get')
      ->willReturn(
        'anon',
        '',
        'mail',
        '',
        'jpegPhoto',
        'uid',
        '',
        'member',
        'groupofnames',
        '',
        '',
        ''
      );
    $this->server->expects($this->once())
      ->method('save')
      ->willReturn(2);
    $collection = $this->createMock('Drupal\Core\Url');
    $this->server->expects($this->once())
      ->method('toUrl')
      ->with('collection')
      ->willReturn($collection);

    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state
      ->method('getValue')
      ->willReturn(
        'service_account',
        'password-to-save',
        'ou=people,dc=hogwarts,dc=edu\r\nou=groups,dc=hogwarts,dc=edu',
        'localhost',
        'localhost');

    $form_state->expects($this->once())
      ->method('setRedirectUrl')
      ->with($collection);

    $this->form->save([], $form_state);
  }

  /**
   * Tests save with a service account.
   */
  public function testSaveServiceAccount(): void {

    $this->server->expects($this->once())
      ->method('id')
      ->willReturn('test');
    $this->server->expects($this->exactly(15))
      ->method('set')
      ->willReturnSelf();
    $this->server->expects($this->exactly(14))
      ->method('get')
      ->willReturn(
        'anon',
        '',
        'mail',
        '',
        'jpegPhoto',
        'uid',
        '',
        'member',
        'groupofnames',
        '',
        '',
        '',
        '',
        ''
      );
    $this->server->expects($this->once())
      ->method('save')
      ->willReturn(2);
    $collection = $this->createMock('Drupal\Core\Url');
    $this->server->expects($this->once())
      ->method('toUrl')
      ->with('collection')
      ->willReturn($collection);

    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state
      ->method('getValue')
      ->willReturn(
        'service_account',
        '****',
        'ou=people,dc=hogwarts,dc=edu\r\nou=groups,dc=hogwarts,dc=edu',
        'localhost',
        'localhost');

    $form_state->expects($this->once())
      ->method('setRedirectUrl')
      ->with($collection);

    $this->form->setEntityTypeManager($this->entityTypeManager);
    $storage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('ldap_server')
      ->willReturn($storage);
    $storage->expects($this->once())
      ->method('load')
      ->with('test')
      ->willReturn($this->server);

    $this->form->save([], $form_state);
  }

}
