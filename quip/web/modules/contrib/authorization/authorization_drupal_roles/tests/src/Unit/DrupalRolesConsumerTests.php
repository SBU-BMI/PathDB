<?php

namespace Drupal\Tests\authorization_drupal_roles\Unit;

use Drupal\authorization_drupal_roles\Plugin\authorization\Consumer\DrupalRolesConsumer;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\authorization_drupal_roles\Plugin\authorization\Consumer\DrupalRolesConsumer
 * @group authorization
 */
class DrupalRolesConsumerTests extends UnitTestCase {

  /**
   * Consumer plugin.
   *
   * @var \Drupal\authorization_drupal_roles\Plugin\authorization\Consumer\DrupalRolesConsumer
   */
  protected $consumerPlugin;

  /**
   * Setup.
   */
  public function setUp() {
    $this->consumerPlugin = $this->getMockBuilder(DrupalRolesConsumer::class)
      ->disableOriginalConstructor()
      ->setMethods(NULL)
      ->getMock();
  }

  /**
   * Test filterProposals().
   */
  public function testFilterProposals() {

    $proposals = [
      'student' => 'student',
      'user' => 'user',
    ];

    // Wildcard (getWildcard() also covered with this).
    $consumerMapping = [
      'role' => 'source',
    ];
    $result = $this->consumerPlugin->filterProposals($proposals, $consumerMapping);
    $this->assertArrayEquals($proposals, $result);

    // Match for single proposal.
    $consumerMapping = [
      'role' => 'staff',
    ];
    $result = $this->consumerPlugin->filterProposals($proposals, $consumerMapping);
    $this->assertArrayEquals(['staff' => 'staff'], $result);

    // Invalid role.
    $consumerMapping = [
      'role' => 'none',
    ];
    $result = $this->consumerPlugin->filterProposals($proposals, $consumerMapping);
    $this->assertArrayEquals([], $result);

    // No proposals.
    $proposals = [];
    $consumerMapping = [
      'role' => 'student',
    ];
    $result = $this->consumerPlugin->filterProposals($proposals, $consumerMapping);
    $this->assertArrayEquals([], $result);
  }

}
