<?php

declare(strict_types = 1);

namespace Drupal\Tests\ldap_query\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test the admin form.
 *
 * @group ldap
 */
class QueryEntityFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Form path.
   *
   * @var string
   */
  protected const FORM_PATH = '/admin/config/people/ldap/query/add';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'ldap_servers',
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

    $edit = [
      'label' => 'My Query',
      'id' => 'my_query',
      'edit-server-id-my-test-server-1' => 'my_test_server_1',
      'status' => 1,
      // @todo Investigate if carriage return is consistent across forms.
      'base_dn' => "ou=group1\r\nou=group2,dc=one",
      'filter' => '(&(objectClass=user)(homePhone=*))',
      'attributes' => 'objectclass,name,cn,samaccountname',
    ];
    $this->submitForm($edit, 'op');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Created the My Query LDAP query.');

    $manager = $this->container->get('entity_type.manager');
    /** @var \Drupal\ldap_query\Entity\QueryEntity $query */
    $query = $manager->getStorage('ldap_query_entity')->load('my_query');
    self::assertEquals('My Query', $query->label());
    self::assertEquals(TRUE, $query->status());
    self::assertEquals(['ou=group1', 'ou=group2,dc=one'], $query->getProcessedBaseDns());
    self::assertEquals('(&(objectClass=user)(homePhone=*))', $query->getFilter());
    $attributes = ['objectclass', 'name', 'cn', 'samaccountname'];
    self::assertEquals($attributes, $query->getProcessedAttributes());
    self::assertEquals('sub', $query->getScope());
    // Test for LDAP_DEREF_NEVER (0).
    self::assertEquals(0, $query->getDereference());
    self::assertEquals(0, $query->getTimeLimit());
    self::assertEquals(0, $query->getSizeLimit());
  }

}
