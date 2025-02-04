<?php

namespace Drupal\typed_data\Plugin\TypedDataFormWidget;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\typed_data\Attribute\TypedDataFormWidget;
use Drupal\typed_data\Form\SubformState;

/**
 * Plugin implementation of the 'datetime' widget.
 *
 * @TypedDataFormWidget(
 *   id = "datetime",
 *   label = @Translation("Datetime"),
 *   description = @Translation("A datetime input widget."),
 * )
 */
#[TypedDataFormWidget(
  id: "datetime",
  label: new TranslatableMarkup("Datetime"),
  description: new TranslatableMarkup("A datetime input widget.")
)]
class DatetimeWidget extends DatetimeWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function form(TypedDataInterface $data, SubformStateInterface $form_state): array {

    $now = $this->dateTime->getRequestTime();
    $params = [
      '%timezone' => $this->dateFormatter->format($now, 'custom', 'T (e) \U\T\CP'),
      '%daylight_saving' => $this->dateFormatter->format($now, 'custom', 'I') ? $this->t('currently in daylight saving mode') : $this->t('not in daylight saving mode'),
    ];
    $timezone_info = $this->t('Timezone : %timezone %daylight_saving.', $params);

    $form = SubformState::getNewSubForm();
    $form['value'] = [
      '#type' => 'datetime',
      '#title' => $this->configuration['label'] ?: $data->getDataDefinition()->getLabel(),
      '#description' => ($this->configuration['description'] ?: $data->getDataDefinition()->getDescription()) . '<br>' . $timezone_info,
      '#default_value' => $this->createDefaultDateTime($data->getValue()),
      '#required' => $data->getDataDefinition()->isRequired(),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(TypedDataInterface $data, SubformStateInterface $form_state): void {
    $value = $form_state->getValue('value');
    if ($value instanceof DrupalDateTime) {
      $format = DateFormat::load('html_datetime')->getPattern();
      $value = $value->format($format);
    }
    $data->setValue($value);
  }

}
