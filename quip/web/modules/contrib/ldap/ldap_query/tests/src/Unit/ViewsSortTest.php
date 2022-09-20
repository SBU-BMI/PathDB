<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_query\Unit;

use Drupal\ldap_query\Plugin\views\query\LdapQuery;
use Drupal\Tests\UnitTestCase;

/**
 * Test multidimensional sorting.
 *
 * @group ldap_query
 */
class ViewsSortTest extends UnitTestCase {

  /**
   * View.
   *
   * @var \Drupal\ldap_query\Plugin\views\query\LdapQuery
   */
  protected $view;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->view = $this->getMockBuilder(LdapQuery::class)
      ->disableOriginalConstructor()
      ->setMethodsExcept(['addOrderBy', 'sortResults'])
      ->getMock();
  }

  /**
   * Tests something.
   */
  public function testOrder(): void {
    $this->view->addOrderBy(NULL, 'sn', 'asc', 'sn');
    $this->view->addOrderBy(NULL, 'uid', 'desc', 'uid');
    $rows = [
      [
        'cn' => ['aaaaaaa'],
        'sn' => ['Granger'],
        'uid' => ['12345'],
      ],
      [
        'cn' => ['hgranger'],
        'sn' => ['Granger'],
        'uid' => ['12346'],
      ],
      [
        'cn' => ['adumbledore'],
        'sn' => ['Dumbledore'],
        'uid' => ['92345'],
      ],
    ];
    $sorted = [
      [
        'cn' => ['adumbledore'],
        'sn' => ['Dumbledore'],
        'uid' => ['92345'],
        'sort_sn' => 'Dumbledore',
        'sort_uid' => '92345',
      ],
      [
        'cn' => ['hgranger'],
        'sn' => ['Granger'],
        'uid' => ['12346'],
        'sort_sn' => 'Granger',
        'sort_uid' => '12346',
      ],
      [
        'cn' => ['aaaaaaa'],
        'sn' => ['Granger'],
        'uid' => ['12345'],
        'sort_uid' => '12345',
        'sort_sn' => 'Granger',
      ],
    ];
    $output = $this->view->sortResults($rows);
    self::assertEquals($sorted, $output);
  }

}
