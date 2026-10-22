<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_authentication\Kernel;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ldap_authentication\Routing\EmailTemplateService;
use Drupal\ldap_authentication\Routing\RouteSubscriber;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests email template redirects and password-reset access.
 */
#[Group('ldap')]
#[RunTestsInSeparateProcesses]
class EmailTemplateAndRouteTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'externalauth',
    'ldap_authentication',
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
    $this->installSchema('externalauth', ['authmap']);
    $this->installConfig(['ldap_authentication', 'ldap_user', 'system']);
  }

  /**
   * Anonymous users do not need a profile update.
   */
  public function testProfileNeedsUpdateAnonymous(): void {
    self::assertFalse(EmailTemplateService::profileNeedsUpdate());
  }

  /**
   * Matching mail regex requires an update.
   */
  public function testProfileNeedsUpdateForTemplateMail(): void {
    $this->config('ldap_authentication.settings')
      ->set('emailTemplateUsagePromptRegex', '@hogwarts\.edu$')
      ->save();

    $account = User::create([
      'name' => 'hpotter',
      'mail' => 'hpotter@hogwarts.edu',
      'status' => 1,
    ]);
    $account->save();
    $this->container->get('current_user')->setAccount($account);
    self::assertTrue(EmailTemplateService::profileNeedsUpdate());
  }

  /**
   * Disabled prompt skips the request subscriber work.
   */
  public function testCheckTemplateDisabled(): void {
    $this->config('ldap_authentication.settings')
      ->set('emailTemplateUsagePromptUser', FALSE)
      ->save();
    $service = new EmailTemplateService($this->container->get('config.factory'));
    $event = new RequestEvent(
      $this->container->get('http_kernel'),
      Request::create('/'),
      HttpKernelInterface::MAIN_REQUEST
    );
    $service->checkTemplate($event);
    self::assertFalse($event->hasResponse());
  }

  /**
   * Mixed mode allows anonymous password reset.
   */
  public function testResetPasswordMixedAllowsAnonymous(): void {
    $this->config('ldap_authentication.settings')
      ->set('authenticationMode', 'mixed')
      ->save();
    self::assertInstanceOf(
      AccessResultAllowed::class,
      RouteSubscriber::validateResetPasswordAllowed()
    );
  }

  /**
   * Exclusive mode without password updates is forbidden.
   */
  public function testResetPasswordExclusiveForbidden(): void {
    $this->config('ldap_authentication.settings')
      ->set('authenticationMode', 'exclusive')
      ->set('passwordOption', 'disable')
      ->save();
    self::assertInstanceOf(
      AccessResultForbidden::class,
      RouteSubscriber::validateResetPasswordAllowed()
    );
  }

  /**
   * Exclusive mode with passwordOption allow is permitted.
   */
  public function testResetPasswordExclusiveAllow(): void {
    $this->config('ldap_authentication.settings')
      ->set('authenticationMode', 'exclusive')
      ->set('passwordOption', 'allow')
      ->save();
    self::assertInstanceOf(
      AccessResultAllowed::class,
      RouteSubscriber::validateResetPasswordAllowed()
    );
  }

  /**
   * LDAP-mapped authenticated users cannot reset passwords.
   */
  public function testResetPasswordLdapUserForbidden(): void {
    $account = User::create([
      'name' => 'hpotter',
      'mail' => 'hpotter@example.com',
      'status' => 1,
    ]);
    $account->save();
    $this->container->get('externalauth.authmap')
      ->save($account, 'ldap_user', 'hpotter');
    $this->container->get('current_user')->setAccount($account);
    $this->config('ldap_authentication.settings')
      ->set('authenticationMode', 'mixed')
      ->save();
    self::assertInstanceOf(
      AccessResultForbidden::class,
      RouteSubscriber::validateResetPasswordAllowed()
    );
  }

}
