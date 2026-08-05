<?php

namespace Drupal\jwt_oauth_ccf;

/**
 * A simple data object describing a stored OAuth client credential.
 *
 * The plaintext secret is never stored; only $secretHash is persisted. The
 * plaintext is available on the transient object returned when a credential is
 * first generated, so it can be shown to the user exactly once.
 */
class ClientCredential {

  /**
   * The owner user ID.
   *
   * @var int
   */
  public int $uid;

  /**
   * The public client identifier.
   *
   * @var string
   */
  public string $clientId;

  /**
   * A human-readable label for the credential.
   *
   * @var string
   */
  public string $label;

  /**
   * The hashed client secret.
   *
   * @var string
   */
  public string $secretHash;

  /**
   * The creation timestamp.
   *
   * @var int
   */
  public int $created;

  /**
   * The plaintext secret. Only set on a freshly generated credential.
   *
   * @var string|null
   */
  public ?string $secret = NULL;

  /**
   * ClientCredential constructor.
   *
   * @param int $uid
   *   The owner user ID.
   * @param string $client_id
   *   The public client identifier.
   * @param string $label
   *   A human-readable label.
   * @param string $secret_hash
   *   The hashed client secret.
   * @param int $created
   *   The creation timestamp.
   */
  public function __construct(int $uid, string $client_id, string $label, string $secret_hash, int $created) {
    $this->uid = $uid;
    $this->clientId = $client_id;
    $this->label = $label;
    $this->secretHash = $secret_hash;
    $this->created = $created;
  }

}
