<?php

namespace Drupal\Tests\jwt\Kernel;

use Drupal\jwt\JsonWebToken\JsonWebToken;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests JWT config schema.
 *
 * @group JWT
 */
class BasicTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'key', 'jwt', 'jwt_test'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installConfig(['key', 'jwt', 'jwt_test']);
  }

  /**
   * Verify the test config was loaded as keys.
   */
  public function testConfig() {
    /** @var \Drupal\key\KeyRepositoryInterface $key_repository */
    $key_repository = $this->container->get('key.repository');
    $key_hmac = $key_repository->getKey('jwt_test_hmac');
    self::assertNotEmpty($key_hmac);
    self::assertSame('jwt_hs', $key_hmac->getKeyType()->getPluginId());
    $key_rsa = $key_repository->getKey('jwt_test_rsa');
    $this->assertNotEmpty($key_rsa);
    self::assertSame('jwt_rs', $key_rsa->getKeyType()->getPluginId());
    // The jwt_test module configures the jwt_test_hmac key to be used.
    /** @var \Drupal\jwt\Transcoder\JwtTranscoderInterface $transcoder */
    $transcoder = $this->container->get('jwt.transcoder');
    $reflected = new \ReflectionClass($transcoder);
    $algorithm = $reflected->getProperty('algorithm');
    $algorithm->setAccessible(TRUE);
    self::assertSame('HS256', $algorithm->getValue($transcoder));
    $jwt = new JsonWebToken();

    $jwt->setClaim(['drupal', 'test'], 1234);
    $encoded = $transcoder->encode($jwt);
    self::assertNotEmpty($encoded);
    self::assertTrue(is_string($encoded));
    $decoded_jwt = $transcoder->decode($encoded);
    self::assertSame(1234, $decoded_jwt->getClaim(['drupal', 'test']));
  }

  /**
   * Test transcoder and JWT class.
   */
  public function testJsonWebToken() {
    $jwt = new JsonWebToken();
    /** @var \Drupal\jwt\Transcoder\JwtTranscoderInterface $transcoder */
    $transcoder = $this->container->get('jwt.transcoder');
    $jwt->setHeader('kid', 'llama');
    $jwt->setHeader('test', 7654);
    $jwt->setHeader('garbage', 'can');
    // These 2 headers should be discarded.
    $jwt->setHeader('alg', 'Zz256');
    $jwt->setHeader('typ', 'XWT');
    self::assertSame('can', $jwt->getHeader('garbage'));
    $jwt->unsetHeader('garbage');
    self::assertNull($jwt->getHeader('garbage'));
    $encoded = $transcoder->encode($jwt);
    $decoded_jwt = $transcoder->decode($encoded);
    self::assertSame('llama', $decoded_jwt->getHeader('kid'));
    self::assertSame(7654, $decoded_jwt->getHeader('test'));
    self::assertNull($decoded_jwt->getHeader('garbage'));
    self::assertSame('HS256', $decoded_jwt->getHeader('alg'));
    self::assertSame('JWT', $decoded_jwt->getHeader('typ'));
  }

}
