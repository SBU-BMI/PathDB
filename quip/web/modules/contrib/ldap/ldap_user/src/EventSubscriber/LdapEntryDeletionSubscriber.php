<?php

declare(strict_types = 1);

namespace Drupal\ldap_user\EventSubscriber;

use Drupal\Core\Config\ConfigFactory;
use Drupal\ldap_servers\LdapUserAttributesInterface;
use Drupal\ldap_servers\LdapUserManager;
use Drupal\ldap_user\Event\LdapUserDeletedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Delete LDAP entry.
 */
class LdapEntryDeletionSubscriber implements EventSubscriberInterface, LdapUserAttributesInterface {

  /**
   * Config.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * LDAP User Manager.
   *
   * @var \Drupal\ldap_servers\LdapUserManager
   */
  protected $ldapUserManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   Config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   * @param \Drupal\ldap_servers\LdapUserManager $ldap_user_manager
   *   LDAP user manager.
   */
  public function __construct(
    ConfigFactory $config_factory,
    LoggerInterface $logger,
    LdapUserManager $ldap_user_manager
  ) {
    $this->config = $config_factory->get('ldap_user.settings');
    $this->logger = $logger;
    $this->ldapUserManager = $ldap_user_manager;
  }

  /**
   * {@inheritdoc}
   *
   * @uses LdapEntryDeletionSubscriber::deleteProvisionedLdapEntry()
   */
  public static function getSubscribedEvents(): array {
    $events[LdapUserDeletedEvent::EVENT_NAME] = ['deleteProvisionedLdapEntry'];
    return $events;
  }

  /**
   * Delete a provisioned LDAP entry.
   *
   * Given a Drupal account, delete LDAP entry that was provisioned based on it.
   * This is usually none or one entry but the ldap_user_prov_entries field
   * supports multiple, and thus we are looping through them.
   *
   * @param \Drupal\ldap_user\Event\LdapUserDeletedEvent $event
   *   Event.
   */
  public function deleteProvisionedLdapEntry(LdapUserDeletedEvent $event): void {
    if (
      $this->config->get('ldapEntryProvisionServer') &&
      \in_array(self::PROVISION_LDAP_ENTRY_ON_USER_ON_USER_DELETE, $this->config->get('ldapEntryProvisionTriggers'), TRUE)
    ) {
      /** @var \Drupal\user\Entity\User $account */
      $account = $event->account;
      // Determine server that is associated with user.
      $entries = $account->get('ldap_user_prov_entries')->getValue();
      foreach ($entries as $entry) {
        $parts = explode('|', $entry['value']);
        if (count($parts) === 2) {
          [$sid, $dn] = $parts;
          $tokens = [
            '%sid' => $sid,
            '%dn' => $dn,
            '%username' => $account->getAccountName(),
            '%uid' => $account->id(),
          ];
          if ($this->ldapUserManager->setServerById($sid) && $dn) {
            if ($this->ldapUserManager->deleteLdapEntry($dn)) {
              $this->logger->info('LDAP entry on server %sid deleted dn=%dn. username=%username, uid=%uid', $tokens);
            }
          }
          else {
            $this->logger->warning("LDAP server %sid not available, cannot delete record '%dn.'", $tokens);
          }
        }
      }
    }
  }

}
