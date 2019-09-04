<?php

namespace Drupal\ldap_servers\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form for disabling a server.
 */
class ServerEnableDisableForm extends EntityConfirmFormBase {

  /**
   * The server entity.
   *
   * @var \Drupal\ldap_servers\Entity\Server
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ldap_servers_enable_disable_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to disable/enable entity %name?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   *
   * If the delete command is canceled, return to the overview page.
   */
  public function getCancelUrl() {
    return new Url('entity.ldap_server.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    if ($this->entity->get('status') == 1) {
      return $this->t('Disable');
    }
    else {
      return $this->t('Enable');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->set('status', !$this->entity->get('status'));
    $this->entity->save();

    $tokens = [
      '%name' => $this->entity->label(),
      '%sid' => $this->entity->id(),
    ];
    if ($this->entity->get('status') == 1) {
      drupal_set_message(t('LDAP server configuration %name (server id = %sid) has been enabled', $tokens));
      \Drupal::logger('ldap_servers')
        ->notice('LDAP server enabled: %name (sid = %sid) ', $tokens);
    }
    else {
      drupal_set_message(t('LDAP server configuration %name (server id = %sid) has been disabled', $tokens));
      \Drupal::logger('ldap_servers')
        ->notice('LDAP server disabled: %name (sid = %sid) ', $tokens);
    }

    $form_state->setRedirect('entity.ldap_server.collection');
  }

}
