<?php

declare(strict_types = 1);

namespace Drupal\ldap_authentication\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides help messages for users when configured.
 */
class DynamicUserHelpLink extends DeriverBase implements ContainerDeriverInterface {

  /**
   * Config.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   Config Factory.
   */
  public function __construct(ConfigFactory $config_factory) {
    $this->config = $config_factory->get('ldap_authentication.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): DynamicUserHelpLink {
    return new static($container->get('config.factory'));
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($basePluginDefinition): array {
    if ($this->config->get('ldapUserHelpLinkText') &&
      $this->config->get('ldapUserHelpLinkUrl')) {
      $basePluginDefinition['title'] = $this->config->get('ldapUserHelpLinkText');
      $basePluginDefinition['route_name'] = 'ldap_authentication.ldap_help_redirect';
      $this->derivatives['ldap_authentication.show_user_help_link'] = $basePluginDefinition;
    }
    return $this->derivatives;
  }

}
