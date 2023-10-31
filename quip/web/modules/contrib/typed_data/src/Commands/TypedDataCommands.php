<?php

namespace Drupal\typed_data\Commands;

use Drush\Commands\DrushCommands;

/**
 * Drush 9+ commands for the Typed Data API Enhancements module.
 */
class TypedDataCommands extends DrushCommands {

  /**
   * Show a list of available entities.
   *
   * @command typed-data:entities
   * @aliases el,entity-list
   */
  public function listEntities(): void {
    // Dependency injection deliberately not used. So ignore the phpcs message.
    // @see https://www.drupal.org/project/typed_data/issues/3164489
    // @phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $entities = array_keys(\Drupal::entityTypeManager()->getDefinitions());
    sort($entities);

    $this->output()->writeln(dt('Entity machine names:'));
    $this->output()->writeln('  ' . implode(PHP_EOL . '  ', $entities) . PHP_EOL);
  }

  /**
   * Show a list of available contexts.
   *
   * @command typed-data:contexts
   * @aliases cl,context-list
   */
  public function listContexts(): void {
    // @phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $contexts = array_keys(\Drupal::service('context.repository')->getAvailableContexts());
    sort($contexts);

    $this->output()->writeln(dt('Global context variables:'));
    $this->output()->writeln('  ' . implode(PHP_EOL . '  ', $contexts) . PHP_EOL);
  }

  /**
   * Show a list of available Typed Data datatypes.
   *
   * @command typed-data:datatypes
   * @aliases tl,datatype-list
   */
  public function listDataTypes(): void {
    // @phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $datatypes = array_keys(\Drupal::service('typed_data_manager')->getDefinitions());
    sort($datatypes);

    $this->output()->writeln(dt('Available TypedData data types:'));
    $this->output()->writeln('  ' . implode(PHP_EOL . '  ', $datatypes) . PHP_EOL);
  }

  /**
   * Show a list of available TypedDataFilter plugins.
   *
   * @command typed-data:datafilters
   * @aliases fl,datafilter-list
   */
  public function listDataFilters(): void {
    $this->formatOutput('plugin.manager.typed_data_filter', 'Available TypedDataFilter plugins:', FALSE);
  }

  /**
   * Show a list of available TypedDataFormWidget plugins.
   *
   * @command typed-data:formwidgets
   * @aliases wl,formwidget-list
   */
  public function listFormWidgets(): void {
    $this->formatOutput('plugin.manager.typed_data_form_widget', 'Available TypedDataFormWidget plugins:', FALSE);
  }

  /**
   * Helper function to format command output.
   */
  protected function formatOutput(string $plugin_manager_service, string $title, bool $categories = TRUE, bool $short = FALSE): void {
    // @phpcs:ignore DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
    $definitions = \Drupal::service($plugin_manager_service)->getDefinitions();
    $plugins = [];
    foreach ($definitions as $plugin) {
      if ($categories) {
        if ($short) {
          $plugins[(string) $plugin['category']][] = $plugin['id'];
        }
        else {
          $plugins[(string) $plugin['category']][] = $plugin['label'] . '   (' . $plugin['id'] . ')';
        }
      }
      else {
        if ($short) {
          $plugins[] = $plugin['id'];
        }
        else {
          $plugins[] = $plugin['label'] . '   (' . $plugin['id'] . ')';
        }
      }
    }

    $this->output()->writeln(dt($title));
    if ($categories) {
      ksort($plugins);
      foreach ($plugins as $category => $plugin_list) {
        $this->output()->writeln('  ' . $category);
        sort($plugin_list);
        $this->output()->writeln('    ' . implode(PHP_EOL . '    ', $plugin_list));
        $this->output()->writeln('');
      }
    }
    else {
      $unique = array_unique($plugins);
      sort($unique);
      $this->output()->writeln('  ' . implode(PHP_EOL . '  ', $unique) . PHP_EOL);
    }
  }

}
