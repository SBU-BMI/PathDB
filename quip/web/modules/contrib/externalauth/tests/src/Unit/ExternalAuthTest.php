<?php

namespace Drupal\Tests\externalauth\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Render\Element\Email;
use Drupal\externalauth\AuthmapInterface;
use Drupal\externalauth\ExternalAuth;
use Drupal\externalauth\ExternalAuthStorageLimits;
use Drupal\externalauth\Event\ExternalAuthAuthmapAlterEvent;
use Drupal\externalauth\Event\ExternalAuthEvents;
use Drupal\externalauth\Event\ExternalAuthRegisterEvent;
use Drupal\externalauth\Exception\ExternalAuthRegisterException;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * ExternalAuth unit tests.
 *
 * @ingroup externalauth
 *
 * @group externalauth
 *
 * @coversDefaultClass \Drupal\externalauth\ExternalAuth
 */
class ExternalAuthTest extends UnitTestCase {

  /**
   * The mocked entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The mocked authmap service.
   *
   * @var \Drupal\externalauth\AuthmapInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $authmap;

  /**
   * The mocked logger instance.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $logger;

  /**
   * The mocked event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a mock EntityTypeManager object.
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    // Create a Mock Logger object.
    $this->logger = $this->createMock(LoggerInterface::class);

    // Create a Mock EventDispatcher object.
    $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

    // Create a Mock Authmap object.
    $this->authmap = $this->createMock(AuthmapInterface::class);
  }

  /**
   * Test the load() method.
   *
   * @covers ::load
   * @covers ::__construct
   */
  public function testLoad() {
    // Set up a mock for Authmap class,
    // mocking getUid() method.
    $authmap = $this->createMock(AuthmapInterface::class);

    $authmap->expects($this->once())
      ->method('getUid')
      ->willReturn(2);

    // Mock the User storage layer.
    $account = $this->createMock(UserInterface::class);
    $entity_storage = $this->createMock(EntityStorageInterface::class);
    // Expect the external loading method to return a user object.
    $entity_storage->expects($this->once())
      ->method('load')
      ->willReturn($account);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->willReturn($entity_storage);

    $externalauth = new ExternalAuth(
      $this->entityTypeManager,
      $authmap,
      $this->logger,
      $this->eventDispatcher
    );
    $result = $externalauth->load("test_authname", "test_provider");
    $this->assertInstanceOf(UserInterface::class, $result);
  }

  /**
   * Test the login() method.
   *
   * @covers ::login
   * @covers ::__construct
   */
  public function testLogin() {
    // Set up a mock for ExternalAuth class,
    // mocking load() & userLoginFinalize() methods.
    $externalauth = $this->getMockBuilder('Drupal\externalauth\ExternalAuth')
      ->onlyMethods(['load', 'userLoginFinalize'])
      ->setConstructorArgs([
        $this->entityTypeManager,
        $this->authmap,
        $this->logger,
        $this->eventDispatcher,
      ])
      ->getMock();

    // Mock load method.
    $externalauth->expects($this->once())
      ->method('load')
      ->willReturn(FALSE);

    // Expect userLoginFinalize() to not be called.
    $externalauth->expects($this->never())
      ->method('userLoginFinalize');

    $result = $externalauth->login("test_authname", "test_provider");
    $this->assertEquals(FALSE, $result);
  }

