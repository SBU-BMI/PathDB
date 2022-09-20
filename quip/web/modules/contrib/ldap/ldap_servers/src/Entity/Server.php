<?php

declare(strict_types = 1);

namespace Drupal\ldap_servers\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ldap_servers\LdapTransformationTraits;
use Drupal\ldap_servers\ServerInterface;
use Symfony\Component\Ldap\Entry;

/**
 * Defines the Server entity.
 *
 * @ConfigEntityType(
 *   id = "ldap_server",
 *   label = @Translation("LDAP Server"),
 *   handlers = {
 *     "list_builder" = "Drupal\ldap_servers\ServerListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ldap_servers\Form\ServerForm",
 *       "edit" = "Drupal\ldap_servers\Form\ServerForm",
 *       "delete" = "Drupal\ldap_servers\Form\ServerDeleteForm",
 *       "test" = "Drupal\ldap_servers\Form\ServerTestForm",
 *       "enable_disable" = "Drupal\ldap_servers\Form\ServerEnableDisableForm"
 *     }
 *   },
 *   config_prefix = "server",
 *   admin_permission = "administer ldap",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/people/ldap/server/{server}/edit",
 *     "delete-form" = "/admin/config/people/ldap/server/{server}/delete",
 *     "collection" = "/admin/config/people/ldap/server"
 *   },
 *   config_export = {
 *    "id",
 *    "label",
 *    "type",
 *    "uuid",
 *    "account_name_attr",
 *    "address",
 *    "basedn",
 *    "bind_method",
 *    "binddn",
 *    "bindpw",
 *    "encryption",
 *    "grp_derive_from_dn_attr",
 *    "grp_derive_from_dn",
 *    "grp_memb_attr_match_user_attr",
 *    "grp_memb_attr",
 *    "grp_nested",
 *    "grp_object_cat",
 *    "grp_test_grp_dn_writeable",
 *    "grp_test_grp_dn",
 *    "grp_unused",
 *    "grp_user_memb_attr_exists",
 *    "grp_user_memb_attr",
 *    "mail_attr",
 *    "mail_template",
 *    "picture_attr",
 *    "port",
 *    "status",
 *    "testing_drupal_user_dn",
 *    "testing_drupal_username",
 *    "timeout",
 *    "unique_persistent_attr_binary",
 *    "unique_persistent_attr",
 *    "user_attr",
 *    "user_dn_expression",
 *    "weight",
 *   }
 * )
 */
class Server extends ConfigEntityBase implements ServerInterface {

  use LdapTransformationTraits;
  use StringTranslationTrait;

  /**
   * Logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * LDAP Details logger.
   *
   * @var \Drupal\ldap_servers\Logger\LdapDetailLog
   */
  protected $detailLog;

  /**
   * Token processor.
   *
   * @var \Drupal\ldap_servers\Processor\TokenProcessor
   */
  protected $tokenProcessor;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;


  /**
   * Server machine name.
   *
   * @var string
   */
  protected $id;

  /**
   * Human readable name.
   *
   * @var string
   */
  protected $label;

  /**
   * Server type.
   *
   * @var string
   */
  protected $type;

  /**
   * LDAP Server connection.
   *
   * @var resource|false
   */
  protected $connection = FALSE;

  /**
   * Account name attribute.
   *
   * @var string
   */
  protected $account_name_attr;

  /**
   * Server address.
   *
   * @var string
   */
  protected $address;

  /**
   * Base DN.
   *
   * @var array
   */
  protected $basedn;

  /**
   * Bind method.
   *
   * @var string
   */
  protected $bind_method;

  /**
   * Bind DN.
   *
   * @var string
   */
  protected $binddn;

  /**
   * Bind password.
   *
   * @var string
   */
  protected $bindpw;

  /**
   * Attribute of the User's LDAP Entry DN which contains the group.
   *
   * @var string
   */
  protected $grp_derive_from_dn_attr;

  /**
   * Groups are derived from user's LDAP entry DN.
   *
   * @var bool
   */
  protected $grp_derive_from_dn = FALSE;

  /**
   * User attribute held in "LDAP Group Entry Attribute Holding...".
   *
   * @var string
   */
  protected $grp_memb_attr_match_user_attr;

