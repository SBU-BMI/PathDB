<?php

namespace Drupal\ldap_query\Plugin\views;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ldap_query\Controller\QueryController;

/**
 * Collates the variable attribute customization to apply it to more than one.
 */
trait VariableAttributeCustomization {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['attribute_name'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $queryOptions = $this->view->getDisplay()->getOption('query')['options'];

    if (!isset($queryOptions['query_id']) || empty($queryOptions['query_id'])) {
      $form['attribute_name'] = [
        '#markup' => 'You must select a valid LDAP search (Advanced => Query settings)',
      ];
      return;
    }
    $controller = new QueryController($queryOptions['query_id']);
    $controller->execute();
    $options = $controller->availableFields();

    $form['attribute_name'] = [
      '#type' => 'select',
      '#title' => t('Attribute name'),
      '#description' => t('The attribute name from LDAP response'),
      '#options' => $options,
      '#default_value' => $this->options['attribute_name'],
      '#required' => TRUE,
    ];
  }

  /**
   * Called to add the field to a query.
   */
  public function query() {
    $this->realField = $this->options['attribute_name'];
    parent::query();
  }

}