  /**
   * Test the register() method.
   *
   * @covers ::register
   * @covers ::__construct
   *
   * @dataProvider registerDataProvider
   */
  public function testRegister($registration_data, $expected_data) {
    // Mock the returned User object.
    $account = $this->createMock(UserInterface::class);
    $account->expects($this->once())
      ->method('enforceIsNew');
    $account->expects($this->once())
      ->method('save');
    $account->expects($this->any())
      ->method('getTimeZone')
      ->willReturn($expected_data['timezone']);

    // Mock the User storage layer to create a new user.
    $entity_storage = $this->createMock(EntityStorageInterface::class);
    $entity_storage->expects($this->once())
      ->method('create')
      ->with($expected_data['created_data'])
      ->willReturn($account);
    $entity_storage->expects($this->once())
      ->method('loadByProperties')
      ->with(['name' => $expected_data['username']])
      ->willReturn([]);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('user')
      ->willReturn($entity_storage);

    $authmap = $this->createMock(AuthmapInterface::class);
    $authmap->expects($this->once())
      ->method('save')
      ->with($account, $registration_data['provider'], $expected_data['authname'], $expected_data['data']);

    $this->eventDispatcher->expects($this->exactly(2))
      ->method('dispatch')
      ->willReturnCallback(function ($event, string $event_name) {
        static $call = 0;
        $call++;

        if ($call === 1) {
          $this->assertInstanceOf(ExternalAuthAuthmapAlterEvent::class, $event);
          $this->assertSame(ExternalAuthEvents::AUTHMAP_ALTER, $event_name);
        }
        else {
          $this->assertInstanceOf(ExternalAuthRegisterEvent::class, $event);
          $this->assertSame(ExternalAuthEvents::REGISTER, $event_name);
        }

        return $event;
      });

    $externalauth = new ExternalAuth(
      $this->entityTypeManager,
      $authmap,
      $this->logger,
      $this->eventDispatcher
    );
    $registered_account = $externalauth->register($registration_data['authname'], $registration_data['provider'], $registration_data['account_data'], $registration_data['authmap_data']);
    $this->assertInstanceOf(UserInterface::class, $registered_account);
    $this->assertEquals($expected_data['timezone'], $registered_account->getTimeZone());
  }

  /**
   * Provides test data for testRegister.
   *
   * @return array
   *   Parameters
   */
  public static function registerDataProvider(): array {
    return [
      // Test basic registration.
      [
        [
          'authname' => 'test_authname',
          'provider' => 'test_provider',
          'account_data' => [],
          'authmap_data' => NULL,
        ],
        [
          'username' => 'test_provider_test_authname',
          'authname' => 'test_authname',
          'timezone' => 'Europe/Brussels',
          'data' => NULL,
          'created_data' => [
            'name' => 'test_provider_test_authname',
            'init' => 'test_provider_test_authname',
            'status' => 1,
            'access' => 0,
          ],
        ],
      ],
      // Test with added account data.
      [
        [
          'authname' => 'test_authname',
          'provider' => 'test_provider',
          'account_data' => ['timezone' => 'Europe/Prague'],
          'authmap_data' => NULL,
        ],
        [
          'username' => 'test_provider_test_authname',
          'authname' => 'test_authname',
          'timezone' => 'Europe/Prague',
          'data' => NULL,
          'created_data' => [
            'name' => 'test_provider_test_authname',
            'init' => 'test_provider_test_authname',
            'status' => 1,
            'access' => 0,
            'timezone' => 'Europe/Prague',
          ],
        ],
      ],
      // Test with added authmap data.
      [
        [
          'authname' => 'test_authname',
          'provider' => 'test_provider',
          'account_data' => [],
          'authmap_data' => ['extra_property' => 'extra'],
        ],
        [
          'username' => 'test_provider_test_authname',
          'authname' => 'test_authname',
          'timezone' => 'Europe/Brussels',
          'data' => ['extra_property' => 'extra'],
          'created_data' => [
            'name' => 'test_provider_test_authname',
            'init' => 'test_provider_test_authname',
            'status' => 1,
            'access' => 0,
          ],
        ],
      ],
    ];
  }

  /**
   * Tests registration validation failures before persistence.
   *
   * @covers ::register
   *
   * @dataProvider registerValidationDataProvider
   */
  public function testRegisterRejectsOversizedValues(array $registration_data, callable $event_alter, string $expected_message, bool $expects_user_storage = TRUE) {
    $entity_storage = $this->createMock(EntityStorageInterface::class);
    $entity_storage->expects($expects_user_storage ? $this->once() : $this->never())
      ->method('loadByProperties')
      ->with($this->callback(static fn(array $properties): bool => isset($properties['name']) && is_string($properties['name'])))
      ->willReturn([]);
    $entity_storage->expects($this->never())
      ->method('create');

    $this->entityTypeManager->expects($expects_user_storage ? $this->once() : $this->never())
      ->method('getStorage')
      ->with('user')
      ->willReturn($entity_storage);

    $authmap = $this->createMock(AuthmapInterface::class);
    $authmap->expects($this->never())
      ->method('save');

    $this->eventDispatcher->expects($expects_user_storage ? $this->once() : $this->never())
      ->method('dispatch')
      ->willReturnCallback(function (ExternalAuthAuthmapAlterEvent $event, string $event_name) use ($event_alter) {
        $this->assertSame(ExternalAuthEvents::AUTHMAP_ALTER, $event_name);
        return $event_alter($event);
      });

    $externalauth = new ExternalAuth(
      $this->entityTypeManager,
      $authmap,
      $this->logger,
      $this->eventDispatcher
    );

    $this->expectException(ExternalAuthRegisterException::class);
    $this->expectExceptionMessage($expected_message);
    $externalauth->register($registration_data['authname'], $registration_data['provider'], $registration_data['account_data'], $registration_data['authmap_data']);
  }