  /**
   * LDAP Group Entry Attribute Holding User's DN, CN, etc.
   *
   * @var string
   */
  protected $grp_memb_attr;

  /**
   * Nested groups are used in my LDAP.
   *
   * @var bool
   */
  protected $grp_nested = FALSE;

  /**
   * Name of Group Object Class.
   *
   * @var string
   */
  protected $grp_object_cat;

  /**
   * Testing LDAP Group DN that is writable.
   *
   * @var string
   */
  protected $grp_test_grp_dn_writeable;

  /**
   * Testing LDAP Group DN.
   *
   * @var string
   */
  protected $grp_test_grp_dn;

  /**
   * Groups are not relevant to this Drupal site.
   *
   * @var bool
   */
  protected $grp_unused = TRUE;

  /**
   * Attribute in User Entry Containing Groups.
   *
   * @var bool
   */
  protected $grp_user_memb_attr_exists;

  /**
   * Attribute in User Entry Containing Groups.
   *
   * @var string
   */
  protected $grp_user_memb_attr;

  /**
   * Email attribute.
   *
   * @var string
   */
  protected $mail_attr;

  /**
   * Email template.
   *
   * @var string
   */
  protected $mail_template;

  /**
   * Thumbnail attribute.
   *
   * @var string
   */
  protected $picture_attr;

  /**
   * Port.
   *
   * @var int
   */
  protected $port;

  /**
   * DN of testing username.
   *
   * @var string
   */
  protected $testing_drupal_user_dn;

  /**
   * Testing Drupal Username.
   *
   * @var string
   */
  protected $testing_drupal_username;

  /**
   * Timeout.
   *
   * @var int
   */
  protected $timeout;

  /**
   * Use Start-TLS.
   *
   * @var bool
   */
  protected $tls = FALSE;

  /**
   * Does PUID hold a binary value?
   *
   * @var bool
   */
  protected $unique_persistent_attr_binary;

  /**
   * Persistent and Unique User ID Attribute.
   *
   * @var string
   */
  protected $unique_persistent_attr;

  /**
   * Authentication name attribute.
   *
   * @var string
   */
  protected $user_attr;

  /**
   * Expression for user DN.
   *
   * @var string
   */
  protected $user_dn_expression;

  /**
   * Weight.
   *
   * @var int
   */
  protected $weight;

  /**
   * Constructor.
   *
   * @param array $values
   *   Values.
   * @param string $entity_type
   *   Entity Type.
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);
    $this->logger = \Drupal::logger('ldap_servers');
    $this->detailLog = \Drupal::service('ldap.detail_log');
    $this->tokenProcessor = \Drupal::service('ldap.token_processor');
    $this->moduleHandler = \Drupal::service('module_handler');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormattedBind(): TranslatableMarkup {
    switch ($this->get('bind_method')) {
      case 'service_account':
      default:
        $namedBind = $this->t('service account bind');
        break;

      case 'user':
        $namedBind = $this->t('user credentials bind');
        break;

      case 'anon':
        $namedBind = $this->t('anonymous bind (search), then user credentials');
        break;

      case 'anon_user':
        $namedBind = $this->t('anonymous bind');
        break;
    }
    return $namedBind;
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseDn(): array {
    return $this->get('basedn');
  }

  /**
   * {@inheritdoc}
   */
  public function deriveUsernameFromLdapResponse(Entry $ldap_entry): string {
    $accountName = '';

    if ($this->getAccountNameAttribute()) {
      if ($ldap_entry->hasAttribute($this->getAccountNameAttribute(), FALSE)) {
        $accountName = $ldap_entry->getAttribute($this->getAccountNameAttribute(), FALSE)[0];
      }
    }
    elseif ($this->getAuthenticationNameAttribute()) {
      if ($ldap_entry->hasAttribute($this->getAuthenticationNameAttribute(), FALSE)) {
        $accountName = $ldap_entry->getAttribute($this->getAuthenticationNameAttribute(), FALSE)[0];
      }
    }

    return $accountName;
  }

