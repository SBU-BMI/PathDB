<?php

namespace Drupal\jwt_oauth_ccf;

/**
 * Interface for the OAuth client credential repository.
 *
 * Credentials are stored in the user.data store keyed by a globally-unique
 * client id (mirroring the users_jwt key repository), so a client id resolves
 * to exactly one user without loading every account.
 */
interface ClientCredentialRepositoryInterface {

  /**
   * Loads a credential by its client id.
   *
   * @param string $client_id
   *   The public client identifier.
   *
   * @return \Drupal\jwt_oauth_ccf\ClientCredential|null
   *   The credential, or NULL if none matches (or the id is not unique).
   */
  public function getClient(string $client_id): ?ClientCredential;

  /**
   * Returns all credentials owned by a user, keyed by client id.
   *
   * @param int $uid
   *   The user ID.
   *
   * @return \Drupal\jwt_oauth_ccf\ClientCredential[]
   *   Credentials keyed by client id.
   */
  public function getUserClients(int $uid): array;

  /**
   * Stores and returns a new credential for a user.
   *
   * When $secret is NULL a strong secret is generated and returned on the
   * ->secret property of the result (so it can be shown once). When the caller
   * supplies $secret, it is hashed and stored but not echoed back on ->secret,
   * since the caller already holds it. Only the hash is ever persisted.
   *
   * @param int $uid
   *   The owner user ID.
   * @param string $label
   *   A human-readable label.
   * @param string|null $secret
   *   A user-supplied plaintext secret, or NULL to auto-generate one.
   * @param string|null $client_id
   *   A caller-supplied client id, or NULL to auto-generate a random one. A
   *   supplied id must be globally unique.
   *
   * @return \Drupal\jwt_oauth_ccf\ClientCredential
   *   The stored credential; ->secret is populated only when auto-generated.
   *
   * @throws \InvalidArgumentException
   *   When a supplied client id is already in use.
   */
  public function createClient(int $uid, string $label, ?string $secret = NULL, ?string $client_id = NULL): ClientCredential;

  /**
   * Verifies a client secret against a stored credential.
   *
   * @param \Drupal\jwt_oauth_ccf\ClientCredential $client
   *   The stored credential.
   * @param string $secret
   *   The plaintext secret presented by the client.
   *
   * @return bool
   *   TRUE if the secret matches.
   */
  public function verifySecret(ClientCredential $client, string $secret): bool;

  /**
   * Deletes a single credential.
   *
   * @param string $client_id
   *   The client id to delete.
   */
  public function deleteClient(string $client_id): void;

  /**
   * Deletes all credentials owned by a user.
   *
   * @param int $uid
   *   The user ID.
   */
  public function deleteUserClients(int $uid): void;

}
