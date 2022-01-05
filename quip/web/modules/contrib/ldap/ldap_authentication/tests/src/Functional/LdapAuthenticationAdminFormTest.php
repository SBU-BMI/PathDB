<?php

declare(strict_types = 1);

namespace Drupal\Tests\ldap_authentication\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test the admin form.
 *
 * @group ldap
 */
class LdapAuthenticationAdminFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  protected const FORM_PATH = '/admin/config/people/ldap/authentication';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'ldap_authentication',
    'ldap_servers',
    'externalauth',
    'ldap_user',
    'ldap_query',
  ];

  /**
   * Sets up the test.
   */
  protected function setUp(): void {
    parent::setUp();

    $manager = $this->container->get('entity_type.manager');
    $server = $manager->getStorage('ldap_server')->create([
      'id' => 'my_test_server_1',
      'label' => 'My Test Server 1',
      'timeout' => 30,
      'encryption' => 'none',
      'address' => 'example',
      'port' => 963,
      'basedn' => ['ou=people,dc=hogwarts,dc=edu'],
      'user_attr' => 'cn',
      'unique_persistent_attr' => 'uid',
      'status' => TRUE,
      'mail_attr' => 'mail',
    ]);
    $server->save();
    $server = $manager->getStorage('ldap_server')->create([
      'id' => 'my_test_server_2',
      'label' => 'My Test Server 2',
      'status' => TRUE,
      'basedn' => ['ou=people,dc=hogwarts,dc=edu'],
    ]);
    $server->save();
    $server = $manager->getStorage('ldap_server')->create([
      'id' => 'my_test_server_3',
      'label' => 'My FALSe Server 3',
      'status' => FALSE,
      'basedn' => ['ou=people,dc=hogwarts,dc=edu'],
    ]);
    $server->save();
  }

  /**
   * Test the form.
   */
  public function testForm(): void {
    $this->drupalGet(self::FORM_PATH);
    $this->assertSession()->statusCodeEquals(403);

    $account = $this->drupalCreateUser(['administer ldap']);
    $this->drupalLogin($account);
    $this->drupalGet(self::FORM_PATH);
    $this->assertSession()->pageTextContains('My Test Server 1');
    $this->assertSession()->pageTextContains('My Test Server 2');
    $this->assertSession()->pageTextNotContains('My Test Server 3');

    $edit = [
      'authenticationMode' => 'exclusive',
      'allowOnlyIfTextInDn' => "one\ntwo",
      'edit-authenticationservers-my-test-server-1' => 1,
    ];
    $this->submitForm($edit, 'op');
    $this->assertSession()->statusCodeEquals(200);

    /** @var \Drupal\Core\Config\ImmutableConfig $config */
    $config = $this->container->get('config.factory')
      ->get('ldap_authentication.settings');
    self::assertEquals('exclusive', $config->get('authenticationMode'));
    self::assertEquals(['one', 'two'], $config->get('allowOnlyIfTextInDn'));
    // @todo Those could be saved nicer.
    $sids = [
      'my_test_server_1' => 'my_test_server_1',
      'my_test_server_2' => 0,
      'my_test_server_3' => 0,
    ];
    self::assertEquals($sids, $config->get('sids'));
  }

}
