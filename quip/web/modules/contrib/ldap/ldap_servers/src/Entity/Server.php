<?php

namespace Drupal\ldap_servers\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\ldap_servers\Helper\ConversionHelper;
use Drupal\ldap_servers\Helper\CredentialsStorage;
use Drupal\ldap_servers\LdapProtocolInterface;
use Drupal\ldap_servers\Helper\MassageAttributes;
use Drupal\ldap_servers\ServerInterface;
use Drupal\ldap_servers\Processor\TokenProcessor;
use Drupal\user\Entity\User;

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
 *   }
 * )
 */
class Server extends ConfigEntityBase implements ServerInterface, LdapProtocolInterface {

  const LDAP_OPT_DIAGNOSTIC_MESSAGE_BYTE = 0x0032;
  const LDAP_SERVER_LDAP_QUERY_CHUNK = 50;
  const LDAP_SERVER_LDAP_QUERY_RECURSION_LIMIT = 10;

  const SCOPE_BASE = 1;
  const SCOPE_ONE_LEVEL = 2;
  const SCOPE_SUBTREE = 3;

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
   * LDAP Server connection.
   *
   * @var resource|false
   */
  protected $connection = FALSE;

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
   * Where the paged search starts.
   *
   * @var int
   *
   * @TODO: This is never set and thus constant.
   */
  protected $searchPageStart = 0;

  /**
   * Where the paged search ends.
   *
   * @var mixed
   *
   * @TODO: This is never set and thus constant.
   */
  protected $searchPageEnd = NULL;

  /**
   * Constructor.
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);
    $this->logger = \Drupal::logger('ldap_servers');
    $this->detailLog = \Drupal::service('ldap.detail_log');
    $this->tokenProcessor = \Drupal::service('ldap.token_processor');
    $this->moduleHandler = \Drupal::service('module_handler');
  }

  /**
   * Returns the formatted label of the bind method.
   *
   * @return string
   *   The formatted text for the current bind.
   */
  public function getFormattedBind() {
    switch ($this->get('bind_method')) {
      case 'service_account':
      default:
        $namedBind = t('service account bind');
        break;

      case 'user':
        $namedBind = t('user credentials bind');
        break;

      case 'anon':
        $namedBind = t('anonymous bind (search), then user credentials');
        break;

      case 'anon_user':
        $namedBind = t('anonymous bind');
        break;
    }
    return $namedBind;
  }

  /**
   * Connects to the LDAP server.
   *
   * @return int
   *   LDAP_SUCCESS or the relevant error.
   */
  public function connect() {
    if (!function_exists('ldap_connect')) {
      $this->logger->error('PHP LDAP extension not found, aborting.');
      return self::LDAP_NOT_SUPPORTED;
    }

    $this->connection = ldap_connect(self::get('address'), self::get('port'));

    if (!$this->connection) {
      $this->logger->notice('LDAP Connect failure to @address on port @port.',
        ['@address' => self::get('address'), '@port' => self::get('port')]
      );
      return self::LDAP_CONNECT_ERROR;
    }

    ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($this->connection, LDAP_OPT_REFERRALS, 0);
    ldap_set_option($this->connection, LDAP_OPT_NETWORK_TIMEOUT, self::get('timeout'));

    // Use TLS if we are configured and able to.
    if (self::get('tls')) {
      ldap_get_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, $protocolVersion);
      if ($protocolVersion == -1) {
        $this->logger->notice('Could not get LDAP protocol version.');
        return self::LDAP_PROTOCOL_ERROR;
      }
      if ($protocolVersion != 3) {
        $this->logger->notice('Could not start TLS, only supported by LDAP v3.');
        return self::LDAP_CONNECT_ERROR;
      }
      elseif (!function_exists('ldap_start_tls')) {
        $this->logger->notice('Could not start TLS. It does not seem to be supported by this PHP setup.');
        return self::LDAP_CONNECT_ERROR;
      }
      elseif (!ldap_start_tls($this->connection)) {
        $this->logger->notice('Could not start TLS. (Error @errno: @error).', [
          '@errno' => ldap_errno($this->connection),
          '@error' => ldap_error($this->connection),
        ]
          );
        return self::LDAP_CONNECT_ERROR;
      }
    }