  /**
   * Provides oversized registration validation test cases.
   */
  public static function registerValidationDataProvider(): array {
    return [
      'provider too long' => [
        [
          'authname' => 'test_authname',
          'provider' => str_repeat('p', ExternalAuthStorageLimits::AUTHMAP_PROVIDER_MAX_LENGTH + 1),
          'account_data' => [],
          'authmap_data' => NULL,
        ],
        static function (ExternalAuthAuthmapAlterEvent $event): ExternalAuthAuthmapAlterEvent {
          return $event;
        },
        sprintf('The authentication provider exceeds the maximum length of %d characters.', ExternalAuthStorageLimits::AUTHMAP_PROVIDER_MAX_LENGTH),
      ],
      'authname too long' => [
        [
          'authname' => str_repeat('a', ExternalAuthStorageLimits::AUTHMAP_AUTHNAME_MAX_LENGTH + 1),
          'provider' => 'test_provider',
          'account_data' => [],
          'authmap_data' => NULL,
        ],
        static function (ExternalAuthAuthmapAlterEvent $event): ExternalAuthAuthmapAlterEvent {
          return $event;
        },
        sprintf('The external authentication name exceeds the maximum length of %d characters.', ExternalAuthStorageLimits::AUTHMAP_AUTHNAME_MAX_LENGTH),
      ],
      'username too long after authmap alter' => [
        [
          'authname' => 'test_authname',
          'provider' => 'test_provider',
          'account_data' => [],
          'authmap_data' => NULL,
        ],
        static function (ExternalAuthAuthmapAlterEvent $event): ExternalAuthAuthmapAlterEvent {
          $event->setUsername(str_repeat('u', UserInterface::USERNAME_MAX_LENGTH + 1));
          return $event;
        },
        sprintf('The username exceeds the maximum length of %d characters.', UserInterface::USERNAME_MAX_LENGTH),
      ],
      'init value too long' => [
        [
          'authname' => str_repeat('a', Email::EMAIL_MAX_LENGTH - ExternalAuthStorageLimits::AUTHMAP_PROVIDER_MAX_LENGTH + 1),
          'provider' => str_repeat('p', ExternalAuthStorageLimits::AUTHMAP_PROVIDER_MAX_LENGTH),
          'account_data' => [],
          'authmap_data' => NULL,
        ],
        static function (ExternalAuthAuthmapAlterEvent $event): ExternalAuthAuthmapAlterEvent {
          $event->setUsername('valid_username');
          return $event;
        },
        sprintf('The initial account value exceeds the maximum length of %d characters.', Email::EMAIL_MAX_LENGTH),
      ],
      'custom init value too long' => [
        [
          'authname' => 'test_authname',
          'provider' => 'test_provider',
          'account_data' => ['init' => str_repeat('i', Email::EMAIL_MAX_LENGTH + 1)],
          'authmap_data' => NULL,
        ],
        static function (ExternalAuthAuthmapAlterEvent $event): ExternalAuthAuthmapAlterEvent {
          return $event;
        },
        sprintf('The initial account value exceeds the maximum length of %d characters.', Email::EMAIL_MAX_LENGTH),
      ],
      'mail value too long' => [
        [
          'authname' => 'test_authname',
          'provider' => 'test_provider',
          'account_data' => ['mail' => str_repeat('m', Email::EMAIL_MAX_LENGTH + 1)],
          'authmap_data' => NULL,
        ],
        static function (ExternalAuthAuthmapAlterEvent $event): ExternalAuthAuthmapAlterEvent {
          return $event;
        },
        sprintf('The email address exceeds the maximum length of %d characters.', Email::EMAIL_MAX_LENGTH),
      ],
      'username is null' => [
        [
          'authname' => 'test_authname',
          'provider' => 'test_provider',
          'account_data' => ['name' => NULL],
          'authmap_data' => NULL,
        ],
        static function (ExternalAuthAuthmapAlterEvent $event): ExternalAuthAuthmapAlterEvent {
          return $event;
        },
        'The username must be a string.',
        FALSE,
      ],
      'username is empty string' => [
        [
          'authname' => 'test_authname',
          'provider' => 'test_provider',
          'account_data' => ['name' => ''],
          'authmap_data' => NULL,
        ],
        static function (ExternalAuthAuthmapAlterEvent $event): ExternalAuthAuthmapAlterEvent {
          return $event;
        },
        'The username cannot be empty.',
        FALSE,
      ],
      'multibyte authname too long after authmap alter' => [
        [
          'authname' => str_repeat('ñ', ExternalAuthStorageLimits::AUTHMAP_AUTHNAME_MAX_LENGTH),
          'provider' => 'test_provider',
          'account_data' => ['name' => 'valid_username'],
          'authmap_data' => NULL,
        ],
        static function (ExternalAuthAuthmapAlterEvent $event): ExternalAuthAuthmapAlterEvent {
          $event->setAuthname(str_repeat('ñ', ExternalAuthStorageLimits::AUTHMAP_AUTHNAME_MAX_LENGTH + 1));
          return $event;
        },
        sprintf('The external authentication name exceeds the maximum length of %d characters.', ExternalAuthStorageLimits::AUTHMAP_AUTHNAME_MAX_LENGTH),
      ],
    ];
  }

