<?php

namespace Drupal\jwt\Transcoder;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\jwt\JsonWebToken\JsonWebToken;
use Drupal\jwt\JsonWebToken\JsonWebTokenInterface;
use Drupal\key\KeyInterface;
use Drupal\key\KeyRepositoryInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * The JWT Transcoder.
 */
class JwtTranscoder implements JwtTranscoderInterface {

  /**
   * A firebase/php-jwt instance or another instance with the same methods.
   *
   * @var \Firebase\JWT\JWT
   */
  protected $transcoder;

  /**
   * The algorithm with which a JWT can be decoded.
   *
   * @var string
   */
  protected string $algorithm;

  /**
   * The algorithm type we are using.
   *
   * @var string
   */
  protected string $algorithmType;

  /**
   * The key used to encode/decode a JsonWebToken.
   *
   * @var string
   */
  protected ?string $secret = NULL;

  /**
   * The PEM encoded private key used for signing RSA JWTs.
   *
   * @var string
   */
  protected ?string $privateKey = NULL;

  /**
   * The PEM encoded public key used to verify signatures on RSA JWTs.
   *
   * @var string
   */
  protected ?string $publicKey = NULL;

  /**
   * {@inheritdoc}
   */
  public static function getAlgorithmOptions(): array {
    return [
      'HS256' => 'HMAC using SHA-256 (HS256)',
      'HS384' => 'HMAC using SHA-384 (HS384)',
      'HS512' => 'HMAC using SHA-512 (HS512)',
      'RS256' => 'RSASSA-PKCS1-v1_5 using SHA-256 (RS256)',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getAlgorithmType(string $algorithm): ?string {
    switch ($algorithm) {
      case 'HS256':
      case 'HS384':
      case 'HS512':
        return 'jwt_hs';

      case 'RS256':
        return 'jwt_rs';

      default:
        return NULL;
    }
  }

  /**
   * Constructs a new JwtTranscoder.
   *
   * @param string|null $jwt_class
   *   The JWT library class.
   * @param \Drupal\key\KeyInterface|null $key
   *   The JWT key.
   */
  public function __construct(?string $jwt_class = NULL, ?KeyInterface $key = NULL) {
    if (!$jwt_class) {
      $jwt_class = JWT::class;
    }
    $this->transcoder = new $jwt_class();
    if ($key) {
      $this->setKey($key);
    }
  }

  /**
   * Apply site configuration to set a key.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Drupal config factory to retrieve the configuration information.
   * @param \Drupal\key\KeyRepositoryInterface $key_repo
   *   The Key repository to retrieve the key.
   */
  public function applyConfiguration(ConfigFactoryInterface $configFactory, KeyRepositoryInterface $key_repo) {
    $key_id = $configFactory->get('jwt.config')->get('key_id');
    if ($key_id) {
      $key = $key_repo->getKey($key_id);
      if ($key) {
        $this->setKey($key);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setKey(KeyInterface $key): void {
    $keyConfig = $key->getPlugin('key_type')->getConfiguration();
    $algorithm = $keyConfig['algorithm'];
    $this->setAlgorithm($algorithm);

    $key_value = $key->getKeyValue();
    $valid = !empty($key_value) && $this->algorithmType;
    if ($valid) {
      if ($this->algorithmType == 'jwt_hs') {
        // Symmetric algorithm so we set the secret.
        $this->setSecret($key_value);
      }
      elseif ($this->algorithmType == 'jwt_rs') {
        // Asymmetric algorithm so we set the public or private key.
        if (strpos($key_value, '-----BEGIN PUBLIC KEY-----') !== FALSE) {
          $this->setPublicKey($key_value);
        }
        else {
          $this->setPrivateKey($key_value);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setSecret(string $secret): bool {
    // @todo Check the algorithm for the needed bits. Minimum is 256.
    $bytes = 256 / 8;
    if (strlen($secret) < $bytes) {
      return FALSE;
    }
    $this->secret = $secret;
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setAlgorithm(string $algorithm): ?string {
    $this->algorithm = $algorithm;
    $this->algorithmType = $this->getAlgorithmType($algorithm);
    return $this->algorithmType;
  }

  /**
   * {@inheritdoc}
   */
  public function setPrivateKey(string $private_key, bool $derive_public_key = TRUE): bool {
    $key_context = openssl_pkey_get_private($private_key);
    if ($key_context === FALSE) {
      return FALSE;
    }
    $key_details = openssl_pkey_get_details($key_context);
    if ($key_details === FALSE || $key_details['type'] != OPENSSL_KEYTYPE_RSA) {
      return FALSE;
    }

    $this->privateKey = $private_key;
    if ($derive_public_key) {
      $this->publicKey = $key_details['key'];
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setPublicKey(string $public_key): bool {
    $key_context = openssl_pkey_get_public($public_key);
    if ($key_context === FALSE) {
      return FALSE;
    }
    $key_details = openssl_pkey_get_details($key_context);
    if ($key_details === FALSE || $key_details['type'] != OPENSSL_KEYTYPE_RSA) {
      return FALSE;
    }

    $this->publicKey = $public_key;
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function decode(string $raw_jwt): JsonWebTokenInterface {
    $key = $this->getKey('decode');
    try {
      $token = $this->transcoder->decode($raw_jwt, new Key($key, $this->algorithm));
      // Unfortunately this JWT implementation does not save or allow the
      // header to be retrieved via a simple method, so we need to decode it
      // again. The decode() call above has already validated it.
      $tks = explode('.', $raw_jwt);
      $headb64 = $tks[0];
      $header = $this->transcoder->jsonDecode($this->transcoder->urlsafeB64Decode($headb64));
    }
    catch (\Exception $e) {
      throw JwtDecodeException::newFromException($e);
    }
    return new JsonWebToken($token, $header);
  }

  /**
   * {@inheritdoc}
   */
  public function encode(JsonWebTokenInterface $jwt): ?string {
    $key = $this->getKey('encode');
    // Refuse to encode if we don't have a key yet.
    if ($key === NULL) {
      return NULL;
    }
    $jwt->unsetHeader('alg');
    $jwt->unsetHeader('typ');
    return (string) $this->transcoder->encode($jwt->getPayload(), $key, $this->algorithm, NULL, $jwt->getHeaders());
  }

  /**
   * Helper function to get the correct key based on operation.
   *
   * @param string $operation
   *   The operation being performed. One of: encode, decode.
   *
   * @return null|string
   *   Returns NULL if operation is not found. Otherwise, returns key.
   */
  protected function getKey(string $operation) {
    if ($this->algorithmType == 'jwt_hs') {
      return $this->secret;
    }
    elseif ($this->algorithmType == 'jwt_rs') {
      if ($operation == 'encode') {
        return $this->privateKey;
      }
      elseif ($operation == 'decode') {
        return $this->publicKey;
      }
    }
    return NULL;
  }

}
