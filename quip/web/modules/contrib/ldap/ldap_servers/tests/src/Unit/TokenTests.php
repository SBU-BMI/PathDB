<?php

namespace Drupal\Tests\ldap_servers\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\ldap_servers\Helper\ConversionHelper;
use Drupal\ldap_servers\Processor\TokenProcessor;
use Drupal\Tests\UnitTestCase;

/**
 * Helper class to make it possible to simulate ldap_explode_dn().
 */
class LdapExplodeDnMock {

  /**
   * Simulate explode_dn.
   *
   * @return array
   *   DN exploded, input ignored.
   */
  public static function ldapExplodeDn($input) {
    return [
      'count' => 4,
      0 => 'cn=hpotter',
      1 => 'ou=Gryffindor',
      2 => 'ou=student',
      3 => 'ou=people',
      4 => 'dc=hogwarts',
      5 => 'dc=edu',
    ];
  }

}

/**
 * @coversDefaultClass \Drupal\ldap_servers\Processor\TokenProcessor
 * @group ldap
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class TokenTests extends UnitTestCase {

  public $serverFactory;
  public $config;
  public $container;
  private $ldapEntry;
  protected $detailLog;
  protected $fileSystem;

  /**
   * Test setup.
   */
  protected function setUp() {
    parent::setUp();

    class_alias(
      '\Drupal\Tests\ldap_servers\Unit\LdapExplodeDnMock',
      '\Drupal\ldap_servers\Entity\Server',
      TRUE
    );

    $this->detailLog = $this->getMockBuilder('\Drupal\ldap_servers\Logger\LdapDetailLog')
      ->disableOriginalConstructor()
      ->getMock();

    $this->fileSystem = $this->getMockBuilder('\Drupal\Core\File\FileSystem')
      ->disableOriginalConstructor()
      ->getMock();

    /* Mocks the Server due to wrapper for ldap_explode_dn(). */
    $this->serverFactory = $this->getMockBuilder('\Drupal\ldap_servers\Entity\Server')
      ->disableOriginalConstructor()
      ->getMock();

    $this->container = new ContainerBuilder();
    $this->container->set('ldap.servers', $this->serverFactory);
    $this->container->set('ldap.detail_log', $this->detailLog);
    $this->container->set('file_system', $this->fileSystem);
    \Drupal::setContainer($this->container);

    $this->ldapEntry = [
      'dn' => 'cn=hpotter,ou=Gryffindor,ou=student,ou=people,dc=hogwarts,dc=edu',
      'mail' => [0 => 'hpotter@hogwarts.edu', 'count' => 1],
      'sAMAccountName' => [0 => 'hpotter', 'count' => 1],
      'house' => [0 => 'Gryffindor', 1 => 'Privet Drive', 'count' => 2],
      'guid' => [0 => 'sdafsdfsdf', 'count' => 1],
      'count' => 3,
    ];
  }

  /**
   * Test the replacement of tokens.
   *
   * See http://drupal.org/node/1245736 for test tokens.
   */
  public function testTokenReplacement() {

    $tokenHelper = new TokenProcessor($this->detailLog, $this->fileSystem);

    $dn = $tokenHelper->tokenReplace($this->ldapEntry, '[dn]');
    $this->assertEquals($this->ldapEntry['dn'], $dn);

    $house0 = $tokenHelper->tokenReplace($this->ldapEntry, '[house:0]');
    $this->assertEquals($this->ldapEntry['house'][0], $house0);

    $mixed = $tokenHelper->tokenReplace($this->ldapEntry, 'thisold[house:0]');
    $this->assertEquals('thisold' . $this->ldapEntry['house'][0], $mixed);

    $compound = $tokenHelper->tokenReplace($this->ldapEntry, '[samaccountname:0][house:0]');
    $this->assertEquals($this->ldapEntry['sAMAccountName'][0] . $this->ldapEntry['house'][0], $compound);

    $literalValue = $tokenHelper->tokenReplace($this->ldapEntry, 'literalvalue');
    $this->assertEquals('literalvalue', $literalValue);

    $house0 = $tokenHelper->tokenReplace($this->ldapEntry, '[house]');
    $this->assertEquals($this->ldapEntry['house'][0], $house0);

    $houseLast = $tokenHelper->tokenReplace($this->ldapEntry, '[house:last]');
    $this->assertEquals($this->ldapEntry['house'][1], $houseLast);

    $sAMAccountName = $tokenHelper->tokenReplace($this->ldapEntry, '[samaccountname:0]');
    $this->assertEquals($this->ldapEntry['sAMAccountName'][0], $sAMAccountName);

    $sAMAccountNameMixedCase = $tokenHelper->tokenReplace($this->ldapEntry, '[sAMAccountName:0]');
    $this->assertEquals($this->ldapEntry['sAMAccountName'][0], $sAMAccountNameMixedCase);

    $sAMAccountName2 = $tokenHelper->tokenReplace($this->ldapEntry, '[samaccountname]');
    $this->assertEquals($this->ldapEntry['sAMAccountName'][0], $sAMAccountName2);

    $sAMAccountName3 = $tokenHelper->tokenReplace($this->ldapEntry, '[sAMAccountName]');
    $this->assertEquals($this->ldapEntry['sAMAccountName'][0], $sAMAccountName3);

    $base64encode = $tokenHelper->tokenReplace($this->ldapEntry, '[guid;base64_encode]');
    $this->assertEquals(base64_encode($this->ldapEntry['guid'][0]), $base64encode);

    $bin2hex = $tokenHelper->tokenReplace($this->ldapEntry, '[guid;bin2hex]');
    $this->assertEquals(bin2hex($this->ldapEntry['guid'][0]), $bin2hex);

    $msguid = $tokenHelper->tokenReplace($this->ldapEntry, '[guid;msguid]');
    $this->assertEquals(ConversionHelper::convertMsguidToString($this->ldapEntry['guid'][0]), $msguid);

    $binary = $tokenHelper->tokenReplace($this->ldapEntry, '[guid;binary]');
    $this->assertEquals(ConversionHelper::binaryConversionToString($this->ldapEntry['guid'][0]), $binary);

    $account = $this->prophesize('\Drupal\user\Entity\User');
    $value = new \stdClass();
    $value->value = $this->ldapEntry['sAMAccountName'][0];
    $account->get('name')->willReturn($value);
    $nameReplacement = $tokenHelper->tokenReplace($account->reveal(), '[property.name]', 'user_account');
    $this->assertEquals($this->ldapEntry['sAMAccountName'][0], $nameReplacement);

  }

  /**
   * Additional token tests for the reverse behaviour for DN derivatives.
   */
  public function testTokensReverse() {
    $tokenHelper = new TokenProcessor($this->detailLog, $this->fileSystem);

    // Test regular reversal (2 elements) at beginning.
    $dc = $tokenHelper->tokenReplace($this->ldapEntry, '[dc:reverse:0]');
    $this->assertEquals('edu', $dc);

    // Test single element reversion.
    $ou = $tokenHelper->tokenReplace($this->ldapEntry, '[cn:reverse:0]');
    $this->assertEquals('hpotter', $ou);

    // Test 3 element reversion at end.
    $ou2 = $tokenHelper->tokenReplace($this->ldapEntry, '[ou:reverse:2]');
    $this->assertEquals('Gryffindor', $ou2);

  }

}
