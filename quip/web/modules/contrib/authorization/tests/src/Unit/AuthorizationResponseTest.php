<?php

declare(strict_types=1);

namespace Drupal\Tests\authorization\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\authorization\AuthorizationResponse;

/**
 * Tests AuthorizationResponse.
 *
 * @coversDefaultClass \Drupal\authorization\AuthorizationResponse
 *
 * @group authorization
 */
class AuthorizationResponseTest extends UnitTestCase {

  /**
   * Test AuthorizationResponse.
   */
  public function testAuthorizationResponse() {
    $message = 'Test message';
    $skipped = FALSE;
    $authorizationsApplied = ['test' => 'test'];

    $response = new AuthorizationResponse($message, $skipped, $authorizationsApplied);

    $this->assertEquals($message, $response->getMessage());
    $this->assertEquals($skipped, $response->getSkipped());
    $this->assertEquals($authorizationsApplied, $response->getAuthorizationsApplied());
  }

}
