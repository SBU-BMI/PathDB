<?php

declare(strict_types=1);

namespace Drupal\Tests\ldap_servers\Unit\Entity;

use Drupal\ldap_servers\Entity\Server;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Server entity tests.
 *
 * @group ldap_servers
 */
class ServerTest extends UnitTestCase {

  /**
   * The server entity.
   *
   * @var \Drupal\ldap_servers\Entity\Server
   */
  protected $server;

  /**
   * The container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * The token processor.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $tokenProcessor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container = new ContainerBuilder();

    $string_translation = $this->getStringTranslationStub();
    $this->container->set('string_translation', $string_translation);

    $logger_factory = $this->prophesize('Drupal\Core\Logger\LoggerChannelFactory');
    $this->container->set('logger.factory', $logger_factory->reveal());

    $detail_logger = $this->prophesize('Drupal\ldap_servers\Logger\LdapDetailLog');
    $this->container->set('ldap.detail_log', $detail_logger->reveal());

    $this->tokenProcessor = $this->prophesize('Drupal\ldap_servers\Processor\TokenProcessor');
    $this->container->set('ldap.token_processor', $this->tokenProcessor->reveal());

    $module_handler = $this->prophesize('Drupal\Core\Extension\ModuleHandlerInterface');
    $this->container->set('module_handler', $module_handler->reveal());

    $entity_type_manager = $this->prophesize('Drupal\Core\Entity\EntityTypeManagerInterface');
    $this->container->set('entity_type.manager', $entity_type_manager->reveal());

    \Drupal::setContainer($this->container);

    $this->server = new Server([
      'id' => 'example',
      'label' => 'Example Server',
      'status' => TRUE,
      'bind_method' => 'service_account',
      'address' => 'ldap.example.com',
      'port' => 389,
      'timeout' => 10,
      'encryption' => 'none',
      'mail_template' => '[cn]@hogwarts.edu',
      'weight' => 0,
      'basedn' => [
        'DC=example,DC=com',
      ],
      'account_name_attr' => 'sAMAccountName',
      'unique_persistent_attr' => 'objectsid',
      'user_attr' => 'cn',
      'grp_user_memb_attr_exists' => FALSE,
    ], 'ldap_server');

  }

  /**
   * Test the getBindMethod method.
   */
  public function testGetBindMethod(): void {
    $this->assertEquals('service_account', $this->server->getBindMethod());
  }

  /**
   * Test the setBindMethod method.
   */
  public function testSetBindMethod(): void {
    $this->server->set('bind_method', 'user');
    $this->assertEquals('user', $this->server->getBindMethod());
  }

  /**
   * Test the getFormattedBind method.
   */
  public function testGetFormattedBind(): void {
    $this->assertEquals('service account bind', (string) $this->server->getFormattedBind());
  }

  /**
   * Test the getFormattedBind method with a service account.
   */
  public function testGetFormattedBindServiceAccount(): void {
    $this->server->set('bind_method', 'service_account');
    $this->assertEquals('service account bind', (string) $this->server->getFormattedBind());
  }

  /**
   * Test the getFormattedBind method with a user account.
   */
  public function testGetFormattedBindUserAccount(): void {
    $this->server->set('bind_method', 'user');
    $this->assertEquals('user credentials bind', (string) $this->server->getFormattedBind());
  }

  /**
   * Test the getFormattedBind method with an anonymous user.
   */
  public function testGetFormattedBindAnonUser(): void {
    $this->server->set('bind_method', 'anon_user');
    $this->assertEquals('anonymous bind', (string) $this->server->getFormattedBind());
  }

  /**
   * Test the getFormattedBind method with an anonymous user.
   */
  public function testGetFormattedBindAnon(): void {
    $this->server->set('bind_method', 'anon');
    $this->assertEquals('anonymous bind (search), then user credentials', (string) $this->server->getFormattedBind());
  }

  /**
   * Test the getFormattedBind method with an anonymous user.
   */
  public function testGetFormattedBindInvalid(): void {
    $this->server->set('bind_method', 'invalid');
    $this->assertEquals('service account bind', (string) $this->server->getFormattedBind());
  }

  /**
   * Test the getBasedn method.
   */
  public function testBasedn(): void {
    $basedn = $this->server->getBasedn();
    $this->assertIsArray($basedn);
    $this->assertCount(1, $basedn);
    $this->assertEquals('DC=example,DC=com', $basedn[0]);
  }

  /**
   * Test the getAccountNameAttribute method.
   */
  public function testGetAccountNameAttribute(): void {
    $this->assertTrue($this->server->hasAccountNameAttribute());
    $this->assertEquals('sAMAccountName', $this->server->getAccountNameAttribute());
  }

  /**
   * Test the getServerAddress method.
   */
  public function testGetServerAddress(): void {
    $this->assertEquals('ldap.example.com', $this->server->getServerAddress());
  }

  /**
   * Test the getPort method.
   */
  public function testGetPort(): void {
    $this->assertEquals(389, $this->server->getPort());
  }

