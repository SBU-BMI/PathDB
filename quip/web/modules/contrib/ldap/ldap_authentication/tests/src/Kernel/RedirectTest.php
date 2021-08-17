<?php

declare(strict_types = 1);

namespace Drupal\Tests\ldap_authentication\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ldap_authentication\Controller\LdapHelpRedirect;

/**
 * Redirect tests.
 *
 * @group ldap
 */
class RedirectTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'externalauth',
    'ldap_servers',
    'ldap_authentication',
    'ldap_user',
    'ldap_query',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('ldap_authentication');
  }

  /**
   * Test the help controller.
   *
   * TrustedRedirectResponse does not actually validate the url.
   */
  public function testRedirectController(): void {
    $controller = LdapHelpRedirect::create($this->container);

    $config = $this->config('ldap_authentication.settings');
    $config->set('ldapUserHelpLinkUrl', 'https://www.example.com/123.html');
    $config->save();
    $redirect = $controller->redirectUrl();
    self::assertEquals('https://www.example.com/123.html', $redirect->getTargetUrl());
    self::assertEquals(0, $redirect->getAge());
    self::assertEquals(0, $redirect->getCacheableMetadata()->getCacheMaxAge());
  }

}
