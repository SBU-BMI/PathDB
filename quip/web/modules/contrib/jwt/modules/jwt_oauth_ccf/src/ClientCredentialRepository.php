<?php

namespace Drupal\jwt_oauth_ccf;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\user\UserDataInterface;

/**
 * Stores OAuth client credentials in the user.data store.
 */
class ClientCredentialRepository implements ClientCredentialRepositoryInterface {

  /**
   * The user.data module name used as the first-level key.
   */
  protected const MODULE = 'jwt_oauth_ccf';

  /**
   * Length of a generated client secret, in characters.
   */
  protected const SECRET_LENGTH = 64;

  /**
   * Maximum secret length accepted for hashing/verification.
   *
   * Mirrors the core password service's guard: refuse to hash or verify very
   * long inputs so a hashing algorithm that processes the whole string cannot
   * be abused for a denial-of-service.
   */
  protected const SECRET_MAX_LENGTH = 512;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected UserDataInterface $userData;

  /**
   * The password generator service.
   *
   * @var \Drupal\Core\Password\PasswordGeneratorInterface
   */
  protected PasswordGeneratorInterface $passwordGenerator;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * Constructs the repository.
   *
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   * @param \Drupal\Core\Password\PasswordGeneratorInterface $password_generator
   *   The password generator service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(UserDataInterface $user_data, PasswordGeneratorInterface $password_generator, TimeInterface $time) {
    $this->userData = $user_data;
    $this->passwordGenerator = $password_generator;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public function getClient(string $client_id): ?ClientCredential {
    if ($client_id === '') {
      return NULL;
    }
    $records = $this->userData->get(self::MODULE, NULL, $client_id);
    // The client id must resolve to exactly one user.
    if (empty($records) || count($records) > 1) {
      return NULL;
    }
    $uid = array_key_first($records);
    return $this->recordToObject((int) $uid, $client_id, $records[$uid]);
  }

  /**
   * {@inheritdoc}
   */
  public function getUserClients(int $uid): array {
    $records = $this->userData->get(self::MODULE, $uid) ?: [];
    $clients = [];
    foreach ($records as $client_id => $record) {
      $clients[$client_id] = $this->recordToObject($uid, (string) $client_id, $record);
    }
    return $clients;
  }

  /**
   * {@inheritdoc}
   */
  public function createClient(int $uid, string $label, ?string $secret = NULL, ?string $client_id = NULL): ClientCredential {
    if ($client_id === NULL || $client_id === '') {
      // Generate a client id that is not already in use. Collisions are
      // astronomically unlikely with 128 bits of entropy, but check anyway.
      do {
        $client_id = 'ccf_' . bin2hex(random_bytes(16));
      } while ($this->getClient($client_id) !== NULL);
    }
    elseif ($this->getClient($client_id) !== NULL) {
      // A caller-supplied id must be unique: refuse to overwrite an existing
      // credential (which could belong to a different account).
      throw new \InvalidArgumentException(sprintf('The OAuth client id "%s" is already in use.', $client_id));
    }

    $generated = $secret === NULL;
    if ($generated) {
      $secret = $this->passwordGenerator->generate(self::SECRET_LENGTH);
    }
    $record = [
      'label' => $label,
      'secret_hash' => password_hash($secret, '2y'),
      'created' => $this->time->getRequestTime(),
    ];
    $this->userData->set(self::MODULE, $uid, $client_id, $record);

    $client = $this->recordToObject($uid, $client_id, $record);
    // Only surface the plaintext when we generated it; a user-supplied secret
    // is already known to the caller and should not be echoed back.
    if ($generated) {
      $client->secret = $secret;
    }
    return $client;
  }

  /**
   * {@inheritdoc}
   */
  public function verifySecret(ClientCredential $client, string $secret): bool {
    if ($secret === '' || $client->secretHash === '') {
      return FALSE;
    }
    // Refuse to verify overly long secrets to avoid a hashing DoS.
    if (strlen($secret) > self::SECRET_MAX_LENGTH) {
      return FALSE;
    }
    return password_verify($secret, $client->secretHash);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteClient(string $client_id): void {
    // The client id is globally unique, so deleting by name is sufficient.
    $this->userData->delete(self::MODULE, NULL, $client_id);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteUserClients(int $uid): void {
    $this->userData->delete(self::MODULE, $uid);
  }

  /**
   * Builds a ClientCredential object from a stored record.
   *
   * @param int $uid
   *   The owner user ID.
   * @param string $client_id
   *   The client id.
   * @param array $record
   *   The stored record.
   *
   * @return \Drupal\jwt_oauth_ccf\ClientCredential
   *   The credential object.
   */
  protected function recordToObject(int $uid, string $client_id, array $record): ClientCredential {
    return new ClientCredential(
      $uid,
      $client_id,
      (string) ($record['label'] ?? ''),
      (string) ($record['secret_hash'] ?? ''),
      (int) ($record['created'] ?? 0),
    );
  }

}
