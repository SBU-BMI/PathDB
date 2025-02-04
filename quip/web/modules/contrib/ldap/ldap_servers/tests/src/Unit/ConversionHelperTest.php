<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_servers\Unit;

use Drupal\ldap_servers\Helper\ConversionHelper;
use Drupal\Tests\UnitTestCase;

/**
 * Conversion helper tests.
 *
 * @group ldap
 */
class ConversionHelperTest extends UnitTestCase {

  /**
   * Test the unescape mechanism.
   */
  public function testUnescape(): void {
    $input  = 'Secretaria de Tecnologia da Informa\C3\A7\C3\A3o';
    $output = ConversionHelper::unescapeDnValue($input);
    $this->assertEquals('Secretaria de Tecnologia da Informação', $output);
  }

  /**
   * Test the findTokensNeededForTemplate method.
   */
  public function testFindTokensNeededForTemplate(): void {
    $input  = 'cn=John Doe,ou=People,dc=example,dc=com';
    $output = ConversionHelper::findTokensNeededForTemplate($input);
    $this->assertEquals([], $output);
  }

  /**
   * Test the convertAttribute method using MD5.
   */
  public function testConvertAttributeMd5(): void {
    $input  = 'cn=John Doe,ou=People,dc=example,dc=com';
    $output = ConversionHelper::convertAttribute($input, 'md5');
    $this->assertEquals('{md5}1ws3xPfDknrhpKjR8DZZLg==', $output);
  }

}