  /**
   * Test the isActive method.
   */
  public function testIsActive(): void {
    $this->assertTrue($this->server->isActive());
  }

  /**
   * Test the getTimeout method.
   */
  public function testGetTimeout(): void {
    $this->assertEquals(10, $this->server->getTimeout());
  }

  /**
   * Test the get('encryption') method.
   */
  public function testGetEncryption(): void {
    $this->assertEquals('none', $this->server->get('encryption'));
  }

  /**
   * Test the getWeight method.
   */
  public function testGetWeight(): void {
    $this->assertEquals(0, $this->server->getWeight());
  }

  /**
   * Test the getUniquePersistentAttribute method.
   */
  public function testGetUniquePersistentAttribute(): void {
    $this->assertEquals('objectsid', $this->server->getUniquePersistentAttribute());
  }

  /**
   * Test the isUniquePersistentAttributeBinary method.
   */
  public function testIsUniquePersistentAttributeBinary() {
    $this->assertFalse($this->server->isUniquePersistentAttributeBinary());
  }

  /**
   * Test the getAuthenticationNameAttribute method.
   */
  public function testGetAuthenticationNameAttribute(): void {
    $this->assertEquals('cn', $this->server->getAuthenticationNameAttribute());
  }

  /**
   * Test the getUserDnExpression method.
   */
  public function testGetUserDnExpression(): void {
    $this->assertNull($this->server->getUserDnExpression());
  }

  /**
   * Test the getPictureAttribute method.
   */
  public function testGetPictureAttribute(): void {
    $this->assertNull($this->server->getPictureAttribute());
  }

  /**
   * Test the getBindDn method.
   */
  public function testGetBindDn(): void {
    $this->assertNull($this->server->getBindDn());
  }

  /**
   * Test the getBindPassword method.
   */
  public function testGetBindPassword(): void {
    $this->assertNull($this->server->getBindPassword());
  }

  /**
   * Test the getTestingDrupalUsername method.
   */
  public function testGetTestingDrupalUsername(): void {
    $this->assertNull($this->server->getTestingDrupalUsername());
  }

  /**
   * Test the getTestingDrupalUserDn method.
   */
  public function testGetTestingDrupalUserDn(): void {
    $this->assertNull($this->server->getTestingDrupalUserDn());
  }

  /**
   * Test the getTestingDrupalUserPassword method.
   */
  public function testGetDerivedGroupFromDnAttribute(): void {
    $this->assertNull($this->server->getDerivedGroupFromDnAttribute());
  }

  /**
   * Test the getDerivedGroupFromDnAttribute method.
   */
  public function testGetUserAttributeFromGroupMembershipEntryAttribute(): void {
    $this->assertNull($this->server->getUserAttributeFromGroupMembershipEntryAttribute());
  }

  /**
   * Test the getGroupMembershipAttribute method.
   */
  public function testGetGroupMembershipAttribute(): void {
    $this->assertNull($this->server->getGroupMembershipAttribute());
  }

  /**
   * Test the getGroupObjectClass method.
   */
  public function testGetGroupObjectClass(): void {
    $this->assertNull($this->server->getGroupObjectClass());
  }

  /**
   * Test the getGroupTestGroupDnWriteable method.
   */
  public function testGetGroupTestGroupDnWriteable(): void {
    $this->assertNull($this->server->getGroupTestGroupDnWriteable());
  }

  /**
   * Test the getGroupTestGroupDn method.
   */
  public function testGetGroupTestGroupDn(): void {
    $this->assertNull($this->server->getGroupTestGroupDn());
  }

  /**
   * Test the getGroupUserMembershipAttribute method.
   */
  public function testGetGroupUserMembershipAttribute(): void {
    $this->assertNull($this->server->getGroupUserMembershipAttribute());
  }

  /**
   * Test the getMailAttribute method.
   */
  public function testGetMailAttribute(): void {
    $this->assertNull($this->server->getMailAttribute());
  }

  /**
   * Test the getMailTemplate method.
   */
  public function testGetMailTemplate(): void {
    $this->assertEquals('[cn]@hogwarts.edu', $this->server->getMailTemplate());
  }

  /**
   * Test the isGroupDerivedFromDn method.
   */
  public function testisGroupDerivedFromDn(): void {
    $this->assertFalse($this->server->isGroupDerivedFromDn());
  }

  /**
   * Test the isGroupNested method.
   */
  public function testIsGroupNested(): void {
    $this->assertFalse($this->server->isGroupNested());
  }

  /**
   * Test the isGroupUnused method.
   */
  public function testIsGroupUnused(): void {
    $this->assertTrue($this->server->isGroupUnused());
  }

  /**
   * Test the isGroupUserMembershipAttributeInUse method.
   */
  public function testIsGroupUserMembershipAttributeInUse(): void {
    $this->assertFalse($this->server->isGroupUserMembershipAttributeInUse());
  }

