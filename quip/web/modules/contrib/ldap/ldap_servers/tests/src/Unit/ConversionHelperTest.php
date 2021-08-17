<?php

declare(strict_types = 1);

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
    self::assertEquals('Secretaria de Tecnologia da Informação', $output);
  }

}
