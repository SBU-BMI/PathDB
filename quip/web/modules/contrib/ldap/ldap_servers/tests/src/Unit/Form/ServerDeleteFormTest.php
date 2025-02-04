<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_servers\Unit\Form;

use Drupal\ldap_servers\Form\ServerDeleteForm;
use Drupal\ldap_servers\ServerInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @coversDefaultClass \Drupal\ldap_servers\Form\ServerDeleteForm
 * @group ldap_servers
 */
class ServerDeleteFormTest extends UnitTestCase {

  /**
   * The server.
   *
   * @var \Drupal\ldap_servers\ServerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $server;

  /**
   * The form under test.
   *
   * @var \Drupal\ldap_servers\Form\ServerDeleteForm
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

    \Drupal::setContainer($container);

    $this->server = $this->createMock(ServerInterface::class);
    $this->form = ServerDeleteForm::create($container);
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
    $this->assertEquals('ldap_server__form', $this->form->getFormId());
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitForm(): void {
    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $this->server->expects($this->once())
      ->method('delete');
    $this->form->submitForm($form, $form_state);
  }

  /**
   * Tests getConfirmText().
   */
  public function testGetConfirmText(): void {
    $this->assertEquals('Delete', (string) $this->form->getConfirmText()->getUntranslatedString());
  }

  /**
   * Tests getCancelUrl().
   */
  public function testGetCancelUrl(): void {
    $this->assertEquals('entity.ldap_server.collection', $this->form->getCancelUrl()->getRouteName());
  }

  /**
   * Tests getQuestion().
   */
  public function testGetQuestion(): void {
    $this->server->expects($this->once())
      ->method('label')
      ->willReturn('Server 1');

    $this->assertEquals('Are you sure you want to delete entity %name?', (string) $this->form->getQuestion()->getUntranslatedString());
  }

}
