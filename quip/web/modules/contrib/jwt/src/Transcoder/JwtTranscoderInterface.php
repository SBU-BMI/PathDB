<?php

namespace Drupal\jwt\Transcoder;

use Drupal\jwt\JsonWebToken\JsonWebTokenInterface;
use Drupal\key\KeyInterface;

/**
 * The Interface for the JWT Transcoder.
 */
interface JwtTranscoderInterface {

  /**
   * Set a new key for the RaftJwtTranscoder.
   *
   * @param \Drupal\key\KeyInterface $key
   *   The JWT key.
   */
  public function setKey(KeyInterface $key): void;

  /**
   * Gets a validated JsonWebToken from an encoded JWT.
   *
   * @param string $raw_jwt
   *   The encoded JWT in raw string form.
   *
   * @return \Drupal\jwt\JsonWebToken\JsonWebTokenInterface
   *   Validated JWT.
   *
   * @throws \Drupal\jwt\Transcoder\JwtDecodeException
   */
  public function decode(string $raw_jwt): JsonWebTokenInterface;

  /**
   * Encodes a JsonWebToken.
   *
   * Note that headers 'alg' and 'typ' will be removed and replaced by the
   * default values if they are set on the JsonWebTokenInterface object.
   *
   * @param \Drupal\jwt\JsonWebToken\JsonWebTokenInterface $jwt
   *   A JWT object.
   *
   * @return string|null
   *   The encoded JWT or null on failure.
   */
  public function encode(JsonWebTokenInterface $jwt): ?string;

  /**
   * Sets the secret that is used for a symmetric algorithm signature.
   *
   * The secret is only used when a symmetric algorithm is selected. Currently
   * the symmetric algorithms supported are:
   *   * HS256
   *   * HS384
   *   * HS512
   * The secret is used for both signature creation and verification.
   *
   * @param string $secret
   *   The secret for the JWT.
   *
   * @return bool
   *   Function does some validation of the key. Returns TRUE on success.
   */
  public function setSecret(string $secret): bool;

  /**
   * Sets the algorithm to be used for the JWT.
   *
   * @param string $algorithm
   *   This can be any of the array keys returned by the getAlgorithmOptions
   *   function.
   *
   * @return string|null
   *   The algorithm type, or NULL if the algorithm is invalid.
   *
   * @see getAlgorithmOptions()
   */
  public function setAlgorithm(string $algorithm): ?string;

  /**
   * Sets the private key used to create signatures for an asymmetric algorithm.
   *
   * This key is only used when an asymmetric algorithm is selected. Currently
   * supported asymmetric algorithms are:
   *   * RS256
   *
   * @param string $private_key
   *   A PEM encoded private key.
   * @param bool $derive_public_key
   *   (Optional) Derive the public key from the private key. Defaults to true.
   *
   * @return bool
   *   Function does some validation of the key. Returns TRUE on success.
   */
  public function setPrivateKey(string $private_key, bool $derive_public_key = TRUE): bool;

  /**
   * Sets the public key used to verify signatures for an asymmetric algorithm.
   *
   * This key is only used when an asymmetric algorithm is selected. Currently
   * supported asymmetric algorithms are:
   *   * RS256
   *
   * @param string $public_key
   *   A PEM encoded public key.
   *
   * @return mixed
   *   Function does some validation of the key. Returns TRUE on success.
   */
  public function setPublicKey(string $public_key): bool;

  /**
   * Return the type of algorithm selected.
   *
   * @param string $algorithm
   *   The algorithm.
   *
   * @return string|null
   *   The algorithm type. Returns NULL if algorithm not found.
   */
  public static function getAlgorithmType(string $algorithm): ?string;

  /**
   * Gets a list of algorithms supported by this transcoder.
   *
   * @return array
   *   An array of options formatted for a select list.
   */
  public static function getAlgorithmOptions(): array;

}
