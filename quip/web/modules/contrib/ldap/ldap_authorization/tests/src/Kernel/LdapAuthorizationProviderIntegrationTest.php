<?php

declare(strict_types = 1);

namespace Drupal\Tests\ldap_authorization\Kernel;

use Drupal\authorization_drupal_roles\Plugin\authorization\Consumer\DrupalRolesConsumer;
use Drupal\KernelTests\KernelTestBase;

/**
 * Integration tests for LdapAuthorizationProvider.
 *
 * @group ldap
 */
class LdapAuthorizationProviderIntegrationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'system',
    'field',
    'text',
    'filter',
    'entity_test',
    'authorization',
    'ldap_servers',
    'ldap_authorization',
    'externalauth',
  ];

  /**
   * Consumer plugin.
   *
   * @var \Drupal\authorization_drupal_roles\Plugin\authorization\Consumer\DrupalRolesConsumer
   */
  protected $consumerPlugin;

  /**
   * Setup of kernel tests.
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(['field', 'text']);

    $this->consumerPlugin = $this->getMockBuilder(DrupalRolesConsumer::class)
      ->disableOriginalConstructor()
      ->setMethods(NULL)
      ->getMock();
  }

  /**
   * Test Provider.
   */
  public function testProvider(): void {
    self::markTestIncomplete('Test missing');
  }

}
