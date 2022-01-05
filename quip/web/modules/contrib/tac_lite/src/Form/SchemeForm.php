<?php

namespace Drupal\tac_lite\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Component\Utility\Html;

/**
 * Builds the scheme configuration form.
 */
class SchemeForm extends ConfigFormBase {

  private $scheme;

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['tac_lite.settings'];
  }
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tac_lite_admin_scheme_form';
  }
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $scheme = NULL) {
    $settings = $this->configFactory->get('tac_lite.settings');
    $this->scheme = $scheme;
    $vids = $settings->get('tac_lite_categories');
    $roles = user_roles();
    $config = self::tacLiteConfig($scheme);
    $form['#tac_lite_config'] = $config;
    if (count($vids)) {
      $form['tac_lite_config_scheme_' . $scheme] = ['#tree' => TRUE];
      $form['tac_lite_config_scheme_' . $scheme]['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Scheme name'),
        '#description' => $this->t("A human-readable name for administrators to see. For example, 'read' or 'read and write'."),
        '#default_value' => $config['name'],
        '#required' => TRUE,
      ];
      // Currently, only view, update and delete are supported by node_access.
      $options = [
        'grant_view' => 'view',
        'grant_update' => 'update',
        'grant_delete' => 'delete',
      ];
      $form['tac_lite_config_scheme_' . $scheme]['perms'] = [
        '#type' => 'select',
        '#title' => $this->t('Permissions'),
        '#multiple' => TRUE,
        '#options' => $options,
        '#default_value' => $config['perms'],
        '#description' => $this->t('Select which permissions are granted by this scheme.  <br/>Note when granting update, it is best to enable visibility on all terms.  Otherwise a user may unknowingly remove invisible terms while editing a node.'),
        '#required' => FALSE,
      ];

      $form['tac_lite_config_scheme_' . $scheme]['unpublished'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Apply to unpublished content'),
        '#description' => $this->t('If checked, permissions in this scheme will apply to unpublished content.  If this scheme includes the view permission, then <strong>unpublished nodes will be visible</strong> to users whose roles would grant them access to the published node.'),
        '#default_value' => $config['unpublished'],
      ];

      $form['tac_lite_config_scheme_' . $scheme]['term_visibility'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Visibility'),
        '#description' => $this->t('If checked, this scheme determines whether a user can view <strong>terms</strong>.  Note the <em>view</em> permission in the select field above refers to <strong>node</strong> visibility.  This checkbox refers to <strong>term</strong> visibility, for example in a content edit form or tag cloud.'),
        '#default_value' => $config['term_visibility'],
      ];

      $form['helptext'] = [
        '#type' => 'markup',
        '#markup' => $this->t('To grant to an individual user, visit the <em>access by taxonomy</em> tab on the account edit page.'),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ];
      $form['helptext2'] = [
        '#type' => 'markup',
        '#markup' => $this->t('To grant by role, select the terms below.'),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ];
      $vocabularies = Vocabulary::loadMultiple();
      $all_defaults = $settings->get('tac_lite_grants_scheme_' . $scheme);
      $form['tac_lite_grants_scheme_' . $scheme] = ['#tree' => TRUE];
      foreach ($roles as $rid => $role) {
        $role_name = $role->get('label');
        $form['tac_lite_grants_scheme_' . $scheme][$rid] = [
          '#type' => 'details',
          '#tree' => TRUE,
          '#title' => Html::escape($this->t('Grant permission by role: :role', [':role' => $role_name])),
          '#open' => TRUE,
        ];
        $defaults = isset($all_defaults[$rid]) ? $all_defaults[$rid] : NULL;
        foreach ($vids as $vid) {
          // Build a taxonomy select form element for this vocab.
          $v = $vocabularies[$vid];
          $default_values = isset($defaults[$vid]) ? $defaults[$vid] : NULL;
          $form['tac_lite_grants_scheme_' . $scheme][$rid][$vid] = self::tacLiteTermSelect($v, $default_values);
        }
      }
      $form['tac_lite_rebuild'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Rebuild content permissions now'),
        // Default false because usually only needed after scheme has
        // been changed.
        '#default_value' => FALSE,
        '#description' => $this->t('Do this once, after you have fully configured access by taxonomy.'),
        '#weight' => 9,
      ];
    }
    else {
      $form['tac_lite_help'] = [
        '#type' => 'markup',
        '#prefix' => '<p>',
        '#suffix' => '</p>',
        '#markup' => $this->t('First, select one or more vocabularies on the <a href=:url>settings tab</a>. Then, return to this page to complete configuration.', [':url' => Url::fromRoute('tac_lite.administration')->toString()]),
      ];
    }

    return parent::buildForm($form, $form_state);
  }
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, $scheme = NULL) {
    $scheme = $this->scheme;
    $values = $form_state->getValues();
    $this->config('tac_lite.settings')
      ->set('menu_rebuild_needed', TRUE)
      ->set('tac_lite_config_scheme_' . $scheme, $values['tac_lite_config_scheme_' . $scheme])
      ->set('tac_lite_grants_scheme_' . $scheme, $values['tac_lite_grants_scheme_' . $scheme])
      ->save();
    if ($values['tac_lite_rebuild']) {
      node_access_rebuild(TRUE);
    }
    else {
      $this->messenger()->addWarning($this->t('Do not forget to <a href=:url>rebuild node access permissions</a> after you have configured taxonomy-based access.', [':url' => Url::fromRoute('node.configure_rebuild_confirm')->toString()]));
    }
    parent::submitForm($form, $form_state);
  }
  /**
   * Helper function to get configuration of scheme.
   */
  public static function tacLiteConfig($scheme) {
    $settings = \Drupal::config('tac_lite.settings');
    $config = $settings->get('tac_lite_config_scheme_' . $scheme);
    $config['name'] = !empty($config['name']) ? $config['name'] : NULL;
    $config['perms'] = !empty($config['perms']) ? $config['perms'] : [];
    $config += [
      'term_visibility' => (isset($config['perms']['grant_view']) && $config['perms']['grant_view']),
      'unpublished' => FALSE,
      'realm' => 'tac_lite_scheme_' . $scheme,
    ];
    return $config;
  }
  /**
   * Helper function to build a taxonomy term select element for a form.
   *
   * @param object $v
   *   A vocabulary object containing a vid and name.
   * @param array $default_values
   *   An array of values to use for the default_value argument for this
   *   form element.
   */
  public static function tacLiteTermSelect($v, $default_values = []) {
    $tree = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->loadTree($v->get('vid'));
    $options = [0 => '<none>'];
    if ($tree) {
      foreach ($tree as $term) {
        $choice = new \stdClass();
        $choice->option = [$term->tid => str_repeat('-', $term->depth) . $term->name];
        $options[] = $choice;
      }
    }
    $field_array = [
      '#type' => 'select',
      '#title' => $v->get('name'),
      '#default_value' => $default_values,
      '#options' => $options,
      '#multiple' => TRUE,
      '#description' => $v->get('description'),
    ];
    return $field_array;
  }

}
