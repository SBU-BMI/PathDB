<?php

namespace Drupal\Tests\jwt_oauth_ccf\Kernel;

use Drupal\jwt_oauth_ccf\ClientCredentialRepositoryInterface;
use Drupal\jwt_oauth_ccf\Controller\TokenController;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the OAuth token endpoint controller.
 *
 * @coversDefaultClass \Drupal\jwt_oauth_ccf\Controller\TokenController
 *
 * @group jwt
 */
class TokenControllerTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'key',
    'jwt',
    'jwt_auth_consumer',
    'jwt_test',
    'jwt_oauth_ccf',
  ];

  /**
   * The credential repository.
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
    // jwt_test provides a working HMAC signing key and the jwt config.
    $this->installConfig(['field', 'key', 'jwt', 'jwt_test']);
    $this->repository = $this->container->get('jwt_oauth_ccf.client_repository');
  }

  /**
   * Builds the controller from the container.
   */
  protected function controller(): TokenController {
    return TokenController::create($this->container);
  }

  /**
   * Builds a POST request to the token endpoint with body parameters.
   */
  protected function request(array $params): Request {
    return Request::create('/oauth2/token', 'POST', $params);
  }

  /**
   * Decodes a JSON response body to an array.
   */
  protected function decode(JsonResponse $response): array {
    return json_decode($response->getContent(), TRUE);
  }

  /**
   * @covers ::token
   */
  public function testSuccessfulTokenRequest(): void {
    $account = $this->createUser(['access content']);
    $client = $this->repository->createClient((int) $account->id(), 'Label');
    $secret = $client->secret;

    $response = $this->controller()->token($this->request([
      'grant_type' => 'client_credentials',
      'client_id' => $client->clientId,
      'client_secret' => $secret,
    ]));

    $this->assertSame(200, $response->getStatusCode());
    // RFC 6749 §5.1: token responses must not be cached.
    $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

    $data = $this->decode($response);
    $this->assertSame('Bearer', $data['token_type']);
    $this->assertNotEmpty($data['access_token']);
    $this->assertGreaterThan(0, $data['expires_in']);

    // The issued token authenticates as the service account.
    /** @var \Drupal\jwt\Transcoder\JwtTranscoderInterface $transcoder */
    $transcoder = $this->container->get('jwt.transcoder');
    $decoded = $transcoder->decode($data['access_token']);
    $this->assertEquals($account->id(), $decoded->getClaim(['drupal', 'uid']));
  }

  /**
   * @covers ::extractCredentials
   */
  public function testBasicAuthHeaderCredentials(): void {
    $account = $this->createUser(['access content']);
    $client = $this->repository->createClient((int) $account->id(), 'Label');

    $request = $this->request(['grant_type' => 'client_credentials']);
    $request->headers->set(
      'Authorization',
      'Basic ' . base64_encode($client->clientId . ':' . $client->secret)
    );

    $response = $this->controller()->token($request);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('Bearer', $this->decode($response)['token_type']);
  }

  /**
   * A secret with reserved characters round-trips via form url encoding.
   *
   * Per RFC 6749 §2.3.1 the client_id and client_secret are form-urlencoded
   * before being base64-encoded into the Basic header, so the controller must
   * urldecode each part. A secret containing '+', ' ' and '%' only survives if
   * that decode happens (an unencoded '+' would otherwise decode to a space).
   *
   * @covers ::extractCredentials
   */
  public function testUrlEncodedBasicAuthCredentials(): void {
    $account = $this->createUser(['access content']);
    $secret = 'a b+c%d:e';
    $client = $this->repository->createClient((int) $account->id(), 'Label', $secret);

    // The client encodes each part, exactly as a conforming client would.
    $request = $this->request(['grant_type' => 'client_credentials']);
    $request->headers->set(
      'Authorization',
      'Basic ' . base64_encode(urlencode($client->clientId) . ':' . urlencode($secret))
    );
    $response = $this->controller()->token($request);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('Bearer', $this->decode($response)['token_type']);

    // The same secret sent without encoding is corrupted by the decode ('+'
    // becomes a space), so authentication fails: proof the decode matters.
    $request = $this->request(['grant_type' => 'client_credentials']);
    $request->headers->set(
      'Authorization',
      'Basic ' . base64_encode($client->clientId . ':' . $secret)
    );
    $response = $this->controller()->token($request);
    $this->assertSame(401, $response->getStatusCode());
    $this->assertSame('invalid_client', $this->decode($response)['error']);
  }

  /**
   * @covers ::token
   */
  public function testUnsupportedGrantType(): void {
    $response = $this->controller()->token($this->request([
      'grant_type' => 'password',
      'client_id' => 'x',
      'client_secret' => 'y',
    ]));
    $this->assertSame(400, $response->getStatusCode());
    $this->assertSame('unsupported_grant_type', $this->decode($response)['error']);
  }

  /**
   * @covers ::token
   */
  public function testMissingCredentials(): void {
    $response = $this->controller()->token($this->request([
      'grant_type' => 'client_credentials',
    ]));
    $this->assertSame(400, $response->getStatusCode());
    $this->assertSame('invalid_request', $this->decode($response)['error']);
  }

  /**
   * @covers ::token
   */
  public function testUnknownClient(): void {
    $response = $this->controller()->token($this->request([
      'grant_type' => 'client_credentials',
      'client_id' => 'ccf_unknown',
      'client_secret' => 'whatever-secret-here',
    ]));
    $this->assertSame(401, $response->getStatusCode());
    $this->assertSame('invalid_client', $this->decode($response)['error']);
  }

  /**
   * @covers ::token
   */
  public function testWrongSecret(): void {
    $account = $this->createUser(['access content']);
    $client = $this->repository->createClient((int) $account->id(), 'Label');

    $response = $this->controller()->token($this->request([
      'grant_type' => 'client_credentials',
      'client_id' => $client->clientId,
      'client_secret' => 'definitely-not-the-secret',
    ]));
    $this->assertSame(401, $response->getStatusCode());
    $this->assertSame('invalid_client', $this->decode($response)['error']);
  }

  /**
   * @covers ::token
   */
  public function testBlockedServiceAccount(): void {
    $account = $this->createUser(['access content']);
    $account->block()->save();
    $client = $this->repository->createClient((int) $account->id(), 'Label');

    $response = $this->controller()->token($this->request([
      'grant_type' => 'client_credentials',
      'client_id' => $client->clientId,
      'client_secret' => $client->secret,
    ]));
    $this->assertSame(401, $response->getStatusCode());
    $this->assertSame('invalid_client', $this->decode($response)['error']);
  }

  /**
   * Repeated failures are throttled by flood control.
   *
   * @covers ::token
   */
  public function testFloodThrottling(): void {
    $account = $this->createUser(['access content']);
    $client = $this->repository->createClient((int) $account->id(), 'Label');
    $bad = [
      'grant_type' => 'client_credentials',
      'client_id' => $client->clientId,
      'client_secret' => 'wrong-secret-value-here',
    ];

    // The threshold is the number of failures allowed within the window; the
    // request that trips the limit is rejected with 429 before authentication.
    for ($i = 0; $i < TokenController::FLOOD_THRESHOLD; $i++) {
      $response = $this->controller()->token($this->request($bad));
      $this->assertSame(401, $response->getStatusCode());
    }
    $response = $this->controller()->token($this->request($bad));
    $this->assertSame(429, $response->getStatusCode());
    $this->assertSame('invalid_client', $this->decode($response)['error']);

    // A valid credential from the same client id/IP is also blocked while the
    // flood window is open.
    $response = $this->controller()->token($this->request([
      'grant_type' => 'client_credentials',
      'client_id' => $client->clientId,
      'client_secret' => $client->secret,
    ]));
    $this->assertSame(429, $response->getStatusCode());
  }

  /**
   * A successful request clears the failure counter.
   *
   * @covers ::token
   */
  public function testSuccessClearsFlood(): void {
    $account = $this->createUser(['access content']);
    $client = $this->repository->createClient((int) $account->id(), 'Label');

    // A few failures, then a success, then more failures: the success resets
    // the counter so the threshold is not reached.
    for ($i = 0; $i < 5; $i++) {
      $this->controller()->token($this->request([
        'grant_type' => 'client_credentials',
        'client_id' => $client->clientId,
        'client_secret' => 'wrong-secret-value-here',
      ]));
    }
    $ok = $this->controller()->token($this->request([
      'grant_type' => 'client_credentials',
      'client_id' => $client->clientId,
      'client_secret' => $client->secret,
    ]));
    $this->assertSame(200, $ok->getStatusCode());
    for ($i = 0; $i < 5; $i++) {
      $response = $this->controller()->token($this->request([
        'grant_type' => 'client_credentials',
        'client_id' => $client->clientId,
        'client_secret' => 'wrong-secret-value-here',
      ]));
      $this->assertSame(401, $response->getStatusCode());
    }
  }

}