  /**
   * Test the deriveUsernameFromLdapResponse method.
   */
  public function testDeriveUsernameFromLdapResponseHasAccountNameAttribute(): void {
    $ldap_entry = $this->createMock('Symfony\Component\Ldap\Entry');
    $ldap_entry->expects($this->once())
      ->method('hasAttribute')
      ->willReturn(TRUE);
    $ldap_entry->expects($this->once())
      ->method('getAttribute')
      ->willReturn(['hpotter']);

    $result = $this->server->deriveUsernameFromLdapResponse($ldap_entry);
    $this->assertIsString($result);
    $this->assertEquals('hpotter', $result);
  }

  /**
   * Test the deriveUsernameFromLdapResponse method.
   */
  public function testDeriveUsernameFromLdapResponseHasAuthenticationNameAttribute(): void {
    $server = new Server([
      'id' => 'example',
      'label' => 'Example Server',
      'status' => TRUE,
      'bind_method' => 'service_account',
      'address' => 'ldap.example.com',
      'port' => 389,
      'timeout' => 10,
      'encryption' => 'none',
      'weight' => 0,
      'basedn' => [
        'DC=example,DC=com',
      ],
      'account_name_attr' => '',
      'unique_persistent_attr' => 'objectsid',
      'user_attr' => 'cn',
      'grp_user_memb_attr_exists' => FALSE,
    ], 'ldap_server');
    $ldap_entry = $this->createMock('Symfony\Component\Ldap\Entry');
    $ldap_entry->expects($this->once())
      ->method('hasAttribute')
      ->willReturn(TRUE);
    $ldap_entry->expects($this->once())
      ->method('getAttribute')
      ->willReturn(['hpotter']);

    $result = $server->deriveUsernameFromLdapResponse($ldap_entry);
    $this->assertIsString($result);
    $this->assertEquals('hpotter', $result);
  }

  /**
   * Test the deriveUsernameFromLdapResponse method.
   */
  public function testDeriveEmailFromLdapResponse(): void {
    $ldap_entry = $this->createMock('Symfony\Component\Ldap\Entry');

    $this->tokenProcessor->ldapEntryReplacementsForDrupalAccount($ldap_entry, '[cn]@hogwarts.edu')
      ->willReturn('hpotter@hogwarts.edu');

    $result = $this->server->deriveEmailFromLdapResponse($ldap_entry);
    $this->assertIsString($result);
    $this->assertEquals('hpotter@hogwarts.edu', $result);
  }

  /**
   * Test the deriveEmailFromLdapResponse method.
   */
  public function testDeriveEmailFromLdapResponseHasMailAttribute(): void {
    $server = new Server([
      'id' => 'example',
      'label' => 'Example Server',
      'status' => TRUE,
      'bind_method' => 'service_account',
      'address' => 'ldap.example.com',
      'port' => 389,
      'timeout' => 10,
      'encryption' => 'none',
      'mail_template' => '[cn]@hogwarts.edu',
      'mail_attr' => 'mail',
      'weight' => 0,
      'basedn' => [
        'DC=example,DC=com',
      ],
      'account_name_attr' => 'sAMAccountName',
      'unique_persistent_attr' => 'objectsid',
      'user_attr' => 'cn',
      'grp_user_memb_attr_exists' => FALSE,
    ], 'ldap_server');

    $ldap_entry = $this->createMock('Symfony\Component\Ldap\Entry');
    $ldap_entry->expects($this->once())
      ->method('hasAttribute')
      ->willReturn(TRUE);
    $ldap_entry->expects($this->once())
      ->method('getAttribute')
      ->willReturn(['hpotter@hogwarts.edu']);

    $result = $server->deriveEmailFromLdapResponse($ldap_entry);
    $this->assertIsString($result);
    $this->assertEquals('hpotter@hogwarts.edu', $result);
  }

  /**
   * Test the deriveEmailFromLdapResponse method.
   */
  public function testDerivePuidFromLdapResponse(): void {
    $server = new Server([
      'id' => 'example',
      'label' => 'Example Server',
      'status' => TRUE,
      'bind_method' => 'service_account',
      'address' => 'ldap.example.com',
      'port' => 389,
      'timeout' => 10,
      'encryption' => 'none',
      'mail_template' => '[cn]@hogwarts.edu',
      'mail_attr' => 'mail',
      'weight' => 0,
      'basedn' => [
        'DC=example,DC=com',
      ],
      'unique_persistent_attr_binary' => TRUE,
      'account_name_attr' => 'sAMAccountName',
      'unique_persistent_attr' => 'objectsid',
      'user_attr' => 'cn',
      'grp_user_memb_attr_exists' => FALSE,
    ], 'ldap_server');

    $ldap_entry = $this->createMock('Symfony\Component\Ldap\Entry');
    $ldap_entry->expects($this->once())
      ->method('hasAttribute')
      ->willReturn(TRUE);
    $ldap_entry->expects($this->once())
      ->method('getAttribute')
      ->willReturn(['hpotter']);

    $result = $server->derivePuidFromLdapResponse($ldap_entry);
    $this->assertIsString($result);
    $this->assertEquals('68706f74746572', $result);
  }

}
