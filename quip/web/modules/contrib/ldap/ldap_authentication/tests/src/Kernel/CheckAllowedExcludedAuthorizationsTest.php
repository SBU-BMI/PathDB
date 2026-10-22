<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_authentication\Kernel;

use Drupal\authorization\AuthorizationServiceInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ldap_authentication\Controller\LoginValidatorLoginForm;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Symfony\Component\Ldap\Entry;

/**
 * Tests excludeIfNoAuthorizations in checkAllowedExcluded().
 *
 * Distinguishes a missing consumer configuration from a processed profile
 * that applied no mappings (see issue #3295284).
 */
#[Group('ldap')]
#[RunTestsInSeparateProcesses]
class CheckAllowedExcludedAuthorizationsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'authorization',
    'externalauth',
    'ldap_authentication',
    'ldap_authorization',
    'ldap_query',
    'ldap_servers',
    'ldap_user',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('authorization_profile');
    $this->installSchema('externalauth', ['authmap']);
    $this->installConfig(['ldap_authentication']);
    if (version_compare(\Drupal::VERSION, '10.2.0', '<')) {
      $this->installSchema('system', 'sequences');
    }

    $this->config('ldap_authentication.settings')
      ->set('excludeIfNoAuthorizations', TRUE)
      ->set('excludeIfTextInDn', [])
      ->set('allowOnlyIfTextInDn', [])
      ->save();
    $this->container->get('config.factory')->reset();
  }

  /**
   * Empty processed list is treated as a configuration error.
   */
  public function testEmptyProcessedAuthorizationsShowsConfigurationError(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('notice')
      ->with($this->stringContains('0 LDAP Authorization consumers are configured.'));

    $validator = $this->createValidator([], $logger);
    $allowed = $validator->checkAllowedExcluded(
      'hpotter',
      new Entry('cn=hpotter,ou=people,dc=hogwarts,dc=edu')
    );

    $this->assertFalse($allowed);
    $warnings = $this->container->get('messenger')
      ->messagesByType(MessengerInterface::TYPE_WARNING);
    $this->assertCount(1, $warnings);
    $this->assertStringContainsString(
      'The site logon is currently not working due to a configuration error',
      (string) reset($warnings)
    );
  }

  /**
   * A processed profile with no applied mappings denies without that error.
   */
  public function testProcessedProfileWithNoAppliedMappingsDeniesSilently(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->never())->method('notice');

    $authorization = $this->createAuthorizationResponse([]);
    $validator = $this->createValidator([$authorization], $logger);
    $allowed = $validator->checkAllowedExcluded(
      'hpotter',
      new Entry('cn=hpotter,ou=people,dc=hogwarts,dc=edu')
    );

    $this->assertFalse($allowed);
    $warnings = $this->container->get('messenger')
      ->messagesByType(MessengerInterface::TYPE_WARNING);
    $this->assertEmpty($warnings);
  }

  /**
   * Applied mappings allow the user through the authorization check.
   */
  public function testAppliedMappingsAllowAccess(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->never())->method('notice');

    $authorization = $this->createAuthorizationResponse(['student']);
    $validator = $this->createValidator([$authorization], $logger);
    $allowed = $validator->checkAllowedExcluded(
      'hpotter',
      new Entry('cn=hpotter,ou=people,dc=hogwarts,dc=edu')
    );

    $this->assertTrue($allowed);
    $warnings = $this->container->get('messenger')
      ->messagesByType(MessengerInterface::TYPE_WARNING);
    $this->assertEmpty($warnings);
  }

  /**
   * Builds a login validator with a stubbed authorization manager.
   *
   * @param array $processed_authorizations
   *   Values returned from getProcessedAuthorizations().
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger used to assert configuration-error notices.
   *
   * @return \Drupal\ldap_authentication\Controller\LoginValidatorLoginForm
   *   The validator under test.
   */
  private function createValidator(array $processed_authorizations, LoggerInterface $logger): LoginValidatorLoginForm {
    $authorization_manager = $this->createMock(AuthorizationServiceInterface::class);
    $authorization_manager->method('getProcessedAuthorizations')
      ->willReturn($processed_authorizations);
    $this->container->set('authorization.manager', $authorization_manager);

    return new LoginValidatorLoginForm(
      $this->container->get('config.factory'),
      $this->container->get('ldap.detail_log'),
      $logger,
      $this->container->get('entity_type.manager'),
      $this->container->get('module_handler'),
      $this->container->get('ldap.bridge'),
      $this->container->get('externalauth.authmap'),
      $this->container->get('ldap_authentication.servers'),
      $this->container->get('ldap.user_manager'),
      $this->container->get('messenger'),
      $this->container->get('ldap.drupal_user_processor'),
      $this->container
    );
  }

  /**
   * Builds a processed-authorization stand-in.
   *
   * @param array $applied
   *   Values for getAuthorizationsApplied().
   *
   * @return object
   *   Object exposing getAuthorizationsApplied().
   */
  private function createAuthorizationResponse(array $applied): object {
    return new class($applied) {

      /**
       * Applied authorization identifiers.
       *
       * @var array
       */
      private array $applied;

      /**
       * Constructs the stand-in.
       *
       * @param array $applied
       *   Applied authorization identifiers.
       */
      public function __construct(array $applied) {
        $this->applied = $applied;
      }

      /**
       * Returns applied authorizations.
       *
       * @return array
       *   Applied authorization identifiers.
       */
      public function getAuthorizationsApplied(): array {
        return $this->applied;
      }

    };
  }

}
