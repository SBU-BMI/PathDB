<?php

namespace Drupal\css_editor;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for generating CSS files from configuration.
 */
class CssEditorService {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a CssEditorService object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, FileSystemInterface $file_system, LoggerInterface $logger) {
    $this->configFactory = $config_factory;
    $this->fileSystem = $file_system;
    $this->logger = $logger;
  }

  /**
   * Generate CSS file for a theme from its configuration.
   *
   * @param string $theme
   *   The machine name of the theme.
   *
   * @return bool
   *   TRUE if file was generated successfully, FALSE otherwise.
   */
  public function generateCssFile($theme) {
    // Load theme configuration.
    $config = $this->configFactory->get('css_editor.theme.' . $theme);

    // Only generate if CSS editor is enabled for this theme.
    if (!$config->get('enabled')) {
      return FALSE;
    }

    $css = $config->get('css');
    if (empty($css)) {
      return FALSE;
    }

    // Prepare directory and file path.
    $path = 'public://css_editor';
    $file = $path . DIRECTORY_SEPARATOR . $theme . '.css';

    // Ensure directory exists with proper permissions.
    if (!$this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      $this->logger->error('Failed to create directory for CSS editor: @path', ['@path' => $path]);
      return FALSE;
    }

    // Save CSS data to file.
    try {
      $this->fileSystem->saveData($css, $file, FileSystemInterface::EXISTS_REPLACE);
      $this->fileSystem->chmod($file);

      // Update config with file path.
      $this->configFactory->getEditable('css_editor.theme.' . $theme)
        ->set('path', $file)
        ->save();

      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to save CSS file for theme @theme: @error', [
        '@theme' => $theme,
        '@error' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Regenerate CSS files for all themes with custom CSS.
   *
   * @return int
   *   The number of CSS files successfully regenerated.
   */
  public function regenerateAllCssFiles() {
    $count = 0;

    // Get all css_editor theme configurations.
    $config_names = $this->configFactory->listAll('css_editor.theme.');

    foreach ($config_names as $config_name) {
      // Extract theme name from config name (css_editor.theme.{theme}).
      $theme = str_replace('css_editor.theme.', '', $config_name);

      if ($this->generateCssFile($theme)) {
        $count++;
      }
    }

    return $count;
  }

}
