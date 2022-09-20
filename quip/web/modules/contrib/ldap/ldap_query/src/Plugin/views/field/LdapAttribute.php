<?php

declare(strict_types = 1);

namespace Drupal\ldap_query\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * The handler for loading a specific LDAP field.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("ldap_attribute")
 */
class LdapAttribute extends FieldPluginBase {

  /**
   * Renders the content.
   *
   * @param \Drupal\views\ResultRow $values
   *   The result row.
   *
   * @return array
   *   The processed result in a render array.
   */
  public function render(ResultRow $values): array {
    $output = '';
    if ($value = $this->getValue($values)) {
      switch ($this->options['multi_value']) {
        case 'v-all':
          $output = implode($this->options['value_separator'], $value);
          break;

        case 'v-count':
          $output = count($value);
          break;

        case 'v-index':
          if ($this->options['index_value'] >= 0) {
            $index = (int) $this->options['index_value'];
          }
          else {
            // Allows for negative offset.
            $index = count($value) + $this->options['index_value'];
          }
          $output = \array_key_exists($index, $value) ? $value[$index] : $value[0];
          break;
      }
    }
    return ['#plain_text' => $output];
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['multi_value'] = ['default' => 'v-all'];
    $options['value_separator'] = ['default' => ''];
    $options['index_value'] = ['default' => 0];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    $form['multi_value'] = [
      '#type' => 'select',
      '#title' => $this->t('Values to show'),
      '#description' => $this->t('What to do with multi-value attributes'),
      '#options' => [
        'v-all' => $this->t('All values'),
        'v-index' => $this->t('Show Nth value'),
        'v-count' => $this->t('Count values'),
      ],
      '#default_value' => $this->options['multi_value'],
      '#required' => TRUE,
    ];
    $form['value_separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value separator'),
      '#description' => $this->t('Separator to use between values in multivalued attributes'),
      '#default_value' => $this->options['value_separator'],
      '#states' => [
        'visible' => [
          [
            ':input[name="options[multi_value]"]' => ['value' => 'v-all'],
          ],
        ],
      ],
    ];
    $form['index_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Index'),
      '#description' => $this->t('Index of the value to show. Use negative numbers to index from last item (0=First, -1=Last)'),
      '#default_value' => $this->options['index_value'],
      '#states' => [
        'visible' => [
          [
            ':input[name="options[multi_value]"]' => ['value' => 'v-index'],
          ],
        ],
      ],
    ];
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function clickSort($order): void {
    $params = $this->options['group_type'] !== 'group' ? ['function' => $this->options['group_type']] : [];
    $this->query->addOrderBy(NULL, $this->realField, $order, $this->field_alias, $params);
  }

}
