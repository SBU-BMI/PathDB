<?php

namespace DrupalCodeGenerator\Tests;

use PHPUnit\Framework\TestCase;

/**
 * A test for Twig environment.
 */
class TwigEnvironmentTest extends TestCase {

  /**
   * Test callback.
   */
  public function testTwigEnvironment() {
    $twig_loader = new \Twig_Loader_Filesystem(__DIR__);
    $twig = \dcg_get_twig_environment($twig_loader);
    $expected = file_get_contents(__DIR__ . '/_twig_environment_fixture.txt');
    $result = $twig->render('twig-environment-template.twig', []);
    static::assertEquals($expected, $result);
  }

}
