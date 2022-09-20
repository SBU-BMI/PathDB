<?php

declare(strict_types = 1);

namespace Drupal\ldap_user\Form;

use Drupal\ldap_user\FieldProvider;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ldap_servers\Mapping;

/**
 * Provides the form to configure user configuration and field mapping.
 */
class LdapUserMappingToDrupalForm extends LdapUserMappingBaseForm {

  /**
   * Direction.
   *
   * @var string
   */
  protected $direction = self::PROVISION_TO_DRUPAL;

  /**
   * Events.
   *
   * @var array
   */
  protected $events = [
    self::EVENT_CREATE_DRUPAL_USER,
    self::EVENT_SYNC_TO_DRUPAL_USER,
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ModuleHandler $module_handler,
    EntityTypeManagerInterface $entity_type_manager,
    FieldProvider $field_provider
  ) {
    parent::__construct($config_factory, $module_handler, $entity_type_manager, $field_provider);
    $this->server = $this->currentConfig->get('drupalAcctProvisionServer');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ldap_user_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['header'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Mappings synced from LDAP to Drupal'),
      '#description' => $this->t('See also the <a href="@wiki_link">Drupal.org wiki page</a> for further information on using LDAP tokens.',
        ['@wiki_link' => 'https://drupal.org/node/1245736']),
    ];

    $form['mappings'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Label'),
        $this->t('Machine name'),
        $this->t('Weight'),
        $this->t('Operations'),
      ],
      '#attributes' => ['class' => ['mappings-table']],
      '#prefix' => '<div id="ldap-user-mappings-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['mappings']['#header'] = [
      [
        'data' => $this->t('Source LDAP tokens'),
        'rowspan' => 1,
      ],
      [
        'data' => $this->t('Convert from binary'),
      ],
      [
        'data' => $this->t('Target Drupal attribute'),
        'rowspan' => 1,
      ],
      [
        'data' => $this->t('Synchronization event'),
        'colspan' => 2,
        'rowspan' => 1,
      ],
      [
        'data' => $this->t('Delete'),
      ],
      [
        // Needed for offset created by 'configured_mapping'.
      ],
    ];

    $form['mappings']['second-header'] = [
      '#attributes' => ['class' => 'header'],
      [
        '#title' => $this->t('Examples: [sn], [mail:0], [ou:last], [sn], [givenName].<br> Constants such as <em>17</em> or <em>imported</em> should not be enclosed in [].'),
        '#type' => 'item',
        '#colspan' => 2,
      ],
      [],
      [],
      [
        '#title' => $this->t('On Drupal User Creation'),
        '#type' => 'item',
        '#class' => 'header-provisioning',
        '#rowspan' => 2,
      ],
      [
        '#title' => $this->t('On Sync to Drupal User'),
        '#type' => 'item',
        '#class' => 'header-provisioning',
      ],
      [],
      [],
    ];

    $mappings_to_add = $this->getServerMappingFields($form_state);
    if ($mappings_to_add) {
      $form['mappings'] += $mappings_to_add;
    }

    $form['mappings'][]['mappings_add_another'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Another'),
      '#submit' => ['::mappingsAddAnother'],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => '::mappingsAjaxCallback',
        'wrapper' => 'ldap-user-mappings-wrapper',
      ],
      '#weight' => 103,
      '#wrapper_attributes' => ['colspan' => 7],
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Save',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getMappingRow(Mapping $mapping, array $target_fields, int $row_id): array {
    $result = [];

    if ($mapping->isConfigurable()) {
      $result['source'] = [
        '#type' => 'textfield',
        '#title' => 'LDAP attribute',
        '#title_display' => 'invisible',
        '#default_value' => $mapping->getLdapAttribute(),
        '#size' => 20,
        '#maxlength' => 255,
        '#attributes' => ['class' => ['ldap-attr']],
      ];
      $result['convert'] = [
        '#type' => 'checkbox',
        '#title' => 'Convert from binary',
        '#title_display' => 'invisible',
        '#default_value' => $mapping->isBinary(),
        '#attributes' => ['class' => ['convert']],
      ];
      $result['target'] = [
        '#type' => 'select',
        '#title' => 'User attribute',
        '#title_display' => 'invisible',
        '#default_value' => $mapping->getDrupalAttribute(),
        '#options' => $target_fields,
      ];
    }
    else {
      $result['source'] = [
        '#type' => 'item',
        '#default_value' => $mapping->getLdapAttribute(),
        '#markup' => $mapping->getLdapAttribute(),
        '#attributes' => ['class' => ['source']],
      ];
      $result['convert'] = [
        '#type' => 'checkbox',
        '#title' => 'Convert from binary',
        '#title_display' => 'invisible',
        '#default_value' => $mapping->isBinary(),
        '#disabled' => TRUE,
        '#attributes' => ['class' => ['convert']],
      ];
      $result['target'] = [
        '#type' => 'item',
        '#markup' => $mapping->getLabel(),
      ];
    }

    foreach ($this->events as $event) {
      $result[$event] = [
        '#type' => 'checkbox',
        '#title' => $event,
        '#title_display' => 'invisible',
        '#default_value' => $mapping->hasProvisioningEvent($event),
        '#disabled' => !$mapping->isConfigurable(),
        '#attributes' => ['class' => ['sync-method']],
      ];
    }

    $result['delete'] = [
      '#type' => 'checkbox',
      '#default_value' => 0,
      '#disabled' => !$mapping->isConfigurable(),
    ];

    $result['configured_mapping'] = [
      '#type' => 'value',
      '#value' => $mapping->isConfigurable(),
    ];

    return $result;
  }

  /**
   * Set specific mapping.
   *
   * @param \Drupal\ldap_servers\Mapping $mapping
   *   Mapping.
   * @param array $row
   *   Row.
   */
  protected function setSpecificMapping(Mapping $mapping, array $row): void {
    $mapping->setLdapAttribute(trim($row['source']));
    $mapping->setDrupalAttribute(trim($row['target']));
  }

}
