<?php

namespace Drupal\simplesamlphp_auth_test;

use Drupal\simplesamlphp_auth\Service\SimplesamlphpAuthManager;

/**
 * Mock SimplesamlphpAuthManager class for testing purposes.
 */
class SimplesamlphpAuthTestManager extends SimplesamlphpAuthManager {

  /**
   * Keeps track of whether the user is authenticated.
   *
   * @var bool
   */
  protected $authenticated = FALSE;

  /**
   * {@inheritdoc}
   */
  public function externalAuthenticate() {
    $this->authenticated = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getStorage() {
    return 'sql';
  }

  /**
   * {@inheritdoc}
   */
  public function isAuthenticated() {
    return $this->authenticated;
  }

  /**
   * {@inheritdoc}
   */
  public function getAttributes() {
    return [
      'uid' => [0 => 'saml_user'],
      'displayName' => [0 => 'Test Saml User'],
      'mail' => [0 => 'saml@example.com'],
      'roles' => [0 => ['employee', 'test_role']],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function logout($redirect_path = NULL) {
    $this->authenticated = FALSE;
    return FALSE;
  }

}
