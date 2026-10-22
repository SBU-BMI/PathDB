<?php

namespace Drupal\markdown\Plugin\Markdown;

/**
 * PHPStan compatibility declarations for the optional Markdown module.
 *
 * These symbols are supplied by drupal/markdown when that optional integration
 * is installed. Keeping them as analysis-only stubs lets Bootstrap be analysed
 * without forcing the optional module into runtime dependencies.
 */
interface ParserInterface {}

interface AllowedHtmlInterface {

  /**
   * Returns allowed HTML tags and attributes.
   *
   * @return array<mixed>
   *   The allowed HTML tags and attributes.
   */
  public function allowedHtmlTags(ParserInterface $parser, mixed $activeTheme = NULL): array;

}
