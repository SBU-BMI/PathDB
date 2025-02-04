<?php

namespace Drupal\typed_data\Plugin\TypedDataFormWidget;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\typed_data\Attribute\TypedDataFormWidget;
use Drupal\typed_data\Form\SubformState;

/**
 * Plugin implementation of the 'datetime_range' widget.
 *
 * @TypedDataFormWidget(
 *   id = "datetime_range",
 *   label = @Translation("Datetime Range"),
 *   description = @Translation("A datetime range input widget, containing two datetime values, for the start and end of the range."),
 * )
 */
#[TypedDataFormWidget(
  id: "datetime_range",
  label: new TranslatableMarkup("Datetime Range"),
  description: new TranslatableMarkup("A datetime range input widget, containing two datetime values, for the start and end of the range.")
)]
class DatetimeRangeWidget extends DatetimeWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function form(TypedDataInterface $data, SubformStateInterface $form_state): array {
    $value = $data->getValue();

    $form = SubformState::getNewSubForm();
    $form['#theme_wrappers'][] = 'fieldset';

    $form['#title'] = $this->configuration['label'] ?: $data->getDataDefinition()->getLabel();

    $now = $this->dateTime->getRequestTime();
    $params = [
      '%timezone' => $this->dateFormatter->format($now, 'custom', 'T (e) \U\T\CP'),
      '%daylight_saving' => $this->dateFormatter->format($now, 'custom', 'I') ? $this->t('currently in daylight saving mode') : $this->t('not in daylight saving mode'),
    ];
    $timezone_info = $this->t('Timezone : %timezone %daylight_saving.', $params);
    $form['#description'] = ($this->configuration['description'] ?: $data->getDataDefinition()->getDescription()) . '<br>' . $timezone_info;

    $form['#element_validate'][] = [$this, 'validateStartEnd'];

    $form['value'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Start date'),
      '#default_value' => $this->createDefaultDateTime($value['value']),
      '#required' => $data->getDataDefinition()->isRequired(),
    ];
    $form['end_value'] = [
      '#type' => 'datetime',
      '#title' => $this->t('End date'),
      '#default_value' => $this->createDefaultDateTime($value['end_value']),
      '#required' => $data->getDataDefinition()->isRequired(),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(TypedDataInterface $data, SubformStateInterface $form_state): void {
    $value = $form_state->getValue('value');
    $end_value = $form_state->getValue('end_value');
    $format = DateFormat::load('html_datetime')->getPattern();
    if ($value instanceof DrupalDateTime) {
      $value = $value->format($format);
    }
    if ($end_value instanceof DrupalDateTime) {
      $end_value = $end_value->format($format);
    }
    $data->setValue([
      'value' => $value,
      'end_value' => $end_value,
    ]);
  }

  /**
   * Ensure that the start date <= the end date.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public function validateStartEnd(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    $start_date = $element['value']['#value']['object'];
    $end_date = $element['end_value']['#value']['object'];

    if ($start_date instanceof DrupalDateTime && $end_date instanceof DrupalDateTime) {
      if ($start_date->getTimestamp() !== $end_date->getTimestamp()) {
        $interval = $start_date->diff($end_date);
        if ($interval->invert === 1) {
          $form_state->setError($element, $this->t('The @title end date cannot be before the start date', ['@title' => $element['#title']]));
        }
      }
    }
  }

}
