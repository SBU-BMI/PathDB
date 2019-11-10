<?php

namespace Drupal\quip\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;

/**
 * Plugin implementation of the 'QuipSeaDragon_field_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "QuipSeaDragon_field_formatter",
 *   label = @Translation("Quip SeaDragon field formatter"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class QuipSeaDragonFieldFormatter extends FileFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      // Implement default settings.
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return [
      // Implement settings form.
    ] + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    // Implement settings summary.

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof \Drupal\node\NodeInterface) {
      $nid = $node->id();
    } else {
      $nid = 0;
    }
    $height = $node->field_height->value;
    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
      $url = file_url_transform_relative(file_create_url($file->getFileUri()));
      $elements[$delta] = [
        '#theme' => 'openseadragon_link_formatter',
        '#url' => $url,
        '#height' => $height,
      ];
    }
    return $elements;
  }
}

