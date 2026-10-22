<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_user\Unit;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Token;
use Drupal\externalauth\AuthmapInterface;
use Drupal\ldap_servers\LdapUserManager;
use Drupal\ldap_servers\Logger\LdapDetailLog;
use Drupal\ldap_servers\Processor\TokenProcessor;
use Drupal\ldap_user\Event\LdapUserLoginEvent;
use Drupal\ldap_user\FieldProvider;
use Drupal\ldap_user\Processor\DrupalUserProcessor;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests DrupalUserProcessor event dispatch.
 */
#[Group('ldap')]
#[CoversClass(DrupalUserProcessor::class)]
class DrupalUserProcessorTest extends UnitTestCase {

  /**
   * Login dispatches LdapUserLoginEvent with EVENT_NAME.
   */
  public function testDrupalUserLogsInDispatchesNamedEvent(): void {
    $ldap_user_settings = $this->createMock(ImmutableConfig::class);
    $ldap_user_settings->method('get')
      ->willReturnMap([
        ['drupalAcctProvisionTriggers', []],
        ['drupalAcctProvisionServer', NULL],
      ]);

    $auth_settings = $this->createMock(ImmutableConfig::class);
    $auth_settings->method('get')
      ->with('skipAdministrators')
      ->willReturn(FALSE);

    $config_factory = $this->createMock(ConfigFactory::class);
    $config_factory->method('get')
      ->willReturnMap([
        ['ldap_user.settings', $ldap_user_settings],
        ['ldap_authentication.settings', $auth_settings],
      ]);

    $module_handler = $this->createMock(ModuleHandlerInterface::class);
    $module_handler->method('moduleExists')
      ->with('file')
      ->willReturn(FALSE);

    $event_dispatcher = $this->createMock(EventDispatcherInterface::class);
    $event_dispatcher->expects($this->once())
      ->method('dispatch')
      ->with(
        $this->isInstanceOf(LdapUserLoginEvent::class),
        LdapUserLoginEvent::EVENT_NAME,
      );

    $exclude = $this->createMock(FieldItemListInterface::class);
    $exclude->method('getString')->willReturn('');
    $account = $this->createMock(UserInterface::class);
    $account->method('get')
      ->with('ldap_user_ldap_exclude')
      ->willReturn($exclude);
    $account->expects($this->once())->method('save');

    $processor = new DrupalUserProcessor(
      $this->createMock(LoggerInterface::class),
      $config_factory,
      $this->createMock(LdapDetailLog::class),
      $this->createMock(TokenProcessor::class),
      $this->createMock(AuthmapInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(FileSystemInterface::class),
      $this->createMock(Token::class),
      $module_handler,
      $this->createMock(AccountInterface::class),
      $this->createMock(LdapUserManager::class),
      $event_dispatcher,
      $this->createMock(FieldProvider::class),
      $this->createMock(MessengerInterface::class),
      $this->createMock(PasswordGeneratorInterface::class),
      $this->createMock(ContainerInterface::class),
    );

    $processor->drupalUserLogsIn($account);
  }

  /**
   * Scheduled update saves without dispatching LdapUserLoginEvent.
   */
  public function testDrupalUserScheduledUpdateDoesNotDispatchLoginEvent(): void {
    $ldap_user_settings = $this->createMock(ImmutableConfig::class);
    $ldap_user_settings->method('get')
      ->willReturnMap([
        ['drupalAcctProvisionTriggers', []],
        ['drupalAcctProvisionServer', NULL],
      ]);

    $auth_settings = $this->createMock(ImmutableConfig::class);
    $auth_settings->method('get')
      ->with('skipAdministrators')
      ->willReturn(FALSE);

    $config_factory = $this->createMock(ConfigFactory::class);
    $config_factory->method('get')
      ->willReturnMap([
        ['ldap_user.settings', $ldap_user_settings],
        ['ldap_authentication.settings', $auth_settings],
      ]);

    $module_handler = $this->createMock(ModuleHandlerInterface::class);
    $module_handler->method('moduleExists')
      ->with('file')
      ->willReturn(FALSE);

    $event_dispatcher = $this->createMock(EventDispatcherInterface::class);
    $event_dispatcher->expects($this->never())
      ->method('dispatch');

    $exclude = $this->createMock(FieldItemListInterface::class);
    $exclude->method('getString')->willReturn('');
    $account = $this->createMock(UserInterface::class);
    $account->method('get')
      ->with('ldap_user_ldap_exclude')
      ->willReturn($exclude);
    $account->expects($this->once())->method('save');

    $processor = new DrupalUserProcessor(
      $this->createMock(LoggerInterface::class),
      $config_factory,
      $this->createMock(LdapDetailLog::class),
      $this->createMock(TokenProcessor::class),
      $this->createMock(AuthmapInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(FileSystemInterface::class),
      $this->createMock(Token::class),
      $module_handler,
      $this->createMock(AccountInterface::class),
      $this->createMock(LdapUserManager::class),
      $event_dispatcher,
      $this->createMock(FieldProvider::class),
      $this->createMock(MessengerInterface::class),
      $this->createMock(PasswordGeneratorInterface::class),
      $this->createMock(ContainerInterface::class),
    );

    $processor->drupalUserScheduledUpdate($account);
  }

}
