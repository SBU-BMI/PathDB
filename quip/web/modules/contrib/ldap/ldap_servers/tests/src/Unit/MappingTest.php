<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_servers\Unit;

use Drupal\ldap_servers\Mapping;
use Drupal\Tests\UnitTestCase;

/**
 * Tests Mapping class.
 *
 * @group ldap_servers
 */
class MappingTest extends UnitTestCase {

  /**
   * The mapping.
   *
   * @var \Drupal\ldap_servers\Mapping
   */
  protected $mapping;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->mapping = new Mapping(
      'id',
      'label',
      TRUE,
      FALSE,
      [],
      'notes',
      'notes'
    );
  }

  /**
   * Test the getLAbel method.
   */
  public function testGetLabel(): void {
    $this->assertEquals('label', $this->mapping->getLabel());
  }

  /**
   * Test the setLabel method.
   */
  public function testSetLabel(): void {
    $this->mapping->setLabel('new label');
    $this->assertEquals('new label', $this->mapping->getLabel());
  }

  /**
   * Test the isConfigurable method.
   */
  public function testIsConfigurable(): void {
    $this->assertTrue($this->mapping->isConfigurable());
  }

  /**
   * Test the setConfigurable method.
   */
  public function testGetNotes(): void {
    $this->assertNull($this->mapping->getNotes());
  }

  /**
   * Test the setNotes method.
   */
  public function testSetNotes(): void {
    $this->mapping->setNotes('new notes');
    $this->assertEquals('new notes', $this->mapping->getNotes());
  }

  /**
   * Test the isEnabled method.
   */
  public function testIsEnabled(): void {
    $this->assertFalse($this->mapping->isEnabled());
  }

  /**
   * Test the setEnabled method.
   */
  public function testSetEnables(): void {
    $this->mapping->setEnabled(TRUE);
    $this->assertTrue($this->mapping->isEnabled());
  }

  /**
   * Test the getProvisioningEvents method.
   */
  public function testGetProvisioningEvents(): void {
    $this->assertEquals([], $this->mapping->getProvisioningEvents());
  }

  /**
   * Test the setProvisioningEvents method.
   */
  public function testHasProvisioningEvent(): void {
    $this->assertFalse($this->mapping->hasProvisioningEvent('event'));
  }

  /**
   * Test the setProvisioningEvents method.
   */
  public function testSetProvisioningEvents(): void {
    $this->mapping->setProvisioningEvents(['event']);
    $this->assertEquals(['event'], $this->mapping->getProvisioningEvents());
  }

  /**
   * Test the getConfigurationModule method.
   */
  public function testGetConfigurationModule(): void {
    $this->assertEquals('notes', $this->mapping->getConfigurationModule());
  }

  /**
   * Test the setConfigurationModule method.
   */
  public function testSetConfigurationModule(): void {
    $this->mapping->setConfigurationModule('new notes');
    $this->assertEquals('new notes', $this->mapping->getConfigurationModule());
  }

  /**
   * Test the getProvisioningModule method.
   */
  public function testGetProvisioningModule(): void {
    $this->assertEquals('notes', $this->mapping->getProvisioningModule());
  }

  /**
   * Test the setProvisioningModule method.
   */
  public function testSetProvisioningModule(): void {
    $this->mapping->setProvisioningModule('new notes');
    $this->assertEquals('new notes', $this->mapping->getProvisioningModule());
  }

  /**
   * Test the getLdapAttribute method.
   */
  public function testGetLdapAttribute(): void {
    $this->assertEmpty($this->mapping->getLdapAttribute());
  }

  /**
   * Test the setLdapAttribute method.
   */
  public function testSetLdapAttribute(): void {
    $this->mapping->setLdapAttribute('new notes');
    $this->assertEquals('new notes', $this->mapping->getLdapAttribute());
  }

  /**
   * Test the getDrupalAttribute method.
   */
  public function testGetDrupalAttribute(): void {
    $this->assertEmpty($this->mapping->getDrupalAttribute());
  }

  /**
   * Test the setDrupalAttribute method.
   */
  public function testSetDrupalAttribute(): void {
    $this->mapping->setDrupalAttribute('new notes');
    $this->assertEquals('new notes', $this->mapping->getDrupalAttribute());
  }

  /**
   * Test the getId method.
   */
  public function testGetId(): void {
    $this->assertEquals('id', $this->mapping->getId());
  }

  /**
   * Test the getUserTokens method.
   */
  public function testGetUserTokens(): void {
    $this->assertEmpty($this->mapping->getUserTokens());
  }

  /**
   * Test the setUserTokens method.
   */
  public function testSetUserTokens(): void {
    $this->mapping->setUserTokens('token');
    $this->assertEquals('token', $this->mapping->getUserTokens());
  }

  /**
   * Test the isBinary method.
   */
  public function testIsBinary(): void {
    $this->assertFalse($this->mapping->isBinary());
  }

  /**
   * Test the setBinary method.
   */
  public function testConvertBinary(): void {
    $this->mapping->convertBinary(TRUE);
    $this->assertTrue($this->mapping->isBinary());
  }

  /**
   * Test the setConfigurable method.
   */
  public function testSetConfigurable(): void {
    $this->mapping->setConfigurable(FALSE);
    $this->assertFalse($this->mapping->isConfigurable());

    $this->mapping->setConfigurable(TRUE);
    $this->assertTrue($this->mapping->isConfigurable());
  }

  /**
   * Test the unserialize method.
   */
  public function testSerialize(): void {
    $this->assertEquals(
      [
        'prov_events' => [],
        'config_module' => 'notes',
        'prov_module' => 'notes',
        'ldap_attr' => '',
        'user_tokens' => '',
        'user_attr' => '',
        'convert' => FALSE,
      ],
      $this->mapping->serialize()
    );
  }

}
