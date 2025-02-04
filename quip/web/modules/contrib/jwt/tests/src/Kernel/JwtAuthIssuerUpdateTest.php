<?php

namespace Drupal\Tests\jwt\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests JWT Auth issuer update.
 *
 * @group JWT
 */
class JwtAuthIssuerUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['key', 'jwt', 'jwt_auth_issuer'];

  /**
   * Verify the update function creates the configuration.
   */
  public function testUpdate() {
    $this->container->get('module_handler')->loadInclude('jwt_auth_issuer', 'install');
    $this->assertTrue($this->config('jwt_auth_issuer.config')->isNew(), 'Configuration does not exist prior to calling jwt_auth_issuer_update_20001()');

    // Invoke the update function that creates the configuration.
    jwt_auth_issuer_update_20001();

    $config = $this->config('jwt_auth_issuer.config');
    $this->assertFalse($config->isNew(), "Configuration created by jwt_auth_issuer_update_20001()");
    $this->assertFalse($config->get('jwt_in_login_response'), 'Configuration set to the expected initial value');
  }

}
