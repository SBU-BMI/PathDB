<?php

namespace Drupal\rules\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Logs rules log entries in the available loggers.
 */
class RulesLoggerChannel extends LoggerChannel {

  /**
   * A configuration object with rules settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Static storage of log entries.
   *
   * @var array
   */
  protected $logs = [];

  /**
   * Creates RulesLoggerChannel object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory instance.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, MessengerInterface $messenger) {
    parent::__construct('rules');
    $this->config = $config_factory->get('rules.settings');
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []) {
    $this->logs[] = [
      'level' => $level,
      'message' => $message,
      'context' => $context,
    ];

    // Log message only if rules logging setting is enabled.
    if ($this->config->get('log')) {
      if ($this->levelTranslation[$this->config->get('log_level_system')] >= $this->levelTranslation[$level]) {
        parent::log($level, $message, $context);
      }
    }
    if ($this->config->get('debug_screen')) {
      if ($this->levelTranslation[$this->config->get('log_level_screen')] >= $this->levelTranslation[$level]) {
        $this->messenger->addMessage($message, $level);
      }
    }
  }

  /**
   * Returns the structured array of entries.
   *
   * @return array
   *   Array of stored log entries.
   */
  public function getLogs() {
    return $this->logs;
  }

  /**
   * Clears the static logs entries cache.
   */
  public function clearLogs() {
    $this->logs = [];
  }

}
