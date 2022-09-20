<?php

declare(strict_types = 1);

namespace Drupal\ldap_servers\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * The detailed logger for the LDAP modules.
 *
 * This logger is only active when the ldap_servers setting watchdog_detail is
 * active. It passes messages to the regular logger with priority debug.
 */
class LdapDetailLog {

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * LdapDetailLog constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $factory
   *   Logger factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Config factory.
   */
  public function __construct(LoggerChannelFactoryInterface $factory, ConfigFactoryInterface $config) {
    $this->loggerFactory = $factory;
    $this->config = $config->get('ldap_servers.settings');
  }

  /**
   * If detailed logging is enabled, log to Drupal log more details.
   *
   * @param string $message
   *   Log message.
   * @param array $context
   *   Values to replace in log.
   * @param string $module
   *   Logging channel to use.
   */
  public function log(string $message, array $context = [], string $module = 'ldap_servers'): void {
    if ($this->config->get('watchdog_detail')) {
      $this->loggerFactory->get($module)->debug($message, $context);
    }
  }

}
