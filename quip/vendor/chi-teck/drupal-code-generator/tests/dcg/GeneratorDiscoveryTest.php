<?php

namespace DrupalCodeGenerator\Tests;

use DrupalCodeGenerator\ApplicationFactory;
use DrupalCodeGenerator\GeneratorDiscovery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Test for GeneratorsDiscovery.
 */
class GeneratorDiscoveryTest extends TestCase {

  const TOTAL_GENERATORS = 15;

  /**
   * Test callback.
   */
  public function testExecute() {
    $discovery = new GeneratorDiscovery(new Filesystem());
    $generators = $discovery->getGenerators(
      [ApplicationFactory::getRoot() . '/src/Command/Drupal_7'],
      '\DrupalCodeGenerator\Command\Drupal_7'
    );
    foreach ($generators as $generator) {
      static::assertInstanceOf('DrupalCodeGenerator\Command\BaseGenerator', $generator);
    }
    static::assertCount(self::TOTAL_GENERATORS, $generators);
  }

}
