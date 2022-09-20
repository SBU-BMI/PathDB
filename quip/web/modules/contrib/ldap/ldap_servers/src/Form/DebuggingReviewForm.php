<?php

declare(strict_types = 1);

namespace Drupal\ldap_servers\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Serialization\Yaml;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function is_array;

/**
 * Form to allow for debugging review.
 */
class DebuggingReviewForm extends FormBase {

  /**
   * Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Module Handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ldap_servers_debugging_review';
  }

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   Module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ModuleHandler $module_handler,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->config = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): DebuggingReviewForm {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Returns raw data of configuration.
   *
   * @param string $configName
   *   Configuration name.
   *
   * @return string
   *   Raw configuration data.
   */
  private function printConfig(string $configName): string {
    return '<pre>' . Yaml::encode($this->config($configName)->getRawData()) . '</pre>';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['title'] = [
      '#markup' => '<h1>' . $this->t('LDAP Debugging Review') . '</h1>',
    ];

    if (!extension_loaded('ldap')) {
      $this->messenger()->addError($this->t('PHP LDAP extension not loaded.'));
    }
    else {
      $form['heading_modules'] = [
        '#markup' => '<h2>' . $this->t('PHP LDAP module') . '</h2>',
      ];
      $form['modules'] = [
        '#markup' => '<pre>' . Yaml::encode($this->parsePhpModules()['ldap']) . '</pre>',
      ];
    }

    $form['heading_ldap'] = [
      '#markup' => '<h2>' . $this->t('Drupal LDAP modules') . '</h2>',
    ];

    if ($this->moduleHandler->moduleExists('ldap_user')) {
      $form['config_users'] = [
        '#markup' =>
        '<h3>' . $this->t('The LDAP user configuration') . '</h3>' .
        $this->printConfig('ldap_user.settings'),
      ];
    }

    $user_register = $this->config('user.settings')->get('register');
    $form['config_users_registration'] = [
      '#markup' => $this->t('Currently active Drupal user registration setting: @setting', ['@setting' => $user_register]),
    ];

    if ($this->moduleHandler->moduleExists('ldap_authentication')) {
      $form['config_authentication'] = [
        '#markup' =>
        '<h3>' . $this->t('The LDAP authentication configuration') . '</h3>' .
        $this->printConfig('ldap_authentication.settings'),
      ];
    }

    $form['config_help'] = [
      '#markup' =>
      '<h3>' . $this->t('The LDAP help configuration') . '</h3>' .
      $this->printConfig('ldap_servers.settings'),
    ];

    $form['heading_servers'] = [
      '#markup' => '<h2>' . $this->t('Drupal LDAP servers') . '</h2>',
    ];

    $storage = $this->entityTypeManager->getStorage('ldap_server');
    $servers = $storage->getQuery()->execute();
    foreach ($storage->loadMultiple($servers) as $sid => $server) {
      /** @var \Drupal\ldap_servers\Entity\Server $server */
      $form['config_server_' . $sid] = [
        '#markup' =>
        '<h3>' . $this->t('Server @name:', ['@name' => $server->label()]) . '</h3>' .
        $this->printConfig('ldap_servers.server.' . $sid),
      ];
    }

    if ($this->moduleHandler->moduleExists('authorization') &&
      $this->moduleHandler->moduleExists('ldap_authorization')) {
      $form['heading_profiles'] = [
        '#markup' => '<h2>' . $this->t('Configured authorization profiles') . '</h2>',
      ];
      $profiles = $this->entityTypeManager->getStorage('authorization_profile')->getQuery()->execute();
      foreach ($profiles as $profile) {
        $form['authorization_profile_' . $profile] = [
          '#markup' =>
          '<h3>' . $this->t('Profile @name:', ['@name' => $profile]) . '</h3>' .
          $this->printConfig('authorization.authorization_profile.' . $profile),
        ];
      }
    }

    if ($this->moduleHandler->moduleExists('ldap_query')) {
      $form['heading_queries'] = [
        '#markup' => '<h2>' . $this->t('Configured LDAP queries') . '</h2>',
      ];

      $queries_found = $this->entityTypeManager->getStorage('ldap_query_entity')->getQuery()->execute();
      foreach ($this->entityTypeManager->getStorage('ldap_query_entity')->loadMultiple($queries_found) as $query) {
        /** @var \Drupal\ldap_query\Entity\QueryEntity $query */
        $form['query_' . $query->id()] = [
          '#markup' =>
          '<h3>' . $this->t('Query @name:', ['@name' => $query->label()]) . '</h3>' .
          $this->printConfig('ldap_query.ldap_query_entity.' . $query->id()),
        ];
      }
    }

    return $form;
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

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Nothing to submit.
  }

}
