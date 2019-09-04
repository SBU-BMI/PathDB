<?php

namespace Drupal\ldap_servers\Logger;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * The detailed logger for the LDAP modules.
 *
 * This logger is only active the ldap_help setting watchdog_detail is active.
 * When it is, it passes messages to the regular logger with priority debug.
 */
class LdapDetailLog {

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
    $this->config = $config->get('ldap_help.settings');
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
  public function log($message, array $context = [], $module = 'ldap_servers') {
    if ($this->config->get('watchdog_detail')) {
      $this->loggerFactory->get($module)->debug($message, $context);
    }
  }

}
