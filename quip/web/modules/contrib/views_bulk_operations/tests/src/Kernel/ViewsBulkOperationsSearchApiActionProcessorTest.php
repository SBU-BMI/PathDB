<?php

declare(strict_types=1);

namespace Drupal\Tests\views_bulk_operations\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\search_api\Entity\Index;
use Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Action processor test on a Search API backed view.
 *
 * Regression test for a Search API view being processed by VBO: the action
 * processor must not assume the built view query is a database SelectInterface,
 * as Search API views produce a search query object instead.
 *
 * @see \Drupal\search_api\Contrib\ViewsBulkOperationsEventSubscriber
 */
#[CoversClass(ViewsBulkOperationsActionProcessor::class)]
#[Group('views_bulk_operations')]
final class ViewsBulkOperationsSearchApiActionProcessorTest extends ViewsBulkOperationsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api',
    'search_api_db',
    'search_api_test_node_indexing',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');
    $this->installConfig([
      'search_api',
      'search_api_test_node_indexing',
    ]);

    // Install the Search API backed VBO test view from config.
    $config_dir = \dirname(__DIR__, 2) . '/config/views';
    $config_id = 'views.view.views_bulk_operations_search_api_test';
    $this->container->get('config.storage')->write(
      $config_id,
      Yaml::decode(\file_get_contents("$config_dir/$config_id.yml")),
    );

    // Create test nodes and index them into the Search API database backend.
    $this->createTestNodes([
      'page' => [
        'count' => 10,
      ],
    ]);
    Index::load('test_node_index')->indexItems();
  }

  /**
   * Tests VBO action execution on a Search API view.
   */
  public function testViewsBulkOperationsSearchApiActionProcessor(): void {
    $vbo_data = [
      'view_id' => 'views_bulk_operations_search_api_test',
      'action_id' => 'views_bulk_operations_simple_test_action',
    ];

    // Without a selection the action is executed on every indexed node.
    $results = $this->executeAction($vbo_data);
    self::assertCount(1, $results['operations']);
    self::assertEquals('Test', $results['operations'][0]['message']);
    self::assertEquals(10, $results['operations'][0]['count']);

    // With a partial selection only the selected rows are processed, verifying
    // the base field condition narrows the Search API query correctly.
    $selection = [0, 2, 4, 6, 8];
    $vbo_data['list'] = $this->getResultsList($vbo_data, $selection);
    $results = $this->executeAction($vbo_data);
    self::assertCount(1, $results['operations']);
    self::assertEquals(\count($selection), $results['operations'][0]['count']);
  }

}
