<?php

namespace Drupal\moderated_content_bulk_publish\Plugin\Action;

//use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
//use Drupal\views_bulk_operations\Action\ViewsBulkOperationsPreconfigurationInterface;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\moderated_content_bulk_publish\AdminPin;
use Drupal\Core\Access\AccessResult;

/**
 * An example action covering most of the possible options.
 *
 * If type is left empty, action will be selectable for all
 * entity types.
 *
 * @Action(
 *   id = "unpin_content_action",
 *   label = @Translation("Unpin Content"),
 *   type = "node",
 *   confirm = TRUE,
 * )
 */
//only need to add "implements" keywords below if we are goign to add configuration forms to the confirmation step.... not the case here!
class UnpinContentAction extends ActionBase/*extends ViewsBulkOperationsActionBase implements ViewsBulkOperationsPreconfigurationInterface, PluginFormInterface*/
{
    
    

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    /*
     * All config resides in $this->configuration.
     * Passed view rows will be available in $this->context.
     * Data about the view used to select results and optionally
     * the batch context are available in $this->context or externally
     * through the public getContext() method.
     * The entire ViewExecutable object  with selected result
     * rows is available in $this->view or externally through
     * the public getView() method.
     */

    // Do some processing..
    // ...
    //\Drupal::Messenger()->addStatus(utf8_encode('Pin bulk operation by moderated_content_bulk_publish module'));
    $user = \Drupal::currentUser();

    if ($user->hasPermission('moderated content bulk unpin content')) {
      \Drupal::logger('moderated_content_bulk_publish')->notice("EXECUTING PIN CONTENT FOR ".$entity->label());

      $adminPin = new AdminPin($entity, $entity->isSticky());
      $entity = $adminPin->unpin();

      // Check if unpinned
      if ($entity->isSticky()){
        $msg = "Something went wrong, the entity must be unpinned by this point.";
        \Drupal::Messenger()->addError(utf8_encode($msg));
        \Drupal::logger('moderated_content_bulk_publish')->warning($msg);
        return $msg;
      }

      return sprintf('Example action (configuration: %s)', print_r($this->configuration, TRUE));

    }
    else {
      \Drupal::messenger()->addWarning(t("You don't have access to execute this operation!"));
      return;
    }

  }

  /**
   * {@inheritdoc}
   */
  /*
  public function buildPreConfigurationForm(array $form, array $values, FormStateInterface $form_state) {
    $form['example_preconfig_setting'] = [
      '#title' => $this->t('Example setting'),
      '#type' => 'textfield',
      '#default_value' => isset($values['example_preconfig_setting']) ? $values['example_preconfig_setting'] : '',
    ];
    return $form;
  }
  */

  /**
   * Configuration form builder.
   *
   * If this method has implementation, the action is
   * considered to be configurable.
   *
   * @param array $form
   *   Form array.
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   The configuration form.
   */
  /*
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['example_config_setting'] = [
      '#title' => t('Example setting pre-execute'),
      '#type' => 'textfield',
      '#default_value' => $form_state->getValue('example_config_setting'),
    ];
    return $form;
  }
  */

  /**
   * Submit handler for the action configuration form.
   *
   * If not implemented, the cleaned form values will be
   * passed direclty to the action $configuration parameter.
   *
   * @param array $form
   *   Form array.
   * @param Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  /*
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // This is not required here, when this method is not defined,
    // form values are assigned to the action configuration by default.
    // This function is a must only when user input processing is needed.
    $this->configuration['example_config_setting'] = $form_state->getValue('example_config_setting');
  }
  */

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object->getEntityTypeId() === 'node') {
      $moderation_info = \Drupal::service('content_moderation.moderation_information');
      // Moderated Entities will return AccessResult::forbidden for attemps
      // to edit $object->status.
      // @see content_moderation_entity_field_access
      if ($moderation_info->isModeratedEntity($object)) {
        $access = $object->access('update', $account, TRUE)
          ->andIf($object->moderation_state->access('edit', $account, TRUE));
      }
      else {
        $access = $object->access('update', $account, TRUE)
          ->andIf($object->status->access('edit', $account, TRUE));
      }
    }
    else {
      $access = AccessResult::forbidden()->setReason('The chosen Action only acts on entities of type node')->setCacheMaxAge(0);
    }
    return $return_as_object ? $access : $access->isAllowed();
  }

}