  /**
   * Test the loginRegister() method.
   *
   * @covers ::loginRegister
   * @covers ::__construct
   */
  public function testLoginRegister() {
    $account = $this->createMock(UserInterface::class);

    // Set up a mock for ExternalAuth class,
    // mocking login(), register() & userLoginFinalize() methods.
    $externalauth = $this->getMockBuilder('Drupal\externalauth\ExternalAuth')
      ->onlyMethods(['login', 'register', 'userLoginFinalize'])
      ->setConstructorArgs([
        $this->entityTypeManager,
        $this->authmap,
        $this->logger,
        $this->eventDispatcher,
      ])
      ->getMock();

    // Mock ExternalAuth methods.
    $externalauth->expects($this->once())
      ->method('login')
      ->willReturn(FALSE);
    $externalauth->expects($this->once())
      ->method('register')
      ->willReturn($account);
    $externalauth->expects($this->once())
      ->method('userLoginFinalize')
      ->willReturn($account);

    $result = $externalauth->loginRegister("test_authname", "test_provider");
    $this->assertInstanceOf(UserInterface::class, $result);
  }

  /**
   * Test linking an existing account.
   *
   * @covers ::linkExistingAccount
   */
  public function testLinkExistingAccount() {
    $account = $this->createMock(UserInterface::class);
    $account->expects($this->once())
      ->method('id')
      ->willReturn(5);
    $account->expects($this->once())
      ->method('getAccountName')
      ->willReturn('Test username');

    $authmap = $this->createMock(AuthmapInterface::class);
    $authmap->expects($this->once())
      ->method('get')
      ->willReturn(FALSE);

    $authmap->expects($this->once())
      ->method('save');

    $this->eventDispatcher->expects($this->once())
      ->method('dispatch')
      ->willReturnCallback(function (ExternalAuthAuthmapAlterEvent $event, string $event_name) {
        $this->assertSame(ExternalAuthEvents::AUTHMAP_ALTER, $event_name);
        $event->setAuthname('Test authname');
        $event->setData('Test data');
        return $event;
      });

    $externalauth = new ExternalAuth(
      $this->entityTypeManager,
      $authmap,
      $this->logger,
      $this->eventDispatcher
    );
    $externalauth->linkExistingAccount("test_authname", "test_provider", $account);
  }

