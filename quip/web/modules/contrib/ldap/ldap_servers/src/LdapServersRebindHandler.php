<?php

namespace Drupal\ldap_servers;

/**
 * Class for enabling rebind functionality for following referrals.
 *
 * @FIXME: Unused class.
 */
class LdapServersRebindHandler {

  private $bindDn = 'Anonymous';
  private $bindPassword = '';

  /**
   * Constructor.
   *
   * @param string $bind_user_dn
   *   Bind user.
   * @param string $bind_user_passwd
   *   Bind password.
   */
  public function __construct($bind_user_dn, $bind_user_passwd) {
    $this->bindDn = $bind_user_dn;
    $this->bindPassword = $bind_user_passwd;
  }

  /**
   * The rebinding callback.
   *
   * @param mixed $ldap
   *   Unknown.
   * @param mixed $referral
   *   Unknown.
   *
   * @return int
   *   Returns int instead of boolean? Weird.
   */
  public function rebindCallback($ldap, $referral) {
    // LDAP options.
    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 1);
    ldap_set_rebind_proc($ldap, [$this, 'rebind_callback']);

    // Bind to new host, assumes initial bind dn has access to the referred
    // servers.
    if (!ldap_bind($ldap, $this->bindDn, $this->bindPassword)) {
      echo "Could not bind to referral server: $referral";
      return 1;
    }
    return 0;
  }

}
