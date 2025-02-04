<?php

namespace Drupal\jwt\JsonWebToken;

/**
 * Interface for a JSON Web Token class that helps manage claims.
 */
interface JsonWebTokenInterface {

  /**
   * Gets the un-encoded payload as an array.
   *
   * @return array
   *   The un-encoded payload.
   */
  public function getPayload(): array;

  /**
   * Retrieve a claim from the JWT payload.
   *
   * @param mixed $claim
   *   Either a string or indexed array of strings (if nested) representing the
   *   claim to retrieve. If an indexed array is passed, it will be used to
   *   traverse the JWT where the 0th element is the topmost claim.
   *
   * @returns mixed The contents of the claim.
   */
  public function getClaim($claim);

  /**
   * Add or update the given claim with the given value.
   *
   * @param mixed $claim
   *   Either a string or indexed array of strings representing the claim or
   *   nested claim to be set.
   * @param mixed $value
   *   A serializable value to set the given claim to on the JWT.
   */
  public function setClaim($claim, $value);

  /**
   * Remove a claim from the JWT payload.
   *
   * @param mixed $claim
   *   Either a string or indexed array of strings.
   *
   * @See Drupal\jwt\JsonWebTokenInterface::getClaim().
   */
  public function unsetClaim($claim);

  /**
   * Gets the un-encoded header claims as an array.
   *
   * @return array
   *   The un-encoded headers.
   */
  public function getHeaders(): array;

  /**
   * Retrieve a claim from the JWT header.
   *
   * @param string $claim
   *   A string representing the header claim to retrieve.
   *
   * @returns mixed The contents of the claim, or null if there is none.
   */
  public function getHeader(string $claim);

  /**
   * Add or update the given claim with the given value.
   *
   * @param string $claim
   *   A string representing the header claim to be set.
   * @param mixed $value
   *   A serializable value to set the given header to on the JWT.
   */
  public function setHeader(string $claim, $value);

  /**
   * Remove a header from the JWT payload.
   *
   * @param string $claim
   *   A string header name.
   *
   * @See Drupal\jwt\JsonWebTokenInterface::getHeader().
   */
  public function unsetHeader(string $claim);

}
