<?php

namespace Drupal\Tests\rules\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Base class for Rules Drupal unit tests.
 */
abstract class RulesKernelTestBase extends KernelTestBase {

  /**
   * The expression plugin manager.
   *
   * @var \Drupal\rules\Engine\ExpressionManager
   */
  protected $expressionManager;

  /**
   * The condition plugin manager.
   *
   * @var \Drupal\Core\Condition\ConditionManager
   */
  protected $conditionManager;

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManager
   */
  protected $typedDataManager;

  /**
   * Rules logger.
   *
   * @var \Drupal\rules\Logger\RulesLoggerChannel
   */
  protected $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The datetime.time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'rules',
    'rules_test',
    'system',
    'typed_data',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->logger = $this->container->get('logger.channel.rules');
    // Clear the log from any stale entries that are bleeding over from previous
    // tests.
    $this->logger->clearLogs();

    $this->expressionManager = $this->container->get('plugin.manager.rules_expression');
    $this->conditionManager = $this->container->get('plugin.manager.condition');
    $this->typedDataManager = $this->container->get('typed_data_manager');
    $this->messenger = $this->container->get('messenger');
    $this->time = $this->container->get('datetime.time');
  }

  /**
   * Creates a new condition.
   *
   * @param string $id
   *   The condition plugin id.
   *
   * @return \Drupal\rules\Core\RulesConditionInterface
   *   The created condition plugin.
   */
  protected function createCondition($id) {
    $condition = $this->expressionManager->createInstance('rules_condition', [
      'condition_id' => $id,
    ]);
    return $condition;
  }

  /**
   * Checks if particular message is in the log with given delta.
   *
   * @param string $message
   *   Log message.
   * @param int $log_item_index
   *   Log item's index in log entries stack.
   */
  protected function assertRulesLogEntryExists($message, $log_item_index = 0) {
    // Test that the action has logged something.
    $logs = $this->logger->getLogs();
    $this->assertEquals($logs[$log_item_index]['message'], $message);
  }

}
