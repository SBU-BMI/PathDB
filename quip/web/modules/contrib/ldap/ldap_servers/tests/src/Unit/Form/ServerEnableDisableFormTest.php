<?php

declare(strict_types=1);

namespace Drupal\Test\ldap_servers\Unit\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\ldap_servers\Form\ServerEnableDisableForm;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the ServerEnableDisableForm.
 *
 * @group ldap_servers
 */
class ServerEnableDisableFormTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The server enable/disable form.
   *
   * @var \Drupal\ldap_servers\Form\ServerEnableDisableForm
   */
  protected $serverEnableDisableForm;

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The server.
   *
   * @var \Drupal\ldap_servers\ServerInterface
   */
  protected $server;

  /**
   * The form.
   *
   * @var \Drupal\ldap_servers\Form\ServerEnableDisableForm
   */
  protected $form;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container = new ContainerBuilder();

    $string_translation = $this->getStringTranslationStub();
    $this->container->set('string_translation', $string_translation);

    $this->config = $this->prophesize(ConfigFactoryInterface::class);
    $this->container->set('config.factory', $this->config->reveal());

    $this->moduleHandler = $this->prophesize(ModuleHandlerInterface::class);
    $this->container->set('module_handler', $this->moduleHandler->reveal());

    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->container->set('entity_type.manager', $this->entityTypeManager->reveal());

    $this->routeMatch = $this->prophesize(RouteMatchInterface::class);
    $this->container->set('current_route_match', $this->routeMatch->reveal());

    $this->logger = $this->prophesize('Psr\Log\LoggerInterface');
    $this->container->set('logger.channel.ldap_servers', $this->logger->reveal());

    $this->messenger = $this->prophesize('Drupal\Core\Messenger\MessengerInterface');
    $this->container->set('messenger', $this->messenger->reveal());

    \Drupal::setContainer($this->container);
    $this->server = $this->prophesize('Drupal\ldap_servers\ServerInterface');
    $this->form = ServerEnableDisableForm::create($this->container);
    $this->form->setEntity($this->server->reveal());
  }

  /**
   * Test the getFormId method.
   */
  public function testGetFormId(): void {
    $this->assertEquals('ldap_servers_enable_disable_form', $this->form->getFormId());
  }

  /**
   * Test the submitForm method.
   */
  public function testSubmitForm(): void {
    $this->server->get('status')
      ->willReturn(0, 1)
      ->shouldBeCalled($this->exactly(2));
    $this->server->set('status', TRUE)
      ->shouldBeCalled($this->once());
    $this->server->save()
      ->shouldBeCalled($this->once());
    $this->server->label()
      ->willReturn('Example Server')
      ->shouldBeCalled($this->once());
    $this->server->id()
      ->willReturn('example')
      ->shouldBeCalled($this->once());
    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $this->form->submitForm($form, $form_state);
  }

  /**
   * Test the submitForm method when the server is disabled.
   */
  public function testSubmitFormEnables(): void {
    $this->server->get('status')
      ->willReturn(1, 0)
      ->shouldBeCalled($this->exactly(2));
    $this->server->set('status', FALSE)
      ->shouldBeCalled($this->once());
    $this->server->save()
      ->shouldBeCalled($this->once());
    $this->server->label()
      ->willReturn('Example Server')
      ->shouldBeCalled($this->once());
    $this->server->id()
      ->willReturn('example')
      ->shouldBeCalled($this->once());
    $form = [];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $this->form->submitForm($form, $form_state);
  }

  /**
   * Test the getQuestion method.
   */
  public function testGetQuestion() {
    $this->server->label()->willReturn('Example Server');
    $this->assertEquals('Are you sure you want to disable/enable entity <em class="placeholder">Example Server</em>?', (string) $this->form->getQuestion());
  }

  /**
   * Test the getConfirmText method.
   */
  public function testGetConfirmText() {
    $this->server->get('status')->willReturn(1);
    $this->assertEquals('Disable', (string) $this->form->getConfirmText());
    $this->server->get('status')->willReturn(0);
    $this->assertEquals('Enable', (string) $this->form->getConfirmText());
  }

  /**
   * Test the getCancelUrl method.
   */
  public function testGetCancelUrl() {
    $this->assertEquals('entity.ldap_server.collection', $this->form->getCancelUrl()->getRouteName());
  }

}