    return self::LDAP_SUCCESS;
  }

  /**
   * Bind (authenticate) against an active LDAP database.
   *
   * @return int
   *   Result of bind in form of LDAP_SUCCESS or relevant error.
   */
  public function bind() {

    // Ensure that we have an active server connection.
    if (!$this->connection) {
      $this->logger->error("LDAP bind failure. Not connected to LDAP server.");
      return self::LDAP_CONNECT_ERROR;
    }

    // Explicitly check for valid binding due to some upgrade issues.
    $validMethods = ['service_account', 'user', 'anon', 'anon_user'];
    if (!in_array($this->get('bind_method'), $validMethods)) {
      $this->logger->error("Bind method missing.");
      return self::LDAP_CONNECT_ERROR;
    }

    if ($this->get('bind_method') == 'anon') {
      $anon_bind = TRUE;
    }
    elseif ($this->get('bind_method') == 'anon_user') {
      if (CredentialsStorage::validateCredentials()) {
        $anon_bind = FALSE;
      }
      else {
        $anon_bind = TRUE;
      }
    }
    else {
      $anon_bind = FALSE;
    }

    if ($anon_bind) {
      $response = $this->anonymousBind();
    }
    else {
      $response = $this->nonAnonymousBind();
    }
    return $response;
  }

  /**
   * Bind to server anonymously.
   *
   * @return int
   *   Returns the binding response code from LDAP.
   */
  private function anonymousBind() {
    if (@!ldap_bind($this->connection)) {
      $this->detailLog->log("LDAP anonymous bind error. Error %error",
        ['%error' => $this->formattedError($this->ldapErrorNumber())]
      );
      $response = ldap_errno($this->connection);
    }
    else {
      $response = self::LDAP_SUCCESS;
    }
    return $response;
  }

  /**
   * Bind to server with credentials.
   *
   * This uses either service account credentials or stored credentials if it
   * has been toggled through CredentialsStorage::testCredentials(true).
   *
   * @return int
   *   Returns the binding response code from LDAP.
   */
  private function nonAnonymousBind() {
    // Default credentials form service account.
    $userDn = $this->get('binddn');
    $password = $this->get('bindpw');

    // Runtime credentials for user binding and password checking.
    if (CredentialsStorage::validateCredentials()) {
      $userDn = CredentialsStorage::getUserDn();
      $password = CredentialsStorage::getPassword();
    }

    if (mb_strlen($password) == 0 || mb_strlen($userDn) == 0) {
      $this->logger->notice("LDAP bind failure due to missing credentials for user userdn=%userdn, pass=%pass.", [
        '%userdn' => $userDn,
        '%pass' => $password,
      ]);
      $response = self::LDAP_LOCAL_ERROR;
    }
    if (@!ldap_bind($this->connection, $userDn, $password)) {
      $this->detailLog->log("LDAP bind failure for user %user. Error %errno: %error", [
        '%user' => $userDn,
        '%errno' => ldap_errno($this->connection),
        '%error' => ldap_error($this->connection),
      ]
      );
      $response = ldap_errno($this->connection);
    }
    else {
      $response = self::LDAP_SUCCESS;
    }
    return $response;
  }

  /**
   * Disconnect (unbind) from an active LDAP server.
   */
  public function disconnect() {
    if (!$this->connection) {
      // Never bound or not currently bound, so no need to disconnect.
    }
    else {
      ldap_unbind($this->connection);
      $this->connection = NULL;
    }
  }

  /**
   * Checks if connected and connects and binds otherwise.
   */
  public function connectAndBindIfNotAlready() {
    if (!$this->connection) {
      $this->connect();
      $this->bind();
    }
  }

  /**
   * Does dn exist for this server and what is its data?
   *
   * @param string $dn
   *   DN to search for.
   * @param array $attributes
   *   In same form as ldap_read $attributes parameter.
   *
   * @return bool|array
   *   Return ldap entry or false.
   */
  public function checkDnExistsIncludeData($dn, array $attributes) {
    $params = [
      'base_dn' => $dn,
      'attributes' => $attributes,
      'attrsonly' => FALSE,
      'filter' => '(objectclass=*)',
      'sizelimit' => 0,
      'timelimit' => 0,
      'deref' => NULL,
    ];

    $result = $this->ldapQuery(Server::SCOPE_BASE, $params);
    if ($result !== FALSE) {
      $entries = @ldap_get_entries($this->connection, $result);
      if ($entries !== FALSE && $entries['count'] > 0) {
        return $entries[0];
      }
    }
    return FALSE;
  }

  /**
   * Does dn exist for this server?
   *
   * @param string $dn
   *   DN to search for.
   *
   * @return bool
   *   DN exists.
   */
  public function checkDnExists($dn) {
    $params = [
      'base_dn' => $dn,
      'attributes' => ['objectclass'],
      'attrsonly' => FALSE,
      'filter' => '(objectclass=*)',
      'sizelimit' => 0,
      'timelimit' => 0,
      'deref' => NULL,
    ];

    $result = $this->ldapQuery(Server::SCOPE_BASE, $params);
    if ($result !== FALSE) {
      $entries = @ldap_get_entries($this->connection, $result);
      if ($entries !== FALSE && $entries['count'] > 0) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Count LDAP entries.
   *
   * @param resource $ldap_result
   *   The LDAP link identifier.
   *
   * @return int|bool
   *   Return false on error or number of entries, if 0 entries will return 0.
   */
  public function countEntries($ldap_result) {
    return ldap_count_entries($this->connection, $ldap_result);
  }

  /**
   * Create LDAP entry.
   *
   * @param array $attributes
   *   Should follow the structure of ldap_add functions.
   *   Entry array: http://us.php.net/manual/en/function.ldap-add.php
   *    $attributes["attribute1"] = "value";
   *    $attributes["attribute2"][0] = "value1";
   *    $attributes["attribute2"][1] = "value2";.
   * @param string $dn
   *   Used as DN if $attributes['dn'] not present.
   *
   * @return bool
   *   Result of action.
   */
  public function createLdapEntry(array $attributes, $dn = NULL) {

    $this->connectAndBindIfNotAlready();

    if (isset($attributes['dn'])) {
      $dn = $attributes['dn'];
      unset($attributes['dn']);
    }
    elseif (!$dn) {
      return FALSE;
    }
    if (!empty($attributes['unicodePwd']) && $this->get('type') == 'ad') {
      $attributes['unicodePwd'] = $this->convertPasswordForActiveDirectoryunicodePwd($attributes['unicodePwd']);
    }

    $result = @ldap_add($this->connection, $dn, $attributes);
    if (!$result) {
      ldap_get_option($this->connection, self::LDAP_OPT_DIAGNOSTIC_MESSAGE_BYTE, $ldap_additional_info);

      $this->logger->error("LDAP Server ldap_add(%dn) Error Server ID = %id, LDAP Error %ldap_error. LDAP Additional info: %ldap_additional_info", [
        '%dn' => $dn,
        '%id' => $this->id(),
        '%ldap_error' => $this->formattedError($this->ldapErrorNumber()),
        '%ldap_additional_info' => $ldap_additional_info,
      ]
      );
    }

    return $result;
  }

  /**
   * Perform an LDAP delete.
   *
   * @param string $dn
   *   DN of entry.
   *
   * @return bool
   *   Result of ldap_delete() call.
   */
  public function deleteLdapEntry($dn) {
    $this->connectAndBindIfNotAlready();

    $result = @ldap_delete($this->connection, $dn);

    if (!$result) {
      $this->logger->error("LDAP Server delete(%dn) in LdapServer::delete() Error Server ID = %id, LDAP Error %ldap_error.", [
        '%dn' => $dn,
        '%id' => $this->id(),
        '%ldap_error' => $this->formattedError($this->ldapErrorNumber()),
      ]
      );
    }
    return $result;
  }

  /**
   * Wrapper for ldap_escape().
   *
   * Helpful for unit testing without the PHP LDAP module.
   *
   * @param string $string
   *   String to escape.
   *
   * @return mixed|string
   *   Escaped string.
   */
  public static function ldapEscape($string) {
    if (function_exists('ldap_escape')) {
      return ldap_escape($string);
    }
    else {
      return str_replace(['*', '\\', '(', ')'], ['\\*', '\\\\', '\\(', '\\)'], $string);
    }
  }

  /**
   * Remove unchanged attributes from entry.
   *
   * Given 2 LDAP entries, old and new, removed unchanged values to avoid
   * security errors and incorrect date modified.
   *
   * @param array $newEntry
   *   LDAP entry in form <attribute> => <value>.
   * @param array $oldEntry
   *   LDAP entry in form <attribute> => ['count' => N, [<value>,...<value>]].
   *
   * @return array
   *   LDAP entry with no values that have NOT changed.
   */
  public static function removeUnchangedAttributes(array $newEntry, array $oldEntry) {

    foreach ($newEntry as $key => $newValue) {
      $oldValue = FALSE;
      $oldValueIsScalar = NULL;
      $keyLowercased = mb_strtolower($key);
      // TODO: Make this if loop include the actions when tests are available.
      if (isset($oldEntry[$keyLowercased])) {
        if ($oldEntry[$keyLowercased]['count'] == 1) {
          $oldValue = $oldEntry[$keyLowercased][0];
          $oldValueIsScalar = TRUE;
        }
        else {
          unset($oldEntry[$keyLowercased]['count']);
          $oldValue = $oldEntry[$keyLowercased];
          $oldValueIsScalar = FALSE;
        }
      }

      // Identical multivalued attributes.
      if (is_array($newValue) && is_array($oldValue) && count(array_diff($newValue, $oldValue)) == 0) {
        unset($newEntry[$key]);
      }
      elseif ($oldValueIsScalar && !is_array($newValue) && mb_strtolower($oldValue) == mb_strtolower($newValue)) {
        // Don't change values that aren't changing to avoid false permission
        // constraints.
        unset($newEntry[$key]);
      }
    }
    return $newEntry;
  }

  /**
   * Modify attributes of LDAP entry.
   *
   * @param string $dn
   *   DN of entry.
   * @param array $attributes
   *   Should follow the structure of ldap_add functions.
   *   Entry array: http://us.php.net/manual/en/function.ldap-add.php
   *     $attributes["attribute1"] = "value";
   *     $attributes["attribute2"][0] = "value1";
   *     $attributes["attribute2"][1] = "value2";.
   * @param bool|array $oldAttributes
   *   Existing attributes.
   *
   * @return bool
   *   Result of query.
   */
  public function modifyLdapEntry($dn, array $attributes = [], $oldAttributes = FALSE) {

    $this->connectAndBindIfNotAlready();

    if (!$oldAttributes) {
      $result = @ldap_read($this->connection, $dn, 'objectClass=*');
      if (!$result) {
        $this->logger->error("LDAP Server ldap_read(%dn) in LdapServer::modifyLdapEntry() Error Server ID = %id, LDAP Err No: %ldap_errno LDAP Err Message: %ldap_err2str ", [
          '%dn' => $dn,
          '%id' => $this->id(),
          '%ldap_errno' => ldap_errno($this->connection),
          '%ldap_err2str' => ldap_err2str(ldap_errno($this->connection)),
        ]
        );
        return FALSE;
      }

      $entries = ldap_get_entries($this->connection, $result);
      if (is_array($entries) && $entries['count'] == 1) {
        $oldAttributes = $entries[0];
      }
    }
    if (!empty($attributes['unicodePwd']) && $this->get('type') == 'ad') {
      $attributes['unicodePwd'] = $this->convertPasswordForActiveDirectoryunicodePwd($attributes['unicodePwd']);
    }

    $attributes = $this->removeUnchangedAttributes($attributes, $oldAttributes);

    foreach ($attributes as $key => $currentValue) {
      $oldValue = FALSE;
      $keyLowercased = mb_strtolower($key);
      if (isset($oldAttributes[$keyLowercased])) {
        if ($oldAttributes[$keyLowercased]['count'] == 1) {
          $oldValue = $oldAttributes[$keyLowercased][0];
        }
        else {
          unset($oldAttributes[$keyLowercased]['count']);
          $oldValue = $oldAttributes[$keyLowercased];
        }
      }

      // Remove empty attributes.
      if ($currentValue == '' && $oldValue != '') {
        unset($attributes[$key]);
        $result = @ldap_mod_del($this->connection, $dn, [$keyLowercased => $oldValue]);
        if (!$result) {
          $this->logger->error("LDAP Server ldap_mod_del(%dn) in LdapServer::modifyLdapEntry() Error Server ID = %id, LDAP Err No: %ldap_errno LDAP Err Message: %ldap_err2str ", [
            '%dn' => $dn,
            '%id' => $this->id(),
            '%ldap_errno' => ldap_errno($this->connection),
            '%ldap_err2str' => ldap_err2str(ldap_errno($this->connection)),
          ]
          );
          return FALSE;
        }
      }
      elseif (is_array($currentValue)) {
        foreach ($currentValue as $nestedKey => $nestedValue) {
          if ($nestedValue == '') {
            // Remove empty values in multivalues attributes.
            unset($attributes[$key][$nestedKey]);
          }
          else {
            $attributes[$key][$nestedKey] = $nestedValue;
          }
        }
      }
    }

    if (count($attributes) > 0) {
      $result = @ldap_modify($this->connection, $dn, $attributes);
      if (!$result) {
        $this->logger->error("LDAP Server ldap_modify(%dn) in LdapServer::modifyLdapEntry() Error Server ID = %id, LDAP Err No: %ldap_errno LDAP Err Message: %ldap_err2str ", [
          '%dn' => $dn,
          '%id' => $this->id(),
          '%ldap_errno' => ldap_errno($this->connection),
          '%ldap_err2str' => ldap_err2str(ldap_errno($this->connection)),
        ]
        );
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Perform an LDAP search on all base dns and aggregate into one result.
   *
   * @param string $filter
   *   The search filter, such as sAMAccountName=jbarclay. Attribute values
   *   (e.g. jbarclay) should be esacaped before calling.
   * @param array $attributes
   *   List of desired attributes. If omitted, we only return "dn".
   * @param int $scope
   *   Scope of the search, defaults to subtree.
   *
   * @return array|bool
   *   An array of matching entries->attributes (will have 0 elements if search
   *   returns no results), or FALSE on error on any of the base DN queries.
   */
  public function searchAllBaseDns($filter, array $attributes = [], $scope = NULL) {
    if ($scope == NULL) {
      $scope = Server::SCOPE_SUBTREE;
    }
    $allEntries = [];

    foreach ($this->getBaseDn() as $baseDn) {
      $relativeFilter = str_replace(',' . $baseDn, '', $filter);
      $entries = $this->search($baseDn, $relativeFilter, $attributes, 0, 0, 0, NULL, $scope);
      // If error in any search, return false.
      if ($entries === FALSE) {
        return FALSE;
      }
      if (count($allEntries) == 0) {
        $allEntries = $entries;
      }
      else {
        $existingCount = $allEntries['count'];
        unset($entries['count']);
        foreach ($entries as $i => $entry) {
          $allEntries[$existingCount + $i] = $entry;
        }
        $allEntries['count'] = count($allEntries);
      }
    }

    return $allEntries;
  }

  // @codingStandardsIgnoreStart
  /**
   * Perform an LDAP search.
   *
   * @param string $base_dn
   *   The search base. If NULL, we use $this->basedn. should not be esacaped.
   * @param string $filter
   *   The search filter. such as sAMAccountName=jbarclay.  attribute values
   *   (e.g. jbarclay) should be esacaped before calling.
   * @param array $attributes
   *   List of desired attributes. If omitted, we only return "dn".
   * @param int $attrsonly
   *   Attributes.
   * @param int $sizelimit
   *   Size limit.
   * @param int $timelimit
   *   Time limit.
   * @param null $deref
   *   Dereference.
   * @param int $scope
   *   Scope.
   *
   * @return array|bool
   *   An array of matching entries->attributes (will have 0
   *   elements if search returns no results),
   *   or FALSE on error.
   *
   * @remaining params mimick ldap_search() function params
   * @TODO: Remove coding standard violation.
   */
  public function search($base_dn = NULL, $filter, array $attributes = [], $attrsonly = 0, $sizelimit = 0, $timelimit = 0, $deref = NULL, $scope = NULL) {
    // @codingStandardsIgnoreEnd
    if ($scope == NULL) {
      $scope = Server::SCOPE_SUBTREE;
    }
    if ($base_dn == NULL) {
      if (count($this->getBaseDn()) == 1) {
        $base_dn = $this->getBaseDn()[0];
      }
      else {
        return FALSE;
      }
    }

    $this->detailLog->log("LDAP search call with base_dn '%base_dn'. Filter is '%filter' with attributes '%attributes'. Only attributes %attrs_only, size limit %size_limit, time limit %time_limit, dereference %deref, scope %scope.", [
      '%base_dn' => $base_dn,
      '%filter' => $filter,
      '%attributes' => is_array($attributes) ? implode(',', $attributes) : 'none',
      '%attrs_only' => $attrsonly,
      '%size_limit' => $sizelimit,
      '%time_limit' => $timelimit,
      '%deref' => $deref ? $deref : 'null',
      '%scope' => $scope ? $scope : 'null',
    ]
    );

    // When checking multiple servers, there's a chance we might not be
    // connected yet.
    $this->connectAndBindIfNotAlready();

    $ldapQueryParams = [
      'connection' => $this->connection,
      'base_dn' => $base_dn,
      'filter' => $filter,
      'attributes' => $attributes,
      'attrsonly' => $attrsonly,
      'sizelimit' => $sizelimit,
      'timelimit' => $timelimit,
      'deref' => $deref,
      'scope' => $scope,
    ];

    if ($this->get('search_pagination')) {
      $aggregated_entries = $this->pagedLdapQuery($ldapQueryParams);
      return $aggregated_entries;
    }
    else {
      $result = $this->ldapQuery($scope, $ldapQueryParams);
      if ($result && ($this->countEntries($result) !== FALSE)) {
        $entries = ldap_get_entries($this->connection, $result);
        \Drupal::moduleHandler()->alter('ldap_server_search_results', $entries, $ldapQueryParams);
        return (is_array($entries)) ? $entries : FALSE;
      }
      elseif ($this->hasError()) {
        $this->logger->notice("LDAP search error: %error. Context is base DN: %base_dn | filter: %filter| attributes: %attributes", [
          '%base_dn' => $ldapQueryParams['base_dn'],
          '%filter' => $ldapQueryParams['filter'],
          '%attributes' => json_encode($ldapQueryParams['attributes']),
          '%error' => $this->formattedError($this->ldapErrorNumber()),
        ]
        );
        return FALSE;
      }
      else {
        return FALSE;
      }
    }
  }

  /**
   * Execute a paged LDAP query and return entries as one aggregated array.
   *
   * $this->searchPageStart and $this->searchPageEnd should be set before
   * calling if a particular set of pages is desired.
   *
   * @param resource $queryParameters
   *   Parameters of form: [
   *     'base_dn' => base_dn,
   *     'filter' =>  filter,
   *     'attributes' => attributes,
   *     'attrsonly' => attrsonly,
   *     'sizelimit' => sizelimit,
   *     'timelimit' => timelimit,
   *     'deref' => deref,
   *     'scope' => scope,
   *   ]
   *   This array of parameters is primarily passed on to ldapQuery() method.
   *
   * @return array|bool
   *   Array of LDAP entries or FALSE on error.
   */
  public function pagedLdapQuery($queryParameters) {
    if (!$this->get('search_pagination')) {
      $this->logger->error('Paged LDAP query functionality called but not enabled in LDAP server configuration.');
      return FALSE;
    }

    $pageToken = '';
    $page = 0;
    $estimatedEntries = 0;
    $aggregatedEntries = [];
    $aggregatedEntriesCount = 0;
    $hasPageResults = FALSE;

    do {
      ldap_control_paged_result($this->connection, $this->get('search_page_size'), TRUE, $pageToken);
      $result = $this->ldapQuery($queryParameters['scope'], $queryParameters);

      if ($page >= $this->searchPageStart) {
        $skippedPage = FALSE;
        if ($result && ($this->countEntries($result) !== FALSE)) {
          $pageEntries = ldap_get_entries($this->connection, $result);
          unset($pageEntries['count']);
          $hasPageResults = (is_array($pageEntries) && count($pageEntries) > 0);
          $aggregatedEntries = array_merge($aggregatedEntries, $pageEntries);
          $aggregatedEntriesCount = count($aggregatedEntries);
        }
        elseif ($this->hasError()) {
          $this->logger->notice('LDAP search error: %error. Base DN: %base_dn | filter: %filter | attributes: %attributes.', [
            '%base_dn' => $queryParameters['base_dn'],
            '%filter' => $queryParameters['filter'],
            '%attributes' => json_encode($queryParameters['attributes']),
            '%error' => $this->formattedError($this->ldapErrorNumber()),
          ]
          );
          return FALSE;
        }
        else {
          return FALSE;
        }
      }
      else {
        $skippedPage = TRUE;
      }
      @ldap_control_paged_result_response($this->connection, $result, $pageToken, $estimatedEntries);
      if ($queryParameters['sizelimit'] && $this->ldapErrorNumber() == self::LDAP_SIZELIMIT_EXCEEDED) {
        // False positive error thrown. Do not set result limit error when
        // $sizelimit specified.
      }
      elseif ($this->hasError()) {
        $this->logger->error('Paged query error: %error. Base DN: %base_dn | filter: %filter | attributes: %attributes.', [
          '%error' => $this->formattedError($this->ldapErrorNumber()),
          '%base_dn' => $queryParameters['base_dn'],
          '%filter' => $queryParameters['filter'],
          '%attributes' => json_encode($queryParameters['attributes']),
          '%query' => $queryParameters['query_display'],
        ]
        );
      }

      if (isset($queryParameters['sizelimit']) && $queryParameters['sizelimit'] && $aggregatedEntriesCount >= $queryParameters['sizelimit']) {
        $discarded_entries = array_splice($aggregatedEntries, $queryParameters['sizelimit']);
        break;
      }
      // User defined pagination has run out.
      elseif ($this->searchPageEnd !== NULL && $page >= $this->searchPageEnd) {
        break;
      }
      // LDAP reference pagination has run out.
      elseif ($pageToken === NULL || $pageToken == '') {
        break;
      }
      $page++;
    } while ($skippedPage || $hasPageResults);

    $aggregatedEntries['count'] = count($aggregatedEntries);
    return $aggregatedEntries;
  }

  /**
   * Execute LDAP query and return LDAP records.
   *
   * @param int $scope
   *   Scope of search (base, subtree or one level).
   * @param array|resource $params
   *   See pagedLdapQuery() $params.
   *
   * @return resource|bool
   *   Array of LDAP entries.
   */
  public function ldapQuery($scope, array $params) {
    $result = FALSE;

    $this->connectAndBindIfNotAlready();

    switch ($scope) {
      case Server::SCOPE_SUBTREE:
        $result = @ldap_search($this->connection, $params['base_dn'], $params['filter'], $params['attributes'], $params['attrsonly'],
          $params['sizelimit'], $params['timelimit'], $params['deref']);
        if ($params['sizelimit'] && $this->ldapErrorNumber() == self::LDAP_SIZELIMIT_EXCEEDED) {
          // False positive error thrown.
          // Do not return result limit error when $sizelimit specified.
        }
        elseif ($this->hasError()) {
          $this->logger->error('ldap_search() function error. LDAP Error: %message, ldap_search() parameters: %query', [
            '%message' => $this->formattedError($this->ldapErrorNumber()),
            '%query' => isset($params['query_display']) ? $params['query_display'] : NULL,
          ]
          );
        }
        break;

      case Server::SCOPE_BASE:
        $result = @ldap_read($this->connection, $params['base_dn'], $params['filter'], $params['attributes'], $params['attrsonly'],
          $params['sizelimit'], $params['timelimit'], $params['deref']);
        if ($params['sizelimit'] && $this->ldapErrorNumber() == self::LDAP_SIZELIMIT_EXCEEDED) {
          // False positive error thrown.
          // Do not result limit error when $sizelimit specified.
        }
        elseif ($this->hasError()) {
          $this->logger->error('ldap_read() function error.  LDAP Error: %message, ldap_read() parameters: %query', [
            '%message' => $this->formattedError($this->ldapErrorNumber()),
            '%query' => @$params['query_display'],
          ]
          );
        }
        break;

      case Server::SCOPE_ONE_LEVEL:
        $result = @ldap_list($this->connection, $params['base_dn'], $params['filter'], $params['attributes'], $params['attrsonly'],
          $params['sizelimit'], $params['timelimit'], $params['deref']);
        if ($params['sizelimit'] && $this->ldapErrorNumber() == self::LDAP_SIZELIMIT_EXCEEDED) {
          // False positive error thrown.
          // Do not result limit error when $sizelimit specified.
        }
        elseif ($this->hasError()) {
          $this->logger->error('ldap_list() function error. LDAP Error: %message, ldap_list() parameters: %query', [
            '%message' => $this->formattedError($this->ldapErrorNumber()),
            '%query' => $params['query_display'],
          ]
          );
        }
        break;
    }
    return $result;
  }

  /**
   * Convert DN array to lowercase.
   *
   * @param array $dns
   *   Mixed Case.
   *
   * @return array
   *   Lower Case.
   */
  public function dnArrayToLowerCase(array $dns) {
    return array_keys(array_change_key_case(array_flip($dns), CASE_LOWER));
  }

  /**
   * Fetch base DN.
   *
   * @return array
   *   All base DN.
   */
  public function getBaseDn() {
    $baseDn = $this->get('basedn');

    if (!is_array($baseDn) && is_scalar($baseDn)) {
      $baseDn = explode("\r\n", $baseDn);
    }
    return $baseDn;
  }

  /**
   * Fetches the user account based on the persistent UID.
   *
   * @param string $puid
   *   As returned from ldap_read or other LDAP function (can be binary).
   *
   * @return bool|User
   *   The updated user or error.
   */
  public function userAccountFromPuid($puid) {

    $query = \Drupal::entityQuery('user');
    $query
      ->condition('ldap_user_puid_sid', $this->id(), '=')
      ->condition('ldap_user_puid', $puid, '=')
      ->condition('ldap_user_puid_property', $this->get('unique_persistent_attr'), '=')
      ->accessCheck(FALSE);

    $result = $query->execute();

    if (!empty($result)) {
      if (count($result) == 1) {
        return User::load(array_values($result)[0]);
      }
      else {
        $uids = implode(',', $result);
        $this->logger->error('Multiple users (uids: %uids) with same puid (puid=%puid, sid=%sid, ldap_user_puid_property=%ldap_user_puid_property)', [
          '%uids' => $uids,
          '%puid' => $puid,
          '%id' => $this->id(),
          '%ldap_user_puid_property' => $this->get('unique_persistent_attr'),
        ]
        );
        return FALSE;
      }
    }
    else {
      return FALSE;
    }

  }

  /**
   * Returns the username from the LDAP entry.
   *
   * @param array $ldap_entry
   *   The LDAP entry.
   *
   * @return string
   *   The user name.
   */
  public function userUsernameFromLdapEntry(array $ldap_entry) {

    if ($this->get('account_name_attr')) {
      $accountName = (empty($ldap_entry[$this->get('account_name_attr')][0])) ? FALSE : $ldap_entry[$this->get('account_name_attr')][0];
    }
    elseif ($this->get('user_attr')) {
      $accountName = (empty($ldap_entry[$this->get('user_attr')][0])) ? FALSE : $ldap_entry[$this->get('user_attr')][0];
    }
    else {
      $accountName = FALSE;
    }

    return $accountName;
  }

  /**
   * Returns the user's email from the LDAP entry.
   *
   * @param array $ldapEntry
   *   The LDAP entry.
   *
   * @return string|bool
   *   The user's mail value or FALSE if none present.
   */
  public function userEmailFromLdapEntry(array $ldapEntry) {

    // Not using template.
    if ($ldapEntry && $this->get('mail_attr') && isset($ldapEntry[$this->get('mail_attr')][0])) {
      $mail = isset($ldapEntry[$this->get('mail_attr')][0]) ? $ldapEntry[$this->get('mail_attr')][0] : FALSE;
      return $mail;
    }
    // Template is of form [cn]@illinois.edu.
    elseif ($ldapEntry && $this->get('mail_template')) {
      return $this->tokenProcessor->tokenReplace($ldapEntry, $this->get('mail_template'), 'ldap_entry');
    }
    else {
      return FALSE;
    }
  }

  /**
   * Fetches the persistent UID from the LDAP entry.
   *
   * @param array $ldapEntry
   *   The LDAP entry.
   *
   * @return string
   *   The user's PUID or permanent user id (within ldap), converted from
   *   binary, if applicable.
   */
  public function userPuidFromLdapEntry(array $ldapEntry) {
    if ($this->get('unique_persistent_attr') && isset($ldapEntry[mb_strtolower($this->get('unique_persistent_attr'))])) {
      $puid = $ldapEntry[mb_strtolower($this->get('unique_persistent_attr'))];
      // If its still an array...
      if (is_array($puid)) {
        $puid = $puid[0];
      }
      return ($this->get('unique_persistent_attr_binary')) ? ConversionHelper::binaryConversionToString($puid) : $puid;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Undocumented.
   *
   * TODO: Naming and scope are unclear. Restructure if possible.
   *
   * @param \Drupal\user\Entity\User|array|mixed $user
   *   User account or name.
   *
   * @return array|bool
   *   User's LDAP entry.
   *
   * @deprecated
   */
  public function userUserToExistingLdapEntry($user) {
    $userLdapEntry = FALSE;

    if (is_object($user)) {
      $userLdapEntry = $this->matchUsernameToExistingLdapEntry($user->getAccountName());
    }
    elseif (is_array($user)) {
      $userLdapEntry = $user;
    }
    elseif (is_scalar($user)) {
      // Username.
      if (strpos($user, '=') === FALSE) {
        $userLdapEntry = $this->matchUsernameToExistingLdapEntry($user);
      }
      else {
        $userLdapEntry = $this->checkDnExistsIncludeData($user, ['objectclass']);
      }
    }
    return $userLdapEntry;
  }

  /**
   * Queries LDAP server for the user.
   *
   * @param string $drupalUsername
   *   Drupal user name.
   *
   * @return array|bool
   *   An associative array representing LDAP data of a user. For example:
   *   'sid' => LDAP server id
   *   'mail' => derived from LDAP mail (not always populated).
   *   'dn'   => dn of user
   *   'attr' => single LDAP entry array in form returned from ldap_search()
   *   'dn' => dn of entry
   */
  public function matchUsernameToExistingLdapEntry($drupalUsername) {

    foreach ($this->getBaseDn() as $baseDn) {

      if (empty($baseDn)) {
        continue;
      }

      $massager = new MassageAttributes();
      $filter = '(' . $this->get('user_attr') . '=' . $massager->queryLdapAttributeValue($drupalUsername) . ')';

      $result = $this->search($baseDn, $filter);
      if (!$result || !isset($result['count']) || !$result['count']) {
        continue;
      }

      // Must find exactly one user for authentication to work.
      if ($result['count'] != 1) {
        $count = $result['count'];
        $this->logger->error('Error: %count users found with %filter under %base_dn.', [
          '%count' => $count,
          '%filter' => $filter,
          '%base_dn' => $baseDn,
        ]
          );
        continue;
      }
      $match = $result[0];
      // Fix the attribute name in case a server (i.e.: MS Active Directory) is
      // messing with the characters' case.
      $nameAttribute = $this->get('user_attr');

      if (isset($match[$nameAttribute][0])) {
        // Leave name.
      }
      elseif (isset($match[mb_strtolower($nameAttribute)][0])) {
        $nameAttribute = mb_strtolower($nameAttribute);
      }
      else {
        if ($this->get('bind_method') == 'anon_user') {
          $result = [
            'dn' => $match['dn'],
            'mail' => $this->userEmailFromLdapEntry($match),
            'attr' => $match,
            'id' => $this->id(),
          ];
          return $result;
        }
        else {
          continue;
        }
      }

      // Filter out results with spaces added before or after, which are
      // considered OK by LDAP but are no good for us. Some setups have multiple
      // $nameAttribute per entry, so we loop through all possible options.
      foreach ($match[$nameAttribute] as $value) {
        if (mb_strtolower(trim($value)) == mb_strtolower($drupalUsername)) {
          $result = [
            'dn' => $match['dn'],
            'mail' => $this->userEmailFromLdapEntry($match),
            'attr' => $match,
            'id' => $this->id(),
          ];
          return $result;
        }
      }
    }
  }

  /**
   * Is a user a member of group?
   *
   * @param string $groupDn
   *   Group DN in mixed case.
   * @param mixed $user
   *   A Drupal user entity, an LDAP entry array of a user  or a username.
   *
   * @return bool
   *   Whether the user belongs to the group.
   */
  public function groupIsMember($groupDn, $user) {
    $groupDns = $this->groupMembershipsFromUser($user);
    // While list of group dns is going to be in correct mixed case, $group_dn
    // may not since it may be derived from user entered values so make sure
    // in_array() is case insensitive.
    if (is_array($groupDns) && in_array(mb_strtolower($groupDn), $this->dnArrayToLowerCase($groupDns))) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Recurse through all child groups and add members.
   *
   * @param array $group_dn_entries
   *   Entries of LDAP group entries that are starting point. Should include at
   *   least 1 entry and must include 'objectclass'.
   * @param array $all_member_dns
   *   All member DN as an array of all groups the user is a member of. Mixed
   *   case values.
   * @param array $tested_group_dns
   *   Tested group IDs as an array array of tested group dn, cn, uid, etc.
   *   Mixed case values. Whether these value are dn, cn, uid, etc depends on
   *   what attribute members, uniquemember, memberUid contains whatever
   *   attribute is in $this->$tested_group_ids to avoid redundant recursion.
   * @param int $level
   *   Current level of recursion.
   * @param int $max_levels
   *   Maximum number of recursion levels allowed.
   * @param bool|array $object_classes
   *   You can set the object class evaluated for recursion here, otherwise
   *   derived from group configuration.
   *
   * @return bool
   *   If operation was successful.
   */
  public function groupMembersRecursive(array $group_dn_entries, array &$all_member_dns, array $tested_group_dns, $level, $max_levels, $object_classes = FALSE) {

    if (!$this->groupGroupEntryMembershipsConfigured() || !is_array($group_dn_entries)) {
      return FALSE;
    }
    if (isset($group_dn_entries['count'])) {
      unset($group_dn_entries['count']);
    }

    foreach ($group_dn_entries as $member_entry) {
      // 1.  Add entry itself if of the correct type to $all_member_dns.
      $object_class_match = (!$object_classes || (count(array_intersect(array_values($member_entry['objectclass']), $object_classes)) > 0));
      $object_is_group = in_array($this->groupObjectClass(), array_map('strtolower', array_values($member_entry['objectclass'])));
      // Add member.
      if ($object_class_match && !in_array($member_entry['dn'], $all_member_dns)) {
        $all_member_dns[] = $member_entry['dn'];
      }

      // 2. If its a group, keep recurse the group for descendants.
      if ($object_is_group && $level < $max_levels) {
        if ($this->groupMembershipsAttrMatchingUserAttr() == 'dn') {
          $group_id = $member_entry['dn'];
        }
        else {
          $group_id = $member_entry[$this->groupMembershipsAttrMatchingUserAttr()][0];
        }
        // 3. skip any groups that have already been tested.
        if (!in_array($group_id, $tested_group_dns)) {
          $tested_group_dns[] = $group_id;
          $member_ids = $member_entry[$this->groupMembershipsAttr()];
          if (isset($member_ids['count'])) {
            unset($member_ids['count']);
          }

          if (count($member_ids)) {
            // Example 1: (|(cn=group1)(cn=group2))
            // Example 2: (|(dn=cn=group1,ou=blah...)(dn=cn=group2,ou=blah...))
            $query_for_child_members = '(|(' . implode(")(", $member_ids) . '))';
            // Add or on object classes, otherwise get all object classes.
            if ($object_classes && count($object_classes)) {
              $object_classes_ors = ['(objectClass=' . $this->groupObjectClass() . ')'];
              foreach ($object_classes as $object_class) {
                $object_classes_ors[] = '(objectClass=' . $object_class . ')';
              }
              $query_for_child_members = '&(|' . implode($object_classes_ors) . ')(' . $query_for_child_members . ')';
            }

            $return_attributes = [
              'objectclass',
              $this->groupMembershipsAttr(),
              $this->groupMembershipsAttrMatchingUserAttr(),
            ];
            $child_member_entries = $this->searchAllBaseDns($query_for_child_members, $return_attributes);
            if ($child_member_entries !== FALSE) {
              $this->groupMembersRecursive($child_member_entries, $all_member_dns, $tested_group_dns, $level + 1, $max_levels, $object_classes);
            }
          }
        }
      }
    }
  }

  /**
   * Get list of all groups that a user is a member of.
   *
   * If nesting is configured, the list will include all parent groups. For
   * example, if the user is a member of the "programmer" group and the
   * "programmer" group is a member of the "it" group, the user is a member of
   * both the "programmer" and the "it" group. If $nested is FALSE, the list
   * will only include groups which are directly assigned to the user.
   *
   * @param mixed $user
   *   A Drupal user entity, an LDAP entry array of a user  or a username.
   *
   * @return array|false
   *   Array of group dns in mixed case or FALSE on error.
   *
   * @TODO: Make the user type argument consistent or split the function.
   */
  public function groupMembershipsFromUser($user) {

    $group_dns = FALSE;
    $user_ldap_entry = @$this->userUserToExistingLdapEntry($user);
    if (!$user_ldap_entry || $this->groupFunctionalityUnused()) {
      return FALSE;
    }

    // Preferred method.
    if ($this->groupUserMembershipsFromAttributeConfigured()) {
      $group_dns = $this->groupUserMembershipsFromUserAttr($user_ldap_entry);
    }
    elseif ($this->groupGroupEntryMembershipsConfigured()) {
      $group_dns = $this->groupUserMembershipsFromEntry($user_ldap_entry);
    }
    return $group_dns;
  }

  /**
   * Get list of groups that a user is a member of using the memberOf attribute.
   *
   * @param mixed $user
   *   A Drupal user entity, an LDAP entry array of a user  or a username.
   *
   * @return array|false
   *   Array of group dns in mixed case or FALSE on error.
   *
   * @see groupMembershipsFromUser()
   */
  public function groupUserMembershipsFromUserAttr($user) {
    if (!$this->groupUserMembershipsFromAttributeConfigured()) {
      return FALSE;
    }

    $groupAttribute = $this->groupUserMembershipsAttr();

    // If Drupal user passed in, try to get user_ldap_entry.
    if (empty($user['attr'][$groupAttribute])) {
      $user = $this->userUserToExistingLdapEntry($user);
      if (empty($user['attr'][$groupAttribute])) {
        // User's membership attribute is not present. Either misconfigured or
        // the query failed.
        return FALSE;
      }
    }
    // If not exited yet, $user must be $userLdapEntry.
    $userLdapEntry = $user;
    $allGroupDns = [];
    $level = 0;

    $membersGroupDns = $userLdapEntry['attr'][$groupAttribute];
    if (isset($membersGroupDns['count'])) {
      unset($membersGroupDns['count']);
    }
    $orFilters = [];
    foreach ($membersGroupDns as $memberGroupDn) {
      $allGroupDns[] = $memberGroupDn;
      if ($this->groupNested()) {
        if ($this->groupMembershipsAttrMatchingUserAttr() == 'dn') {
          $member_value = $memberGroupDn;
        }
        else {
          $member_value = $this->getFirstRdnValueFromDn($memberGroupDn, $this->groupMembershipsAttrMatchingUserAttr());
        }
        $orFilters[] = $this->groupMembershipsAttr() . '=' . self::ldapEscape($member_value);
      }
    }

    if ($this->groupNested() && count($orFilters)) {
      $allGroupDns = $this->getNestedGroupDnFilters($allGroupDns, $orFilters, $level);
    }

    return $allGroupDns;
  }

  /**
   * Get list of all groups that a user is a member of by querying groups.
   *
   * @param mixed $user
   *   A Drupal user entity, an LDAP entry array of a user or a username.
   *
   * @return array|false
   *   Array of group dns in mixed case or FALSE on error.
   *
   * @see groupMembershipsFromUser()
   */
  public function groupUserMembershipsFromEntry($user) {
    if (!$this->groupGroupEntryMembershipsConfigured()) {
      return FALSE;
    }

    $userLdapEntry = $this->userUserToExistingLdapEntry($user);

    // MIXED CASE VALUES.
    $allGroupDns = [];
    // Array of dns already tested to avoid excess queries MIXED CASE VALUES.
    $testedGroupIds = [];
    $level = 0;

    if ($this->groupMembershipsAttrMatchingUserAttr() == 'dn') {
      $member_value = $userLdapEntry['dn'];
    }
    else {
      $member_value = $userLdapEntry['attr'][$this->groupMembershipsAttrMatchingUserAttr()][0];
    }

    $groupQuery = '(&(objectClass=' . $this->groupObjectClass() . ')(' . $this->groupMembershipsAttr() . "=$member_value))";

    // Need to search on all basedns one at a time.
    foreach ($this->getBaseDn() as $baseDn) {
      // Only need dn, so empty array forces return of no attributes.
      $groupEntries = $this->search($baseDn, $groupQuery, []);
      if ($groupEntries !== FALSE) {
        $maxLevels = $this->groupNested() ? self::LDAP_SERVER_LDAP_QUERY_RECURSION_LIMIT : 0;
        $this->groupMembershipsFromEntryRecursive($groupEntries, $allGroupDns, $testedGroupIds, $level, $maxLevels);
      }
    }

    return $allGroupDns;
  }

  /**
   * Recurse through all groups, adding parent groups to $all_group_dns array.
   *
   * @param array $currentGroupEntries
   *   Entries of LDAP groups, which are that are starting point. Should include
   *   at least one entry.
   * @param array $allGroupDns
   *   An array of all groups the user is a member of in mixed-case.
   * @param array $testedGroupIds
   *   An array of tested group DN, CN, UID, etc. in mixed-case. Whether these
   *   value are DN, CN, UID, etc. depends on what attribute members,
   *   uniquemember, or memberUid contains whatever attribute in
   *   $this->$tested_group_ids to avoid redundant recursion.
   * @param int $level
   *   Levels of recursion.
   * @param int $maxLevels
   *   Maximum levels of recursion allowed.
   *
   * @return bool
   *   False for error or misconfiguration, otherwise TRUE. Results are passed
   *   by reference.
   *
   * @TODO: See if we can do this with groupAllMembers().
   */
  private function groupMembershipsFromEntryRecursive(array $currentGroupEntries, array &$allGroupDns, array &$testedGroupIds, $level, $maxLevels) {

    if (!$this->groupGroupEntryMembershipsConfigured() || !is_array($currentGroupEntries) || count($currentGroupEntries) == 0) {
      return FALSE;
    }
    if (isset($currentGroupEntries['count'])) {
      unset($currentGroupEntries['count']);
    }

    $orFilters = [];
    foreach ($currentGroupEntries as $key => $groupEntry) {
      if ($this->groupMembershipsAttrMatchingUserAttr() == 'dn') {
        $memberId = $groupEntry['dn'];
      }
      // Maybe cn, uid, etc is held.
      else {
        $memberId = $this->getFirstRdnValueFromDn($groupEntry['dn'], $this->groupMembershipsAttrMatchingUserAttr());
      }

      if ($memberId && !in_array($memberId, $testedGroupIds)) {
        $testedGroupIds[] = $memberId;
        $allGroupDns[] = $groupEntry['dn'];
        // Add $group_id (dn, cn, uid) to query.
        $orFilters[] = $this->groupMembershipsAttr() . '=' . self::ldapEscape($memberId);
      }
    }

    if (count($orFilters)) {
      // Only 50 or so per query.
      for ($key = 0; $key < count($orFilters); $key = $key + self::LDAP_SERVER_LDAP_QUERY_CHUNK) {
        $currentOrFilters = array_slice($orFilters, $key, self::LDAP_SERVER_LDAP_QUERY_CHUNK);
        // Example 1: (|(cn=group1)(cn=group2))
        // Example 2: (|(dn=cn=group1,ou=blah...)(dn=cn=group2,ou=blah...))
        $or = '(|(' . implode(")(", $currentOrFilters) . '))';
        $queryForParentGroups = '(&(objectClass=' . $this->groupObjectClass() . ')' . $or . ')';

        // Need to search on all basedns one at a time.
        foreach ($this->getBaseDn() as $baseDn) {
          // No attributes, just dns needed.
          $group_entries = $this->search($baseDn, $queryForParentGroups);
          if ($group_entries !== FALSE && $level < $maxLevels) {
            // @TODO: Verify recursion with true return.
            $this->groupMembershipsFromEntryRecursive($group_entries, $allGroupDns, $testedGroupIds, $level + 1, $maxLevels);
          }
        }
      }
    }
    return TRUE;
  }

  /**
   * Get "groups" from derived from DN.  Has limited usefulness.
   *
   * @param mixed $user
   *   A Drupal user entity, an LDAP entry array of a user or a username.
   *
   * @return array|bool
   *   Array of group strings.
   */
  public function groupUserMembershipsFromDn($user) {

    if (!$this->groupDeriveFromDn() || !$this->groupDeriveFromDnAttr()) {
      return FALSE;
    }
    elseif ($user_ldap_entry = $this->userUserToExistingLdapEntry($user)) {
      return $this->getAllRdnValuesFromDn($user_ldap_entry['dn'], $this->groupDeriveFromDnAttr());
    }
    else {
      return FALSE;
    }

  }

  /**
   * Does the LDAP query return an error.
   *
   * @return bool
   *   Error state.
   */
  public function hasError() {
    if ($this->ldapErrorNumber() != Server::LDAP_SUCCESS) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Returns a string for the error to show administrators and in logs.
   *
   * @param int $number
   *   The LDAP error number.
   *
   * @return string
   *   Human readable string with error number.
   */
  public function formattedError($number) {
    return ldap_err2str($number) . ' (' . $number . ')';
  }

  /**
   * Returns the raw LDAP error code.
   */
  public function ldapErrorNumber() {
    return ldap_errno($this->connection);
  }

  /**
   * Returns whether groups are in use.
   */
  protected function groupFunctionalityUnused() {
    return $this->get('grp_unused');
  }

  /**
   * Returns whether groups are nested.
   */
  protected function groupNested() {
    return $this->get('grp_nested');
  }

  /**
   * Returns entity configuration value.
   */
  protected function groupUserMembershipsAttrExists() {
    return $this->get('grp_user_memb_attr_exists');
  }

  /**
   * Returns entity configuration value.
   */
  protected function groupUserMembershipsAttr() {
    return $this->get('grp_user_memb_attr');
  }

  /**
   * Returns entity configuration value.
   */
  protected function groupMembershipsAttrMatchingUserAttr() {
    return $this->get('grp_memb_attr_match_user_attr');
  }

  /**
   * Returns entity configuration value.
   */
  public function groupMembershipsAttr() {
    return $this->get('grp_memb_attr');
  }

  /**
   * Returns entity configuration value.
   */
  public function groupObjectClass() {
    return $this->get('grp_object_cat');
  }

  /**
   * Returns entity configuration value.
   */
  protected function groupDeriveFromDn() {
    return $this->get('grp_derive_from_dn');
  }

  /**
   * Returns entity configuration value.
   */
  protected function groupDeriveFromDnAttr() {
    return $this->get('grp_derive_from_dn_attr');
  }

  /**
   * Check if group memberships from attribute are configured.
   *
   * @return bool
   *   Whether group user memberships are configured.
   */
  public function groupUserMembershipsFromAttributeConfigured() {
    return $this->groupUserMembershipsAttrExists() && $this->groupUserMembershipsAttr();
  }

  /**
   * Check if group memberships from group entry are configured.
   *
   * @return bool
   *   Whether group memberships from group entry are configured.
   */
  public function groupGroupEntryMembershipsConfigured() {
    return $this->groupMembershipsAttrMatchingUserAttr() && $this->groupMembershipsAttr();
  }

  /**
   * Return the first RDN Value from DN.
   *
   * Given a DN (such as cn=jdoe,ou=people) and an RDN (such as cn),
   * determine that RND value (such as jdoe).
   *
   * @param string $dn
   *   Input DN.
   * @param string $rdn
   *   RDN Value to find.
   *
   * @return string
   *   Value of RDN.
   */
  private function getFirstRdnValueFromDn($dn, $rdn) {
    // Escapes attribute values, need to be unescaped later.
    $pairs = $this->ldapExplodeDn($dn, 0);
    array_shift($pairs);
    $rdn = mb_strtolower($rdn);
    $rdn_value = FALSE;
    foreach ($pairs as $p) {
      $pair = explode('=', $p);
      if (mb_strtolower(trim($pair[0])) == $rdn) {
        $rdn_value = ConversionHelper::unescapeDnValue(trim($pair[1]));
        break;
      }
    }
    return $rdn_value;
  }

  /**
   * Returns all RDN values from DN.
   *
   * Given a DN (such as cn=jdoe,ou=people) and an rdn (such as cn),
   * determine that RDN value (such as jdoe).
   *
   * @param string $dn
   *   Input DN.
   * @param string $rdn
   *   RDN Value to find.
   *
   * @return array
   *   All values of RDN.
   */
  private function getAllRdnValuesFromDn($dn, $rdn) {
    // Escapes attribute values, need to be unescaped later.
    $pairs = $this->ldapExplodeDn($dn, 0);
    array_shift($pairs);
    $rdn = mb_strtolower($rdn);
    $rdn_values = [];
    foreach ($pairs as $p) {
      $pair = explode('=', $p);
      if (mb_strtolower(trim($pair[0])) == $rdn) {
        $rdn_values[] = ConversionHelper::unescapeDnValue(trim($pair[1]));
        break;
      }
    }
    return $rdn_values;
  }

  /**
   * Wrapper for ldap_explode_dn().
   *
   * Helpful for unit testing without the PHP LDAP module.
   *
   * @param string $dn
   *   DN to explode.
   * @param int $attribute
   *   Attribute.
   *
   * @return array
   *   Exploded DN.
   */
  public static function ldapExplodeDn($dn, $attribute) {
    return ldap_explode_dn($dn, $attribute);
  }

  /**
   * Convert password to format required by Active Directory.
   *
   * For the purpose of changing or setting the password. Note that AD needs the
   * field to be called unicodePwd (as opposed to userPassword).
   *
   * @param string $password
   *   The password that is being formatted for Active Directory unicodePwd
   *   field.
   *
   * @return string
   *   $password surrounded with quotes and in UTF-16LE encoding
   */
  public function convertPasswordForActiveDirectoryunicodePwd($password) {
    // This function can be called with $attributes['unicodePwd'] as an array.
    if (!is_array($password)) {
      return mb_convert_encoding("\"{$password}\"", "UTF-16LE");
    }
    else {
      // Presumably there is no use case for there being more than one password
      // in the $attributes array, hence it will be at index 0 and we return in
      // kind.
      return [mb_convert_encoding("\"{$password[0]}\"", "UTF-16LE")];
    }
  }

  /**
   * Search within the nested groups for further filters.
   *
   * @param array $allGroupDns
   *   Currently set groups.
   * @param array $orFilters
   *   Filters before diving deeper.
   * @param int $level
   *   Last relevant nesting leven.
   *
   * @return array
   *   Nested group filters.
   */
  private function getNestedGroupDnFilters(array $allGroupDns, array $orFilters, $level) {
    // Only 50 or so per query.
    for ($key = 0; $key < count($orFilters); $key = $key + self::LDAP_SERVER_LDAP_QUERY_CHUNK) {
      $currentOrFilters = array_slice($orFilters, $key, self::LDAP_SERVER_LDAP_QUERY_CHUNK);
      // Example 1: (|(cn=group1)(cn=group2))
      // Example 2: (|(dn=cn=group1,ou=blah...)(dn=cn=group2,ou=blah...))
      $orFilter = '(|(' . implode(")(", $currentOrFilters) . '))';
      $queryForParentGroups = '(&(objectClass=' . $this->groupObjectClass() . ')' . $orFilter . ')';

      // Need to search on all base DN one at a time.
      foreach ($this->getBaseDn() as $basedn) {
        // No attributes, just dns needed.
        $groupEntries = $this->search($basedn, $queryForParentGroups);
        if ($groupEntries !== FALSE && $level < self::LDAP_SERVER_LDAP_QUERY_RECURSION_LIMIT) {
          $testedGroupIds = [];
          $this->groupMembershipsFromEntryRecursive($groupEntries, $allGroupDns, $testedGroupIds, $level + 1, self::LDAP_SERVER_LDAP_QUERY_RECURSION_LIMIT);
        }
      }
    }
    return $allGroupDns;
  }

  /**
   * Add a group entry.
   *
   * Functionality is not in use, only called by server test form.
   *
   * @param string $group_dn
   *   The group DN as an LDAP DN.
   * @param array $attributes
   *   Attributes in key value form
   *    $attributes = array(
   *      "attribute1" = "value",
   *      "attribute2" = array("value1", "value2"),
   *      )
   *
   * @return bool
   *   Operation result.
   */
  public function groupAddGroup($group_dn, array $attributes = []) {

    if ($this->checkDnExists($group_dn)) {
      return FALSE;
    }

    $attributes = array_change_key_case($attributes, CASE_LOWER);
    if (empty($attributes['objectclass'])) {
      $objectClass = $this->groupObjectClass();
    }
    else {
      $objectClass = $attributes['objectclass'];
    }
    $attributes['objectclass'] = $objectClass;

    $context = [
      'action' => 'add',
      'corresponding_drupal_data' => [$group_dn => $attributes],
      'corresponding_drupal_data_type' => 'group',
    ];
    $ldap_entries = [$group_dn => $attributes];
    $this->moduleHandler->alter('ldap_entry_pre_provision', $ldap_entries, $this, $context);
    $attributes = $ldap_entries[$group_dn];

    $ldap_entry_created = $this->createLdapEntry($attributes, $group_dn);

    if ($ldap_entry_created) {
      $this->moduleHandler->invokeAll('ldap_entry_post_provision', [
        $ldap_entries,
        $this,
        $context,
      ]
      );
      return TRUE;
    }
    else {
      return FALSE;
    }

  }

  /**
   * Remove a group entry.
   *
   * Functionality is not in use, only called by server test form.
   *
   * @param string $group_dn
   *   Group DN as LDAP dn.
   * @param bool $only_if_group_empty
   *   TRUE = group should not be removed if not empty
   *   FALSE = groups should be deleted regardless of members.
   *
   * @return bool
   *   Removal result.
   */
  public function groupRemoveGroup($group_dn, $only_if_group_empty = TRUE) {

    if ($only_if_group_empty) {
      $members = $this->groupAllMembers($group_dn);
      if (is_array($members) && count($members) > 0) {
        return FALSE;
      }
    }
    return $this->deleteLdapEntry($group_dn);

  }

  /**
   * Add a member to a group.
   *
   * Functionality only called by server test form.
   *
   * @param string $group_dn
   *   LDAP group DN.
   * @param string $user
   *   LDAP user DN.
   *
   * @return bool
   *   Operation successful.
   */
  public function groupAddMember($group_dn, $user) {
    $result = FALSE;
    if ($this->groupGroupEntryMembershipsConfigured()) {
      $this->connectAndBindIfNotAlready();
      $new_member = [$this->groupMembershipsAttr() => $user];
      $result = @ldap_mod_add($this->connection, $group_dn, $new_member);
    }

    return $result;
  }

  /**
   * Remove a member from a group.
   *
   * Functionality only called by server test form.
   *
   * @param string $group_dn
   *   LDAP DN group.
   * @param string $member
   *   LDAP DN member.
   *
   * @return bool
   *   Operation successful.
   */
  public function groupRemoveMember($group_dn, $member) {
    $result = FALSE;
    if ($this->groupGroupEntryMembershipsConfigured()) {
      $del = [];
      $del[$this->groupMembershipsAttr()] = $member;
      $this->connectAndBindIfNotAlready();
      $result = @ldap_mod_del($this->connection, $group_dn, $del);
    }
    return $result;
  }

  /**
   * Get all members of a group.
   *
   * Currently not in use.
   *
   * @param string $group_dn
   *   Group DN as LDAP DN.
   *
   * @return bool|array
   *   FALSE on error, otherwise array of group members (could be users or
   *   groups).
   *
   * @TODO: Split return functionality or throw an error.
   */
  public function groupAllMembers($group_dn) {

    if (!$this->groupGroupEntryMembershipsConfigured()) {
      return FALSE;
    }

    $attributes = [$this->groupMembershipsAttr(), 'cn', 'objectclass'];
    $group_entry = $this->checkDnExistsIncludeData($group_dn, $attributes);
    if (!$group_entry) {
      return FALSE;
    }
    else {
      // If attributes weren't returned, don't give false  empty group.
      if (empty($group_entry['cn'])) {
        return FALSE;
      }
      if (empty($group_entry[$this->groupMembershipsAttr()])) {
        // If no attribute returned, no members.
        return [];
      }
      $members = $group_entry[$this->groupMembershipsAttr()];
      if (isset($members['count'])) {
        unset($members['count']);
      }
      $result = $this->groupMembersRecursive([$group_entry], $members, [], 0, self::LDAP_SERVER_LDAP_QUERY_RECURSION_LIMIT);
      // Remove the DN of the source group.
      if (($key = array_search($group_dn, $members)) !== FALSE) {
        unset($members[$key]);
      }
    }

    if ($result !== FALSE) {
      return $members;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Get direct members of a group.
   *
   * Currently not in use.
   *
   * @param string $group_dn
   *   Group DN as LDAP DN.
   *
   * @return bool|array
   *   FALSE on error, otherwise array of group members (could be users or
   *   groups).
   *
   * @TODO: Split return functionality or throw an error.
   */
  public function groupMembers($group_dn) {

    if (!$this->groupGroupEntryMembershipsConfigured()) {
      return FALSE;
    }

    $attributes = [$this->groupMembershipsAttr(), 'cn', 'objectclass'];
    $group_entry = $this->checkDnExistsIncludeData($group_dn, $attributes);
    if (!$group_entry) {
      return FALSE;
    }
    else {
      // If attributes weren't returned, don't give false  empty group.
      if (empty($group_entry['cn'])) {
        return FALSE;
      }
      if (empty($group_entry[$this->groupMembershipsAttr()])) {
        // If no attribute returned, no members.
        return [];
      }
      $members = $group_entry[$this->groupMembershipsAttr()];
      if (isset($members['count'])) {
        unset($members['count']);
      }
      return $members;
    }
  }

}
