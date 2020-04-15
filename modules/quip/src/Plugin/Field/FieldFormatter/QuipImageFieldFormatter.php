<?php

namespace Drupal\quip\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;

/**
 * Plugin implementation of the 'QuipImage_field_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "QuipSeaDragon_field_formatter",
 *   label = @Translation("Quip Image field formatter"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class QuipImageFieldFormatter extends FileFormatterBase {

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
    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
	$url = "http://www.ebremer.com";
	$height = 10000;
	$elements[$delta] = [
       		'#theme' => 'openseadragon_link_formatter',
       		'#url' => $url,
       		'#height' => $height,
      	];
    }
    return $elements;
  }
}

