<?php

namespace Drupal\Tests\jwt_oauth_ccf\Kernel;

use Drupal\jwt_oauth_ccf\ClientCredentialRepositoryInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the OAuth client credential repository.
 *
 * @coversDefaultClass \Drupal\jwt_oauth_ccf\ClientCredentialRepository
 *
 * @group jwt
 */
class ClientCredentialRepositoryTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'jwt_oauth_ccf',
  ];

  /**
   * The repository under test.
   *
   * @var \Drupal\jwt_oauth_ccf\ClientCredentialRepositoryInterface
   */
  protected ClientCredentialRepositoryInterface $repository;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->repository = $this->container->get('jwt_oauth_ccf.client_repository');
  }

  /**
   * @covers ::createClient
   * @covers ::verifySecret
   */
  public function testCreateWithGeneratedSecret(): void {
    $client = $this->repository->createClient(42, 'Integration A');

    $this->assertSame(42, $client->uid);
    $this->assertSame('Integration A', $client->label);
    $this->assertStringStartsWith('ccf_', $client->clientId);
    $this->assertGreaterThan(0, $client->created);

    // The plaintext is surfaced exactly once, on the generated credential.
    $this->assertNotNull($client->secret);
    $this->assertNotSame('', $client->secret);

    // Only a hash is stored, never the plaintext.
    $this->assertNotSame($client->secret, $client->secretHash);
    $this->assertNotSame('', $client->secretHash);

    // The generated secret verifies; a wrong one does not.
    $this->assertTrue($this->repository->verifySecret($client, $client->secret));
    $this->assertFalse($this->repository->verifySecret($client, $client->secret . 'x'));
    $this->assertFalse($this->repository->verifySecret($client, ''));
  }

  /**
   * @covers ::createClient
   */
  public function testCreateWithSuppliedSecret(): void {
    $secret = 'a-supplied-secret-value';
    $client = $this->repository->createClient(7, 'Integration B', $secret);

    // A caller-supplied secret is never echoed back on ->secret.
    $this->assertNull($client->secret);
    // But it is still hashed and verifiable.
    $this->assertTrue($this->repository->verifySecret($client, $secret));
    $this->assertNotSame($secret, $client->secretHash);
  }

  /**
   * @covers ::createClient
   */
  public function testCreateWithSuppliedClientId(): void {
    $client = $this->repository->createClient(9, 'Integration C', 'a-supplied-secret-value', 'my-custom-id');

    $this->assertSame('my-custom-id', $client->clientId);
    $this->assertSame(9, $client->uid);
    // The credential is retrievable under the supplied id.
    $loaded = $this->repository->getClient('my-custom-id');
    $this->assertNotNull($loaded);
    $this->assertSame('Integration C', $loaded->label);
  }

  /**
   * @covers ::createClient
   */
  public function testCreateWithDuplicateClientIdThrows(): void {
    $this->repository->createClient(9, 'First', 'a-supplied-secret-value', 'shared-id');

    $this->expectException(\InvalidArgumentException::class);
    $this->repository->createClient(10, 'Second', 'another-secret-value', 'shared-id');
  }

  /**
   * @covers ::getClient
   */
  public function testGetClient(): void {
    $client = $this->repository->createClient(5, 'Label', 'the-secret-value-1');

    $loaded = $this->repository->getClient($client->clientId);
    $this->assertNotNull($loaded);
    $this->assertSame($client->clientId, $loaded->clientId);
    $this->assertSame(5, $loaded->uid);
    $this->assertSame('Label', $loaded->label);
    // A reloaded credential never carries the plaintext.
    $this->assertNull($loaded->secret);
    $this->assertTrue($this->repository->verifySecret($loaded, 'the-secret-value-1'));

    // Unknown and empty ids resolve to nothing.
    $this->assertNull($this->repository->getClient('ccf_does_not_exist'));
    $this->assertNull($this->repository->getClient(''));
  }

  /**
   * @covers ::getUserClients
   */
  public function testGetUserClients(): void {
    $this->repository->createClient(10, 'One', 'secret-for-client-one');
    $this->repository->createClient(10, 'Two', 'secret-for-client-two');
    $this->repository->createClient(11, 'Other', 'secret-for-other-user');

    $clients = $this->repository->getUserClients(10);
    $this->assertCount(2, $clients);
    foreach ($clients as $client_id => $client) {
      $this->assertSame($client_id, $client->clientId);
      $this->assertSame(10, $client->uid);
    }

    $this->assertCount(1, $this->repository->getUserClients(11));
    $this->assertCount(0, $this->repository->getUserClients(999));
  }

  /**
   * @covers ::deleteClient
   */
  public function testDeleteClient(): void {
    $a = $this->repository->createClient(1, 'A', 'secret-value-for-a-1');
    $b = $this->repository->createClient(1, 'B', 'secret-value-for-b-1');

    $this->repository->deleteClient($a->clientId);

    $this->assertNull($this->repository->getClient($a->clientId));
    // Deleting one leaves the sibling intact.
    $this->assertNotNull($this->repository->getClient($b->clientId));
  }

  /**
   * @covers ::deleteUserClients
   */
  public function testDeleteUserClients(): void {
    $this->repository->createClient(20, 'One', 'secret-value-user20-a');
    $this->repository->createClient(20, 'Two', 'secret-value-user20-b');
    $kept = $this->repository->createClient(21, 'Keep', 'secret-value-user21-a');

    $this->repository->deleteUserClients(20);

    $this->assertCount(0, $this->repository->getUserClients(20));
    // Another user's credentials are untouched.
    $this->assertNotNull($this->repository->getClient($kept->clientId));
  }

  /**
   * Deleting an account removes its credentials via hook_user_delete().
   */
  public function testUserDeleteRemovesClients(): void {
    $account = $this->createUser();
    $client = $this->repository->createClient((int) $account->id(), 'Label', 'secret-value-for-hook');
    $this->assertNotNull($this->repository->getClient($client->clientId));

    $account->delete();

    $this->assertNull($this->repository->getClient($client->clientId));
  }

}
