<?php

declare(strict_types=1);

namespace Drupal\ldap_servers\Controller;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function is_array;

/**
 * Debug report.
 *
 * @package Drupal\ldap_servers\Controller
 */
class DebugController extends ControllerBase {

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   */
  final public function __construct(
    ConfigFactoryInterface $config_factory,
    ModuleHandlerInterface $module_handler,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->config = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): DebugController {
    return new self(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Debug report.
   *
   * @return array
   *   Render array.
   */
  public function report(): array {
    $build = [];
    $build['#title'] = $this->t('LDAP Debug Report');
    $build[]['#markup'] = $this->ldap();
    $build[]['#markup'] = $this->debug();
    $build[]['#markup'] = $this->user();
    $build[]['#markup'] = $this->servers();
    $build[]['#markup'] = $this->authorization();
    $build[]['#markup'] = $this->query();

    return $build;
  }

  /**
   * Returns a list of ldap_servers.
   *
   * @return string
   *   yaml.
   */
  private function servers(): string {
    $output = '<h2>' . $this->t('Drupal LDAP servers') . '</h2>';
    $storage = $this->entityTypeManager->getStorage('ldap_server');
    foreach ($storage->loadMultiple() as $sid => $server) {
      $ldap_server = $this->config->getEditable('ldap_servers.server.' . $sid);
      if (!is_null($ldap_server->get('binddn'))) {
        $ldap_server->set('binddn', '***');
      }

      if (!is_null($ldap_server->get('bindpw'))) {
        $ldap_server->set('bindpw', '***');
      }

      /** @var \Drupal\ldap_servers\Entity\Server $server */
      $output .=
        '<h3>' . $this->t('Server @name:', ['@name' => $server->label()]) . '</h3>' .
        $this->printConfig($ldap_server);
    }

    return $output;
  }

  /**
   * Returns ldap_user setting.
   *
   * @return string
   *   yaml.
   */
  private function user(): string {
    $output = '<h2>' . $this->t('Users') . '</h2>';
    $user_register = $this->config('user.settings')->get('register');
    $output .= $this->t('Currently active Drupal user registration setting: @setting', ['@setting' => $user_register]);

    if ($this->moduleHandler->moduleExists('ldap_user')) {
      $output .=
        '<h3>' . $this->t('LDAP user configuration') . '</h3>' .
        $this->printConfig($this->config('ldap_user.settings'));

    }

    return $output;
  }

  /**
   * Returns ldap_servers setting.
   *
   * @return string
   *   yaml.
   */
  private function debug(): string {
    return '<h3>' . $this->t('LDAP debug configuration') . '</h3>' .
    $this->printConfig($this->config('ldap_servers.settings'));
  }

  /**
   * Returns authorization setting.
   *
   * @return string
   *   yaml.
   */
  private function authorization(): string {
    $output = '';
    if (!$this->moduleHandler->moduleExists('ldap_authorization')) {
      return $output;
    }
    $output .= '<h2>' . $this->t('Configured authorization profiles') . '</h2>';
    $profiles = $this->entityTypeManager
      ->getStorage('authorization_profile')
      ->getQuery()
      ->accessCheck(FALSE)
      ->execute();
    foreach ($profiles as $profile) {
      $output .=
        '<h3>' . $this->t('Profile @name:', ['@name' => $profile]) . '</h3>' .
        $this->printConfig($this->config('authorization.authorization_profile.' . $profile));
    }

    return $output;
  }

  /**
   * Returns ldap_queries.
   *
   * @return string
   *   yaml.
   */
  private function query(): string {
    $output = '';
    if (!$this->moduleHandler->moduleExists('ldap_query')) {
      return $output;
    }
    $output .= '<h2>' . $this->t('Configured LDAP queries') . '</h2>';
    $queries = $this->entityTypeManager
      ->getStorage('ldap_query_entity')
      ->getQuery()
      ->accessCheck(FALSE)
      ->execute();
    foreach ($queries as $query) {
      $output .=
        '<h3>' . $this->t('Query @name:', ['@name' => $query]) . '</h3>' .
        $this->printConfig($this->config('ldap_query.query.' . $query));
    }

    return $output;
  }

  /**
   * Returns ldap extension properties.
   *
   * @return string
   *   yaml.
   */
  private function ldap(): string {
    $output = '';
    if (!extension_loaded('ldap')) {
      $this->messenger()->addError($this->t('PHP LDAP extension not loaded.'));
    }
    else {
      $output .= '<h2>' . $this->t('PHP LDAP module') . '</h2>';
      $output .= '<pre>' . Yaml::encode($this->parsePhpModules()['ldap'] ?? '<em>Missing</em>') . '</pre>';
    }

    return $output;
  }

  /**
   * Returns raw data of configuration.
   *
   * @param \Drupal\Core\Config\Config $config
   *   Configuration name.
   *
   * @return string
   *   Raw configuration data.
   */
  private function printConfig(Config $config): string {
    return '<pre>' . Yaml::encode($config->getRawData()) . '</pre>';
  }

  /**
   * Generates an array of values from phpinfo().
   *
   * @return array
   *   Module list.
   */
  private function parsePhpModules(): array {
    ob_start();
    phpinfo(INFO_MODULES);
    $output = ob_get_clean();
    $output = strip_tags($output, '<h2><th><td>');
    $output = preg_replace('/<th[^>]*>([^<]+)<\/th>/', "<info>\\1</info>", $output);
    $output = preg_replace('/<td[^>]*>([^<]+)<\/td>/', "<info>\\1</info>", $output);
    /** @var string[] $rows */
    $rows = preg_split('/(<h2>[^<]+<\/h2>)/', $output, -1, PREG_SPLIT_DELIM_CAPTURE);
    $modules = [];
    if (is_array($rows)) {
      $rowCount = count($rows);
      // First line with CSS can be ignored.
      for ($i = 1; $i < $rowCount - 1; $i++) {
        $this->extractModule($rows[$i], $rows[$i + 1], $modules);
      }
    }
    return $modules;
  }

  /**
   * Extract module information.
   *
   * @param string $row
   *   Row.
   * @param string $nextRow
   *   Next row.
   * @param array $modules
   *   Extracted modules data.
   */
  private function extractModule(string $row, string $nextRow, array &$modules): void {
    if (preg_match('/<h2>([^<]+)<\/h2>/', $row, $headingMatches)) {
      $moduleName = trim($headingMatches[1]);
      $moduleInfos = explode("\n", $nextRow);
      foreach ($moduleInfos as $info) {
        $infoPattern = '<info>([^<]+)<\/info>';
        // 3 columns.
        if (preg_match("/$infoPattern\s*$infoPattern\s*$infoPattern/", $info, $infoMatches)) {
          $modules[$moduleName][trim($infoMatches[1])] = [
            trim($infoMatches[2]),
            trim($infoMatches[3]),
          ];
        }
        // 2 columns.
        elseif (preg_match("/$infoPattern\s*$infoPattern/", $info, $infoMatches)) {
          $modules[$moduleName][trim($infoMatches[1])] = trim($infoMatches[2]);
        }
      }
    }
  }

}