  /**
   * Tests linkExistingAccount() validation failures before authmap writes.
   *
   * @covers ::linkExistingAccount
   *
   * @dataProvider linkExistingAccountValidationDataProvider
   */
  public function testLinkExistingAccountRejectsOversizedValues(array $link_data, callable $event_alter, string $expected_message) {
    $account = $this->createMock(UserInterface::class);
    $account->expects($this->once())
      ->method('id')
      ->willReturn(5);
    $account->expects($this->once())
      ->method('getAccountName')
      ->willReturn('Test username');

    $authmap = $this->createMock(AuthmapInterface::class);
    $authmap->expects($this->once())
      ->method('get')
      ->with(5, $link_data['provider'])
      ->willReturn(FALSE);
    $authmap->expects($this->never())
      ->method('save');

    $this->eventDispatcher->expects($this->once())
      ->method('dispatch')
      ->willReturnCallback(function (ExternalAuthAuthmapAlterEvent $event, string $event_name) use ($event_alter) {
        $this->assertSame(ExternalAuthEvents::AUTHMAP_ALTER, $event_name);
        return $event_alter($event);
      });

    $externalauth = new ExternalAuth(
      $this->entityTypeManager,
      $authmap,
      $this->logger,
      $this->eventDispatcher
    );

    $this->expectException(ExternalAuthRegisterException::class);
    $this->expectExceptionMessage($expected_message);
    $externalauth->linkExistingAccount($link_data['authname'], $link_data['provider'], $account);
  }

  /**
   * Provides oversized authmap validation test cases for linking accounts.
   */
  public static function linkExistingAccountValidationDataProvider(): array {
    return [
      'provider too long' => [
        [
          'authname' => 'test_authname',
          'provider' => str_repeat('p', ExternalAuthStorageLimits::AUTHMAP_PROVIDER_MAX_LENGTH + 1),
        ],
        static function (ExternalAuthAuthmapAlterEvent $event): ExternalAuthAuthmapAlterEvent {
          return $event;
        },
        sprintf('The authentication provider exceeds the maximum length of %d characters.', ExternalAuthStorageLimits::AUTHMAP_PROVIDER_MAX_LENGTH),
      ],
      'authname too long after authmap alter' => [
        [
          'authname' => 'test_authname',
          'provider' => 'test_provider',
        ],
        static function (ExternalAuthAuthmapAlterEvent $event): ExternalAuthAuthmapAlterEvent {
          $event->setAuthname(str_repeat('a', ExternalAuthStorageLimits::AUTHMAP_AUTHNAME_MAX_LENGTH + 1));
          return $event;
        },
        sprintf('The external authentication name exceeds the maximum length of %d characters.', ExternalAuthStorageLimits::AUTHMAP_AUTHNAME_MAX_LENGTH),
      ],
    ];
  }

  /**
   * Tests multibyte authnames at the supported length boundary.
   *
   * @covers ::register
   */
  public function testRegisterAllowsMultibyteAuthnameAtBoundary() {
    $authname = str_repeat('ñ', ExternalAuthStorageLimits::AUTHMAP_AUTHNAME_MAX_LENGTH);
    $username = 'valid_username';

    $account = $this->createMock(UserInterface::class);
    $account->expects($this->once())
      ->method('enforceIsNew');
    $account->expects($this->once())
      ->method('save');

    $entity_storage = $this->createMock(EntityStorageInterface::class);
    $entity_storage->expects($this->once())
      ->method('loadByProperties')
      ->with(['name' => $username])
      ->willReturn([]);
    $entity_storage->expects($this->once())
      ->method('create')
      ->with([
        'name' => $username,
        'init' => 'test_provider_' . $authname,
        'status' => 1,
        'access' => 0,
      ])
      ->willReturn($account);
    $this->entityTypeManager->expects($this->once())
      ->method('getStorage')
      ->with('user')
      ->willReturn($entity_storage);

    $authmap = $this->createMock(AuthmapInterface::class);
    $authmap->expects($this->once())
      ->method('save')
      ->with($account, 'test_provider', $authname, NULL);

    $this->eventDispatcher->expects($this->exactly(2))
      ->method('dispatch')
      ->willReturnCallback(static function ($event) {
        return $event;
      });

    $externalauth = new ExternalAuth(
      $this->entityTypeManager,
      $authmap,
      $this->logger,
      $this->eventDispatcher
    );

    $registered_account = $externalauth->register($authname, 'test_provider', ['name' => $username]);
    $this->assertSame($account, $registered_account);
  }

}
