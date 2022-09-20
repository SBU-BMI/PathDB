<?php

declare(strict_types = 1);

namespace Drupal\ldap_query\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBase;
use Drupal\ldap_query\Controller\QueryController;
use Drupal\ldap_servers\Form\ServerTestForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Test form for queries.
 */
class QueryTestForm extends FormBase {

  /**
   * LDAP Query.
   *
   * @var \Drupal\ldap_query\Controller\QueryController
   */
  protected $ldapQuery;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ldap_query_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(QueryController $ldap_query) {
    $this->ldapQuery = $ldap_query;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): QueryTestForm {
    return new static($container->get('ldap.query'));
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $ldap_query_entity = NULL): array {
    if ($ldap_query_entity) {
      $this->ldapQuery->load($ldap_query_entity);
      $this->ldapQuery->execute();
      $data = $this->ldapQuery->getRawResults();

      $form['result_count'] = [
        '#markup' => '<h2>' . $this->t('@count results', ['@count' => count($data)]) . '</h2>',
      ];

      $header[] = 'DN';
      $attributes = $this->ldapQuery->availableFields();
      foreach ($attributes as $attribute) {
        $header[] = $attribute;
      }

      $rows = [];
      foreach ($data as $entry) {
        $row = [$entry->getDn()];
        foreach ($attributes as $attribute_data) {
          if (!$entry->hasAttribute($attribute_data, FALSE)) {
            $row[] = 'No data';
          }
          else {
            if (count($entry->getAttribute($attribute_data, FALSE)) > 1) {
              $row[] = ServerTestForm::binaryCheck(implode("\n", $entry->getAttribute($attribute_data, FALSE)));
            }
            else {
              $row[] = ServerTestForm::binaryCheck($entry->getAttribute($attribute_data, FALSE)[0]);
            }
          }
        }
        $rows[] = $row;
      }

      $form['result'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

  }

}
