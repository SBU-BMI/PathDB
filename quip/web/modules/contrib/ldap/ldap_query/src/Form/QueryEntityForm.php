<?php

declare(strict_types = 1);

namespace Drupal\ldap_query\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Query entity form.
 *
 * @package Drupal\ldap_query\Form
 */
class QueryEntityForm extends EntityForm {

  /**
   * Query Entity.
   *
   * @var \Drupal\ldap_query\Entity\QueryEntity
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    $ldap_query_entity = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $ldap_query_entity->label(),
      '#description' => $this->t('Label for the LDAP Queries.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $ldap_query_entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\ldap_query\Entity\QueryEntity::load',
      ],
      '#disabled' => !$ldap_query_entity->isNew(),
    ];

    $storage = $this->entityTypeManager
      ->getStorage('ldap_server');
    $servers = $storage->getQuery()->execute();

    $options = [];
    /** @var \Drupal\ldap_servers\Entity\Server $server */
    foreach ($storage->loadMultiple($servers) as $server) {
      $options[$server->id()] = $server->label();
    }

    $form['server_id'] = [
      '#type' => 'radios',
      '#title' => $this->t('LDAP server used for query'),
      '#default_value' => $ldap_query_entity->get('server_id'),
      '#options' => $options,
      '#required' => TRUE,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Query enabled'),
      '#default_value' => $ldap_query_entity->get('status'),
    ];

    $form['base_dn'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Base DNs to search in query'),
      '#default_value' => $ldap_query_entity->get('base_dn'),
      '#description' => $this->t('Each Base DN will be queried and results merged, e.g. <code>ou=groups,dc=hogwarts,dc=edu</code>. <br>Enter one per line in case if you need more than one.'),
    ];

    $form['filter'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Filter'),
      '#default_value' => $ldap_query_entity->get('filter'),
      '#description' => $this->t('LDAP query filter such as <code>(objectClass=group)</code> or <code>(&(objectClass=user)(homePhone=*))</code>'),
    ];

    $form['attributes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Attributes'),
      '#default_value' => $ldap_query_entity->get('attributes'),
      '#description' => $this->t('Enter as comma separated list.<br> The DN is automatically returned. <br>Leave empty to return all attributes. e.g. <code>objectclass,name,cn,samaccountname</code>'),
    ];

    $limit = $ldap_query_entity->get('size_limit');
    if ($limit === NULL) {
      $limit = 0;
    }
    $form['size_limit'] = [
      '#type' => 'number',
      '#length' => '7',
      '#title' => $this->t('Size limit of returned data'),
      '#default_value' => $limit,
      '#min' => 0,
      '#description' => $this->t('This limit may already be set by the LDAP server. 0 signifies no limit.'),
      '#required' => TRUE,
    ];

    $limit = $ldap_query_entity->get('time_limit');
    if ($limit === NULL) {
      $limit = 0;
    }
    $form['time_limit'] = [
      '#type' => 'number',
      '#length' => '7',
      '#title' => $this->t('Time limit in seconds'),
      '#default_value' => $limit,
      '#min' => 0,
      '#description' => $this->t('This limit may already be set by the LDAP server. 0 signifies no limit.'),
      '#required' => TRUE,
    ];

    $dereference = $ldap_query_entity->get('dereference');
    if (!$dereference) {
      $dereference = \defined('LDAP_DEREF_NEVER') ? LDAP_DEREF_NEVER : 0;
    }

    $form['dereference'] = [
      '#type' => 'radios',
      '#title' => $this->t('How should aliases should be handled'),
      '#default_value' => $dereference,
      '#required' => TRUE,
      '#options' => [
        // Integers provided as fallback for CI without ldap extension.
        \defined('LDAP_DEREF_NEVER') ? LDAP_DEREF_NEVER : 0 => $this->t('Aliases are never dereferenced (default).'),
        \defined('LDAP_DEREF_SEARCHING') ? LDAP_DEREF_SEARCHING : 1 => $this->t('Aliases should be dereferenced during the search but not when locating the base object of the search.'),
        \defined('LDAP_DEREF_FINDING') ? LDAP_DEREF_FINDING : 2 => $this->t('Aliases should be dereferenced when locating the base object but not during the search.'),
        \defined('LDAP_DEREF_ALWAYS') ? LDAP_DEREF_ALWAYS : 3 => $this->t('Aliases should always be dereferenced.'),
      ],
    ];

    $form['scope'] = [
      '#type' => 'radios',
      '#title' => $this->t('Scope of search'),
      '#default_value' => $ldap_query_entity->get('scope') ?: 'sub',
      '#required' => TRUE,
      '#options' => [
        'sub' => $this->t('Subtree (default)'),
        'base' => $this->t('Base'),
        'one' => $this->t('One Level'),
      ],
      '#description' => $this->t('
      <em>Subtree</em>: This value is used to indicate searching of all entries at all levels under and including the specified base DN.<br>
      <em>Base</em>: This value is used to indicate searching only the entry at the base DN, resulting in only that entry being returned (keep in mind that it also has to meet the search filter criteria).<br>
      <em>One Level</em>: Thhis value is used to indicate searching all entries one level under the base DN - but not including the base DN and not including any entries under that one level under the base DN.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $ldap_query_entity = $this->entity;

    // Ideally this would not be a text-input but an list of text fields.
    $attributes_without_spaces = str_replace(' ', '', $ldap_query_entity->get('attributes'));
    $ldap_query_entity->set('attributes', $attributes_without_spaces);

    $status = $ldap_query_entity->save();

    if ($status === SAVED_NEW) {
      $this->messenger()
        ->addMessage($this->t('Created the %label LDAP query.', [
          '%label' => $ldap_query_entity->label(),
        ]));
    }
    else {
      $this->messenger()
        ->addMessage($this->t('Saved the %label LDAP query.', [
          '%label' => $ldap_query_entity->label(),
        ]));
    }
    $form_state->setRedirectUrl($ldap_query_entity->toUrl('collection'));
  }

}
