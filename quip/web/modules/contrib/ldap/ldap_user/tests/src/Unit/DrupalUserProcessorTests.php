<?php

namespace Drupal\Tests\ldap_user\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\ldap_servers\LdapUserAttributesInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests for the DrupalUserProcessor.
 *
 * @coversDefaultClass \Drupal\ldap_user\Processor\DrupalUserProcessor
 * @group ldap
 */
class DrupalUserProcessorTests extends UnitTestCase implements LdapUserAttributesInterface {

  public $cacheFactory;
  public $configFactory;
  public $serverFactory;
  public $config;
  public $container;

  public $provisioningEvents;

  /**
   * Test setup.
   */
  protected function setUp() {
    parent::setUp();

    $this->provisioningEvents = [
      self::PROVISION_TO_DRUPAL => [
        self::EVENT_SYNC_TO_DRUPAL_USER,
        self::EVENT_SYNC_TO_DRUPAL_USER,
      ],

      self::PROVISION_TO_LDAP => [
        self::EVENT_SYNC_TO_LDAP_ENTRY,
        self::EVENT_CREATE_LDAP_ENTRY,
      ],
    ];

    $this->config = $this->getMockBuilder('\Drupal\Core\Config\ImmutableConfig')
      ->disableOriginalConstructor()
      ->getMock();

    $this->configFactory = $this->getMockBuilder('\Drupal\Core\Config\ConfigFactory')
      ->disableOriginalConstructor()
      ->getMock();

    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('ldap_user.settings')
      ->willReturn($this->config);

    $this->cacheFactory = $this->getMockBuilder('\Drupal\Core\Cache\CacheFactory')
      ->disableOriginalConstructor()
      ->getMock();

    $this->cacheFactory->expects($this->any())
      ->method('get')
      ->willReturn(FALSE);

    $this->detailLog = $this->getMockBuilder('\Drupal\ldap_servers\Logger\LdapDetailLog')
      ->disableOriginalConstructor()
      ->getMock();

    $this->container = new ContainerBuilder();
    $this->container->set('config.factory', $this->configFactory);
    $this->container->set('cache.default', $this->cacheFactory);
    $this->container->set('ldap.detail_log', $this->detailLog);
    \Drupal::setContainer($this->container);
  }

  /**
   * Placeholder test.
   */
  public function testBase() {
    $this->assertTrue(TRUE);
  }

  // @TODO: Write test to show basic functionality of creating users via
  // provisionDrupalAccount() works.
  // @TODO: Write test to show that syncing to existing Drupal users works.
  // @TODO: Write a test showing that a constant value gets passend on
  // correctly, i.e. ldap_attr is "Faculty" instead of [type].
  // @TODO: Write a test validating compound tokens, i.e. ldap_attr is
  // '[cn]@hogwarts.edu' or '[givenName] [sn]'.
  // @TODO: Write a test validating multiple mail properties, i.e. [mail]
  // returns the following and we get both:
  // [['mail' => 'hpotter@hogwarts.edu'], ['mail' => 'hpotter@owlmail.com']].
  // @TODO: Write a test validating non-integer values on the account status.
  // @TODO: Write a test for applyAttributes for binary fields.
  // @TODO: Write a test for applyAttributes for case sensitivity in tokens.
  // @TODO: Write a test for applyAttributes for user_attr in mappings.
  // @TODO: Write a test to prove puid update works, with and without binary mode
  // and including a conflicting account.
}
