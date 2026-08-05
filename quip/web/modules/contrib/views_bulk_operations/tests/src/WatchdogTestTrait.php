<?php

declare(strict_types=1);

namespace Drupal\Tests\views_bulk_operations;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Render\Markup;

/**
 * Fails tests that logged PHP errors, warnings, notices or deprecations.
 *
 * Requires the dblog module to be installed.
 */
trait WatchdogTestTrait {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->watchdogTest();
    parent::tearDown();
  }

  /**
   * Watchdog cleanness test.
   *
   * No severity cutoff: deprecations are logged with DEBUG severity and
   * every "php" type entry is an error handler entry.
   */
  private function watchdogTest(): void {
    $query = $this->container->get('database')->select('watchdog', 'w');
    $results = $query
      ->condition('type', 'php')
      ->fields('w', [
        'type',
        'message',
        'variables',
        'severity',
        'location',
      ])
      ->execute()
      // @todo Replace with FetchAs::Associative when Drupal 10 support ends.
      ->fetchAll(\PDO::FETCH_ASSOC);

    $messages = [];
    foreach ($results as $result) {
      $variables = \unserialize($result['variables'], ['allowed_classes' => [Markup::class]]);
      $message = new FormattableMarkup($result['message'], $variables);
      $messages[] = \sprintf('Severity: %d, type: %s, location: %s, message: %s',
        $result['severity'],
        $result['type'],
        $result['location'],
        \strip_tags((string) $message)
      );
    }

    self::assertEmpty($messages, \implode(\PHP_EOL, $messages));
  }

}
