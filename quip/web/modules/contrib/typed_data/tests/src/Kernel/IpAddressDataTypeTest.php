<?php

declare(strict_types=1);

namespace Drupal\Tests\typed_data\Kernel;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\Type\StringInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the functionality of the IP address datatype.
 *
 * @group typed_data
 */
class IpAddressDataTypeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'typed_data'];

  /**
   * Tests the IP address data type.
   */
  public function testIpAddressDatatype(): void {
    $value = $this->randomString();
    $definition = DataDefinition::create('ip_address');
    /** @var \Drupal\typed_data\Plugin\DataType\IpAddressData $typed_data */
    $typed_data = $this->container->get('typed_data_manager')->create($definition, $value, 'ip_address');

    $this->assertInstanceOf(TypedDataInterface::class, $typed_data, 'Typed data object is an instance of the typed data interface.');
    $this->assertInstanceOf(StringInterface::class, $typed_data, 'Typed data object is an instance of StringInterface).');

    $this->assertSame($value, $typed_data->getValue(), 'IP address value was fetched.');
    $this->assertCount(1, $typed_data->validate());
    $new_value = '127.0.0.1';
    $typed_data->setValue($new_value);
    $this->assertSame($new_value, $typed_data->getValue(), 'IP address value was changed.');
    $this->assertSame($new_value, $typed_data->getIpv4Address(), 'IP address is a valid IPv4 address.');
    $this->assertNull($typed_data->getIpv6Address());
    $this->assertCount(0, $typed_data->validate());

    $new_value = '::1';
    $typed_data->setValue($new_value);
    $this->assertSame($new_value, $typed_data->getIpv6Address(), 'IP address is a valid IPv6 address.');
    $this->assertNull($typed_data->getIpv4Address());
    $this->assertCount(0, $typed_data->validate());
  }

}
