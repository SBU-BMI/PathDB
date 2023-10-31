<?php

namespace Drupal\http_response_headers\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\http_response_headers\ResponseHeaderInterface;

/**
 * Defines the Example entity.
 *
 * @ConfigEntityType(
 *   id = "response_header",
 *   label = @Translation("Response Header"),
 *   handlers = {
 *     "list_builder" = "Drupal\http_response_headers\Controller\ResponseHeaderListBuilder",
 *     "form" = {
 *       "add" = "Drupal\http_response_headers\Form\ResponseHeaderForm",
 *       "edit" = "Drupal\http_response_headers\Form\ResponseHeaderForm",
 *       "delete" = "Drupal\http_response_headers\Form\ResponseHeaderDeleteForm",
 *     }
 *   },
 *   config_prefix = "response_header",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "group",
 *     "name",
 *     "value",
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/system/response-headers/{header}",
 *     "delete-form" = "/admin/config/system/response-headers/{header}/delete",
 *   }
 * )
 */
class ResponseHeader extends ConfigEntityBase implements ResponseHeaderInterface
{

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
   * An optional group for this header type.
   *
   * @var string
   */
  public $group;

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

}
