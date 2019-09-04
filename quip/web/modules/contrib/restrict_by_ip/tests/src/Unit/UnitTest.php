<?php
namespace Drupal\Tests\restrict_by_ip\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\restrict_by_ip\IPTools;
use Drupal\restrict_by_ip\Exception\InvalidIPException;

/**
 * Test the restrict by ip module.
 *
 * @group restrict_by_ip
 */
class UnitTest extends UnitTestCase {

  public function setUp() {
    parent::setUp();
  }

  public function testIpFailValidation() {

    $invalid_ips = [
      'string' => 'Not an IP address',
      '127.0.0.1' => 'Missing CIDR mask',
      '127.0.1' => 'Not enough octets',
      '127.0.0.1/8' => 'Invalid /8',
      '127.0.0.1/16' => 'Invalid /16',
      '127.0.0.1/24' => 'Invalid /24',
      'not.an.ip.address/8' => 'Invalid octets',
      '192.168.256.1/32' => 'Out of range octet',
      '192.168.-1.1/32' => 'Out of range octet',
      '127.0.0.1/octet' => 'Invalid CIDR mask',
      '127.0.0.1/33' => 'Out of range CIDR mask',
      '127.0.0.1/-1' => 'Out of range CIDR mask',
    ];

    foreach ($invalid_ips as $ip => $message) {
      try {
        IPTools::validateIP($ip);
      }
      catch (InvalidIPException $e) {
        // We wanted an exception, continue to next IP.
        continue;
      }

      // No exception means an IP passed validation that shouldn't.
      $this->fail($message);
    }
  }

  public function testIpPassValidation() {
    $valid_ips = [
      '127.0.0.0/8' => 'Valid /8',
      '127.1.0.0/16' => 'Valid /16',
      '127.1.1.0/24' => 'Valid /24',
      '127.0.0.1/32' => 'Valid /32',
    ];

    foreach ($valid_ips as $ip => $message) {
      IPTools::validateIP($ip);
    }
  }
}
