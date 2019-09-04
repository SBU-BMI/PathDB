<?php

namespace Drupal\Tests\ldap_user\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\ldap_servers\LdapUserAttributesInterface;
use Drupal\Tests\UnitTestCase;
use ReflectionClass;

/**
 * @coversDefaultClass \Drupal\ldap_user\Helper\SyncMappingHelper
 * @group ldap
 */
class SyncMappingHelperTests extends UnitTestCase implements LdapUserAttributesInterface {

  public $configFactory;
  public $serverFactory;
  public $config;
  public $container;

  /**
   * Prepare the sync mapping tests.
   */
  protected function setUp() {
    parent::setUp();

    /* Mocks the configuration due to detailed watchdog logging. */
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

    $this->container = new ContainerBuilder();
    $this->container->set('config.factory', $this->configFactory);
    \Drupal::setContainer($this->container);
  }

  /**
   * Prove that field syncs work and provide the demo data here.
   */
  public function testSyncValidatorIsSynced() {
    $syncTestData = [
      'drupal' => [
        '[field.ldap_user_puid_sid]' => [
          // Actually TranslatableMarkup.
          'name' => 'SID',
          'configurable_to_drupal' => 0,
          'configurable_to_ldap' => 1,
          'notes' => 'not configurable',
          'direction' => 'drupal',
          'enabled' => TRUE,
          'prov_events' => [
            self::EVENT_CREATE_DRUPAL_USER,
          ],
        ],
        '[property.name]' => [
          // Actually TranslatableMarkup.
          'name' => 'Name',
          'source' => '[cn]',
          'direction' => 'drupal',
          'enabled' => TRUE,
          'prov_events' => [
            self::EVENT_CREATE_DRUPAL_USER,
            self::EVENT_SYNC_TO_DRUPAL_USER,
          ],
        ],
      ],
      'ldap' => [
        '[property.name]' => [
          // Actually TranslatableMarkup.
          'name' => 'Name',
          'source' => '',
          'direction' => 'ldap',
          'enabled' => TRUE,
          'prov_events' => [
            self::EVENT_CREATE_LDAP_ENTRY,
            self::EVENT_SYNC_TO_LDAP_ENTRY,
          ],
          'configurable_to_ldap' => TRUE,
        ],
      ],
    ];

    $processor = $this->getMockBuilder('Drupal\ldap_user\Helper\SyncMappingHelper')
      ->setMethods(['processSyncMappings'])
      ->disableOriginalConstructor()
      ->getMock();

    $reflection = new ReflectionClass(get_class($processor));
    $method = $reflection->getMethod('setAllSyncMappings');
    $method->setAccessible(TRUE);
    $method->invoke($processor, $syncTestData);

    /** @var \Drupal\ldap_user\Helper\SyncMappingHelper $processor */
    $isSynced = $processor->isSynced('[field.ldap_user_puid_sid]', [self::EVENT_CREATE_DRUPAL_USER], self::PROVISION_TO_DRUPAL);
    $this->assertTrue($isSynced);

    $isSynced = $processor->isSynced('[field.ldap_user_puid_sid]', [self::EVENT_CREATE_DRUPAL_USER], self::PROVISION_TO_LDAP);
    $this->assertFalse($isSynced);

    $isSynced = $processor->isSynced('[field.ldap_user_puid_sid]', [self::EVENT_CREATE_LDAP_ENTRY], self::PROVISION_TO_LDAP);
    $this->assertFalse($isSynced);

    $isSynced = $processor->isSynced('[field.ldap_user_puid_sid]', [self::EVENT_CREATE_LDAP_ENTRY], self::PROVISION_TO_LDAP);
    $this->assertFalse($isSynced);

    $isSynced = $processor->isSynced('[property.name]', [self::EVENT_CREATE_LDAP_ENTRY], self::PROVISION_TO_LDAP);
    $this->assertTrue($isSynced);

    $isSynced = $processor->isSynced('[property.xyz]', [self::EVENT_CREATE_DRUPAL_USER], self::PROVISION_TO_DRUPAL);
    $this->assertFalse($isSynced);

    // TODO: Review behaviour. Should this actually be allowed that one of many
    // events returns true?
    $isSynced = $processor->isSynced('[field.ldap_user_puid_sid]', [self::EVENT_CREATE_DRUPAL_USER, self::EVENT_SYNC_TO_DRUPAL_USER], self::PROVISION_TO_DRUPAL);
    $this->assertTrue($isSynced);

  }

  // TODO: Write test for getSyncMappings().
  // TODO: Write test for getLdapUserRequiredAttributes().
}
