<?php

namespace Drupal\http_response_headers\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the Response Header add and edit forms.
 */
class ResponseHeaderForm extends EntityForm {

  /**
   * Constructs an ResponseHeaderForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager;
   *   The entity query.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\http_response_headers\Entity\ResponseHeader $response_header */
    $response_header = $this->entity;

    $form['label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $response_header->label(),
      '#description' => $this->t("Label for the Response Header."),
      '#required' => TRUE,
    );
    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $response_header->id(),
      '#machine_name' => array(
        'exists' => array($this, 'exist'),
      ),
      '#disabled' => !$response_header->isNew(),
    );
    $form['description'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#maxlength' => 255,
      '#default_value' => $response_header->get('description'),
      '#description' => $this->t("Description for the Response Header."),
      '#required' => FALSE,
    );

    $form['group'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Header Group'),
      '#maxlength' => 255,
      '#default_value' => $response_header->get('group'),
      '#description' => $this->t("Group for the Response Header."),
      '#required' => FALSE,
    );
    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Header name'),
      '#maxlength' => 255,
      '#default_value' => $response_header->get('name'),
      '#description' => $this->t("The name for the Response Header."),
      '#required' => TRUE,
    );
    $form['value'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Header value'),
      '#maxlength' => 255,
      '#default_value' => $response_header->get('value'),
      '#description' => $this->t("The value for the Response Header."),
      '#required' => FALSE,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $response_header = $this->entity;
    $status = $response_header->save();

    if ($status) {
      $this->messenger()->addMessage($this->t('Saved the %label Response Header.', array(
        '%label' => $response_header->label(),
      )));
    }
    else {
      $this->messenger()->addMessage($this->t('The %label Response Header was not saved.', array(
        '%label' => $response_header->label(),
      )));
    }

    $form_state->setRedirect('entity.response_header.collection');
  }

  /**
   * Helper function to check whether an Response Header configuration entity exists.
   */
  public function exist($id) {
    $entity = $this->entityTypeManager->getStorage('response_header')->getQuery()
      ->accessCheck(FALSE)
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

}
