<?php

declare(strict_types = 1);

namespace Drupal\ldap_servers\Form;

use Drupal\Core\StringTranslation\TranslatableMarkup;
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
  public function getFormId(): string {
    return 'ldap_servers_enable_disable_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to disable/enable entity %name?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   *
   * If the delete command is canceled, return to the overview page.
   */
  public function getCancelUrl(): Url {
    return new Url('entity.ldap_server.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    if ($this->entity->get('status') == 1) {
      return $this->t('Disable');
    }

    return $this->t('Enable');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->entity->set('status', !$this->entity->get('status'));
    $this->entity->save();

    $tokens = [
      '%name' => $this->entity->label(),
      '%sid' => $this->entity->id(),
    ];
    if ($this->entity->get('status') === 1) {
      $this->messenger()
        ->addMessage($this->t('LDAP server configuration %name (server id = %sid) has been enabled', $tokens));
      \Drupal::logger('ldap_servers')
        ->notice('LDAP server enabled: %name (sid = %sid) ', $tokens);
    }
    else {
      $this->messenger()
        ->addMessage($this->t('LDAP server configuration %name (server id = %sid) has been disabled', $tokens));
      \Drupal::logger('ldap_servers')
        ->notice('LDAP server disabled: %name (sid = %sid) ', $tokens);
    }

    $form_state->setRedirect('entity.ldap_server.collection');
  }

}
