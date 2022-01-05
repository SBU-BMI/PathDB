<?php

namespace Drupal\tac_lite\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Builds the configuration form.
 */
class ConfigForm extends ConfigFormBase {

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
    return 'tac_lite_admin_settings';
  }
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $vocabularies = Vocabulary::loadMultiple();
    if (!count($vocabularies)) {
      $form['body'] = [
        '#markup' => $this->t('You must <a href=":url">create a vocabulary</a> before you can use
          tac_lite.', [':url' => Url::fromRoute('entity.taxonomy_vocabulary.add_form')->toString()]),
      ];
      return $form;
    }
    else {
      $settings = $this->configFactory->get('tac_lite.settings');
      $options = [];
      foreach ($vocabularies as $vocab) {
        $options[$vocab->get('vid')] = $vocab->get('name');
      }
      $form['tac_lite_categories'] = [
        '#type' => 'select',
        '#title' => $this->t('Vocabularies'),
        '#default_value' => $settings->get('tac_lite_categories'),
        '#options' => $options,
        '#description' => $this->t('Select one or more vocabularies to control privacy.<br/>Use caution with hierarchical (nested) taxonomies as <em>visibility</em> settings may cause problems on node edit forms.<br/>Do not select free tagging vocabularies, they are not supported.'),
        '#multiple' => TRUE,
        '#required' => TRUE,
      ];
      $scheme_options = [];
      // Currently only view, edit, delete permissions possible, so 7
      // permutations will be more than enough.
      for ($i = 1; $i < 8; $i++) {
        $scheme_options[$i] = $i;
      }
      $form['tac_lite_schemes'] = [
        '#type' => 'select',
        '#title' => $this->t('Number of Schemes'),
        '#description' => $this->t('Each scheme allows for a different set of permissions.  For example, use scheme 1 for read-only permission; scheme 2 for read and update; scheme 3 for delete; etc. Additional schemes increase the size of your node_access table, so use no more than you need.'),
        '#default_value' => $settings->get('tac_lite_schemes'),
        '#options' => $scheme_options,
        '#required' => TRUE,
      ];
      $form['tac_lite_rebuild'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Rebuild content permissions now'),
        // Default false because usually only needed after scheme
        // has been changed.
        '#default_value' => FALSE,
        '#description' => $this->t('Do this once, after you have fully configured access by taxonomy.'),
      ];
    }
    return parent::buildForm($form, $form_state);
  }
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Clear the cache if new schemes are created/deleted, so that tabs are correctly displayed/removed.
    if ($this->config('tac_lite.settings')->get('tac_lite_schemes') != $form_state->getValue('tac_lite_schemes')) {
      \Drupal::cache('render')->deleteAll();
    }

    // Change configuration.
    $this->config('tac_lite.settings')
      ->set('tac_lite_categories', $form_state->getValue('tac_lite_categories'))
      ->set('tac_lite_schemes', $form_state->getValue('tac_lite_schemes'))
      ->save();

    // Rebuild the node_access table.
    $rebuild = $form_state->getValue('tac_lite_rebuild');
    if ($rebuild) {
      node_access_rebuild(TRUE);
    }
    else {
      $this->messenger()->addWarning($this->t('Do not forget to <a href=:url>rebuild node access permissions </a> after you have configured taxonomy-based access.', [
        ':url' => Url::fromRoute('node.configure_rebuild_confirm')->toString(),
      ]));
    }
    // And rebuild menus, in case the number of schemes has changed.
    \Drupal::service('router.builder')->rebuild();
    parent::submitForm($form, $form_state);
  }

}
