<?php

namespace Drupal\http_response_headers\Entity;

use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the HTTP Response Header entity.
 *
 * @ConfigEntityType(
 *   id = "response_header",
 *   label = @Translation("HTTP Response Header"),
 *   label_collection = @Translation("HTTP Response Headers"),
 *   label_singular = @Translation("HTTP Response Header"),
 *   label_plural = @Translation("HTTP Response Headers"),
 *   label_count = @PluralTranslation(
 *     singular = "@count HTTP response header",
 *     plural = "@count HTTP response headers",
 *   ),
 *   handlers = {
 *     "access" = "Drupal\http_response_headers\Entity\Access\ResponseHeaderAccessControlHandler",
 *     "list_builder" = "Drupal\http_response_headers\Controller\ResponseHeaderListBuilder",
 *     "form" = {
 *       "add" = "Drupal\http_response_headers\Entity\Form\ResponseHeaderForm",
 *       "edit" = "Drupal\http_response_headers\Entity\Form\ResponseHeaderForm",
 *       "delete" = "Drupal\http_response_headers\Entity\Form\ResponseHeaderDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\http_response_headers\Routing\ResponseHeaderRouteProvider",
 *     },
 *   },
 *   config_prefix = "response_header",
 *   admin_permission = "administer http response headers",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "status" = "status",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "name",
 *     "value",
 *     "visibility",
 *   },
 *   links = {
 *     "collection" = "/admin/config/system/response-headers",
 *     "add-form" = "/admin/config/system/response-headers/add",
 *     "edit-form" = "/admin/config/system/response-headers/{response_header}",
 *     "delete-form" = "/admin/config/system/response-headers/{response_header}/delete",
 *     "enable" = "/admin/config/system/response-headers/{response_header}/enable",
 *     "disable" = "/admin/config/system/response-headers/{response_header}/disable",
 *   }
 * )
 */
class ResponseHeader extends ConfigEntityBase implements ResponseHeaderInterface {

  /**
   * The header ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human readable label.
   *
   * @var string
   */
  public $label;

  /**
   * The description.
   *
   * @var string
   */
  public $description;

  /**
   * The header name.
   *
   * @var string
   */
  public $name;

  /**
   * The header value.
   *
   * @var string
   */
  public $value;

  /**
   * The visibility settings for this HTTP header response.
   *
   * @var array
   */
  protected $visibility = [];

  /**
   * The available contexts for this HTTP header response and its visibility conditions.
   *
   * @var array
   */
  protected $contexts = [];

  /**
   * The visibility collection.
   *
   * @var \Drupal\Core\Condition\ConditionPluginCollection
   */
  protected $visibilityCollection;

  /**
   * The condition plugin manager.
   *
   * @var \Drupal\Core\Executable\ExecutableManagerInterface
   */
  protected $conditionPluginManager;

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();
    $this->addDependency('module', 'http_response_headers');
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getVisibility() {
    return $this->getVisibilityConditions()->getConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function setVisibilityConfig($instance_id, array $configuration) {
    $conditions = $this->getVisibilityConditions();
    if (!$conditions->has($instance_id)) {
      $configuration['id'] = $instance_id;
      $conditions->addInstanceId($instance_id, $configuration);
    }
    else {
      $conditions->setInstanceConfiguration($instance_id, $configuration);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getVisibilityConditions() {
    if (!isset($this->visibilityCollection)) {
      $this->visibilityCollection = new ConditionPluginCollection($this->conditionPluginManager(), $this->get('visibility'));
    }
    return $this->visibilityCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getVisibilityCondition($instance_id) {
    return $this->getVisibilityConditions()->get($instance_id);
  }

  /**
   * Gets the condition plugin manager.
   *
   * @return \Drupal\Core\Executable\ExecutableManagerInterface
   *   The condition plugin manager.
   */
  protected function conditionPluginManager() {
    if (!isset($this->conditionPluginManager)) {
      $this->conditionPluginManager = \Drupal::service('plugin.manager.condition');
    }
    return $this->conditionPluginManager;
  }

}