  /**
   * {@inheritdoc}
   */
  public function deriveEmailFromLdapResponse(Entry $ldap_entry): string {
    $mail = '';
    // Not using template.
    if ($this->getMailAttribute() && $ldap_entry->hasAttribute($this->getMailAttribute(), FALSE)) {
      $mail = $ldap_entry->getAttribute($this->getMailAttribute(), FALSE)[0];
    }
    elseif ($this->getMailTemplate()) {
      // Template is of form [cn]@illinois.edu.
      $mail = $this->tokenProcessor->ldapEntryReplacementsForDrupalAccount($ldap_entry, $this->getMailTemplate());
    }

    return $mail;
  }

  /**
   * {@inheritdoc}
   */
  public function derivePuidFromLdapResponse(Entry $ldapEntry): string {
    $puid = '';
    if ($this->getUniquePersistentAttribute() && $ldapEntry->hasAttribute($this->getUniquePersistentAttribute(), FALSE)) {
      $puid = $ldapEntry->getAttribute($this->getUniquePersistentAttribute(), FALSE)[0];
      if ($this->isUniquePersistentAttributeBinary()) {
        $puid = bin2hex($puid);
      }
    }
    return $puid;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccountNameAttribute(): ?string {
    return $this->account_name_attr;
  }

  /**
   * {@inheritdoc}
   */
  public function hasAccountNameAttribute(): bool {
    return !empty($this->account_name_attr);
  }

  /**
   * {@inheritdoc}
   */
  public function getServerAddress(): string {
    return $this->address;
  }

  /**
   * {@inheritdoc}
   */
  public function getBindMethod(): string {
    return $this->bind_method;
  }

  /**
   * {@inheritdoc}
   */
  public function getBindDn(): ?string {
    return $this->binddn;
  }

  /**
   * {@inheritdoc}
   */
  public function getBindPassword(): ?string {
    return $this->bindpw;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivedGroupFromDnAttribute(): ?string {
    return $this->grp_derive_from_dn_attr;
  }

  /**
   * {@inheritdoc}
   */
  public function isGroupDerivedFromDn(): bool {
    return $this->grp_derive_from_dn;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserAttributeFromGroupMembershipEntryAttribute(): ?string {
    return $this->grp_memb_attr_match_user_attr;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupMembershipAttribute(): ?string {
    return $this->grp_memb_attr;
  }

  /**
   * {@inheritdoc}
   */
  public function isGrouppNested(): bool {
    return $this->grp_nested;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupObjectClass(): ?string {
    return $this->grp_object_cat;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupTestGroupDnWriteable(): ?string {
    return $this->grp_test_grp_dn_writeable;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupTestGroupDn(): ?string {
    return $this->grp_test_grp_dn;
  }

  /**
   * {@inheritdoc}
   */
  public function isGroupUnused(): bool {
    return $this->grp_unused;
  }

  /**
   * {@inheritdoc}
   */
  public function isGroupUserMembershipAttributeInUse(): bool {
    return $this->grp_user_memb_attr_exists;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupUserMembershipAttribute(): ?string {
    return $this->grp_user_memb_attr;
  }

  /**
   * {@inheritdoc}
   */
  public function getMailAttribute(): ?string {
    return $this->mail_attr;
  }

  /**
   * {@inheritdoc}
   */
  public function getMailTemplate(): ?string {
    return $this->mail_template;
  }

  /**
   * {@inheritdoc}
   */
  public function getPictureAttribute(): ?string {
    return $this->picture_attr;
  }

  /**
   * {@inheritdoc}
   */
  public function getPort(): int {
    return $this->port;
  }

  /**
   * {@inheritdoc}
   */
  public function isActive(): bool {
    return $this->status;
  }

  /**
   * {@inheritdoc}
   */
  public function getTestingDrupalUserDn(): ?string {
    return $this->testing_drupal_user_dn;
  }

  /**
   * {@inheritdoc}
   */
  public function getTestingDrupalUsername(): ?string {
    return $this->testing_drupal_username;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimeout(): int {
    return $this->timeout;
  }

  /**
   * {@inheritdoc}
   */
  public function isUniquePersistentAttributeBinary(): bool {
    return $this->unique_persistent_attr_binary ?: FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getUniquePersistentAttribute(): ?string {
    return $this->unique_persistent_attr;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthenticationNameAttribute(): ?string {
    return $this->user_attr;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserDnExpression(): ?string {
    return $this->user_dn_expression;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(): int {
    return $this->weight;
  }

}
