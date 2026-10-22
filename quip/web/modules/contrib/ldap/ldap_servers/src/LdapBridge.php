<?php

declare(strict_types=1);

namespace Drupal\ldap_servers;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ldap_servers\Entity\Server;
use Drupal\ldap_servers\Helper\CredentialsStorage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Ldap\LdapInterface;

/**
 * Ldap Bridge to symfony/ldap.
 */
class LdapBridge implements LdapBridgeInterface {

  /**
   * Bind method.
   *
   * @var string
   */
  protected $bindMethod;

  /**
   * Bind DN.
   *
   * @var string|null
   */
  protected $bindDn;

  /**
   * Bind PW.
   *
   * @var string|null
   */
  protected $bindPw;

  /**
   * LDAP.
   *
   * @var \Symfony\Component\Ldap\Ldap
   */
  protected $ldap;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   */
  public function __construct(
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->logger            = $logger;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function setServerById(string $sid): void {
    $server = $this->entityTypeManager->getStorage('ldap_server')->load($sid);
    /** @var \Drupal\ldap_servers\Entity\Server $server */
    if ($server) {
      $this->setServer($server);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setServer(Server $server): void {
    $parameters = [
      'host' => $server->get('address'),
      'port' => $server->get('port'),
      'encryption' => $server->get('encryption'),
      'options' => [
        'timeout' => $server->getTimeout(),
        'network_timeout' => $server->getTimeout(),
      ],
    ];

    $this->bindMethod = $server->get('bind_method');
    $this->bindDn = $server->get('binddn');
    $this->bindPw = $server->get('bindpw');
    // @todo Make this cacheable.
    $this->ldap = Ldap::create('ext_ldap', $parameters);
  }

  /**
   * {@inheritdoc}
   */
  public function bind(): bool {
    if (
      $this->bindMethod === 'anon' ||
      ($this->bindMethod === 'anon_user' && !CredentialsStorage::validateCredentials())
    ) {
      $userDn = NULL;
      $password = NULL;
    }
    else {
      // Default credentials form service account.
      $userDn = $this->bindDn;
      $password = $this->bindPw;

      // Runtime credentials for user binding and password checking.
      if (CredentialsStorage::validateCredentials()) {
        $userDn = CredentialsStorage::getUserDn();
        $password = CredentialsStorage::getPassword();
      }

      if (empty($password) || empty($userDn)) {
        $this->logger->notice('LDAP bind failure due to missing credentials for user userdn=%userdn', [
          '%userdn' => $userDn,
        ]);
        return FALSE;
      }
    }

    $existing_binds = &drupal_static(__FUNCTION__);
    if (!empty($existing_binds)) {
      foreach ($existing_binds as $result) {
        if (isset(
          $result['bindMethod'],
          $result['bindDn'],
          $result['bindPw'],
          $result['result'],
          $result['ldap']
        )) {
          if (
            $result['bindMethod'] === $this->bindMethod
            && $result['bindDn'] === $userDn
            && $result['bindPw'] === $password
          ) {
            $this->ldap = $result['ldap'];
            return $result['result'];
          }
        }
      }
    }

    $existing_binds[] = &$existing_bind;
    $existing_bind = [
      'bindMethod' => $this->bindMethod,
      'bindDn' => $userDn,
      'bindPw' => $password,
      'result' => FALSE,
      'ldap' => $this->ldap,
    ];

    try {
      $this->ldap->bind($userDn, $password);
    }
    catch (ConnectionException $e) {
      $this->logger->notice('LDAP connection failure: %message.', [
        '%message' => $e->getMessage(),
      ]);
      return FALSE;
    }
    catch (LdapException $e) {
      $this->logger->notice('LDAP bind failure: %message.', [
        '%message' => $e->getMessage(),
      ]);
      return FALSE;
    }
    $existing_bind['result'] = TRUE;
    return TRUE;
  }

  /**
   * Get LDAP service.
   *
   * @return \Symfony\Component\Ldap\LdapInterface
   *   LDAP service.
   */
  public function get(): LdapInterface {
    return $this->ldap;
  }

}
