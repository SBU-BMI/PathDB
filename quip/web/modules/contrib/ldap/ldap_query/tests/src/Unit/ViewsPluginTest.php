<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_query\Unit;

use Drupal\ldap_query\Plugin\views\argument\LdapAttribute as LdapAttributeArgument;
use Drupal\ldap_query\Plugin\views\field\LdapAttribute;
use Drupal\ldap_query\Plugin\views\field\LdapVariableImageAttribute;
use Drupal\ldap_query\Plugin\views\filter\LdapAttribute as LdapAttributeFilter;
use Drupal\ldap_query\Plugin\views\query\LdapQuery;
use Drupal\Tests\UnitTestCase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Unit coverage for LDAP Views plugins.
 */
#[Group('ldap')]
class ViewsPluginTest extends UnitTestCase {

  /**
   * Field render modes for multi-value attributes.
   */
  public function testAttributeFieldRender(): void {
    $field = $this->getMockBuilder(LdapAttribute::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getValue'])
      ->getMock();
    $field->options = [
      'multi_value' => 'v-all',
      'value_separator' => '|',
      'index_value' => 0,
    ];
    $row = new ResultRow();
    $field->method('getValue')->willReturn(['a', 'b']);
    self::assertEquals(['#plain_text' => 'a|b'], $field->render($row));

    $field->options['multi_value'] = 'v-count';
    self::assertEquals(['#plain_text' => 2], $field->render($row));

    $field->options['multi_value'] = 'v-index';
    $field->options['index_value'] = 1;
    self::assertEquals(['#plain_text' => 'b'], $field->render($row));

    $field->options['index_value'] = -1;
    self::assertEquals(['#plain_text' => 'b'], $field->render($row));
  }

  /**
   * Image field encodes binary data.
   */
  public function testVariableImageRender(): void {
    $field = $this->getMockBuilder(LdapVariableImageAttribute::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getValue'])
      ->getMock();
    $row = new ResultRow();
    $field->method('getValue')->willReturn(['raw-bytes']);
    $result = $field->render($row);
    self::assertArrayHasKey('#markup', $result);
    self::assertStringContainsString('data:image/jpeg;base64', (string) $result['#markup']);
  }

  /**
   * Filter operators map to LDAP-style where clauses.
   */
  public function testFilterOperators(): void {
    $query = $this->getMockBuilder(LdapQuery::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMock();
    $filter = $this->getMockBuilder(LdapAttributeFilter::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMock();
    $filter->query = $query;
    $filter->options = ['group' => 0];
    $filter->realField = 'cn';
    $filter->value = 'hpotter';
    $filter->operator = '=';

    self::assertEquals('=', $filter->operator());
    $filter->operator = '!=';
    self::assertEquals('!=', $filter->operator());
    $filter->opEqual('cn');
    self::assertNotEmpty($query->where);
  }

  /**
   * Argument plugins add equality filters.
   */
  public function testArgumentQuery(): void {
    $query = $this->getMockBuilder(LdapQuery::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['ensureTable'])
      ->getMock();
    $argument = $this->getMockBuilder(LdapAttributeArgument::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['ensureMyTable'])
      ->getMock();
    $argument->query = $query;
    $argument->realField = 'cn';
    $argument->argument = 'hpotter';
    $argument->query();
    self::assertNotEmpty($query->where);
  }

  /**
   * Missing query id logs an error.
   */
  public function testExecuteWithoutQueryId(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())->method('error');
    $plugin = $this->getMockBuilder(LdapQuery::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMock();
    $ref = new \ReflectionProperty(LdapQuery::class, 'logger');
    $ref->setAccessible(TRUE);
    $ref->setValue($plugin, $logger);
    $plugin->options = ['query_id' => NULL];
    $view = $this->createMock(ViewExecutable::class);
    self::assertFalse($plugin->execute($view));
  }

  /**
   * Where groups compile into LDAP filters.
   */
  public function testBuildConditions(): void {
    $plugin = $this->getMockBuilder(LdapQuery::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMock();
    $plugin->addWhere(0, 'cn', 'hpotter', '=');
    $plugin->addWhere(0, 'mail', '*@example.com', '!=');
    $conditions = $plugin->buildConditions();
    self::assertStringContainsString('cn=hpotter', $conditions);
    self::assertStringContainsString('(!(mail=*@example.com))', $conditions);
  }

  /**
   * Options form lists query ids.
   */
  public function testEnsureTableAndAddField(): void {
    $plugin = $this->getMockBuilder(LdapQuery::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMock();
    self::assertEquals('', $plugin->ensureTable('ldap'));
    self::assertEquals('cn', $plugin->addField('ldap', 'cn'));
    $plugin->addOrderBy(NULL, 'sn', 'ASC');
    self::assertCount(1, $plugin->orderby);
  }

}
