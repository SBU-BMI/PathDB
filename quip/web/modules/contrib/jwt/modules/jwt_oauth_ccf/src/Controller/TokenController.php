<?php

namespace Drupal\jwt_oauth_ccf\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\jwt\JsonWebToken\JsonWebToken;
use Drupal\jwt\Transcoder\JwtTranscoderInterface;
use Drupal\jwt_oauth_ccf\ClientCredentialRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Issues short-lived JWTs for the OAuth 2.0 client credentials grant.
 *
 * The issued token is an ordinary site JWT: it is signed with the site key and
 * validated by the global 'jwt_auth' provider like any other JWT. The claims
 * are stamped directly from the authenticated client's service account, rather
 * than dispatching the JWT GENERATE event, so the token carries exactly the
 * claims this grant intends (identity + lifetime) regardless of which other
 * modules are installed.
 */
class TokenController extends ControllerBase {

  /**
   * Flood control event name for failed token requests.
   */
  protected const FLOOD_NAME = 'jwt_oauth_ccf.failed_token';

  /**
   * Maximum failed attempts allowed per identifier within the window.
   */
  public const FLOOD_THRESHOLD = 20;

  /**
   * Flood window, in seconds.
   */
  protected const FLOOD_WINDOW = 3600;

  /**
   * Lifetime of an issued token, in seconds.
   */
  protected const TOKEN_LIFETIME = 3600;

  /**
   * The client credential repository.
   *
   * @var \Drupal\jwt_oauth_ccf\ClientCredentialRepositoryInterface
   */
  protected ClientCredentialRepositoryInterface $repository;

  /**
   * The JWT transcoder service.
   *
   * @var \Drupal\jwt\Transcoder\JwtTranscoderInterface
   */
  protected JwtTranscoderInterface $transcoder;

  /**
   * The flood service.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected FloodInterface $flood;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * Constructs the controller.
   */
  public function __construct(
    ClientCredentialRepositoryInterface $repository,
    JwtTranscoderInterface $transcoder,
    FloodInterface $flood,
    EntityTypeManagerInterface $entity_type_manager,
    TimeInterface $time,
  ) {
    $this->repository = $repository;
    $this->transcoder = $transcoder;
    $this->flood = $flood;
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('jwt_oauth_ccf.client_repository'),
      $container->get('jwt.transcoder'),
      $container->get('flood'),
      $container->get('entity_type.manager'),
      $container->get('datetime.time'),
    );
  }

  /**
   * Handles a POST to the OAuth token endpoint.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   An RFC 6749 token response, or an error response.
   */
  public function token(Request $request): JsonResponse {
    $grant_type = (string) $request->request->get('grant_type', '');
    if ($grant_type !== 'client_credentials') {
      return $this->error('unsupported_grant_type', 'Only the client_credentials grant is supported.', 400);
    }

    [$client_id, $client_secret] = $this->extractCredentials($request);
    if ($client_id === '' || $client_secret === '') {
      return $this->error('invalid_request', 'Missing client credentials.', 400);
    }

    // Throttle brute-force attempts per client id and per source address.
    $identifier = $client_id . '::' . $request->getClientIp();
    if (!$this->flood->isAllowed(self::FLOOD_NAME, self::FLOOD_THRESHOLD, self::FLOOD_WINDOW, $identifier)) {
      return $this->error('invalid_client', 'Too many failed attempts. Try again later.', 429);
    }

    $client = $this->repository->getClient($client_id);
    if (!$client || !$this->repository->verifySecret($client, $client_secret)) {
      $this->flood->register(self::FLOOD_NAME, self::FLOOD_WINDOW, $identifier);
      return $this->error('invalid_client', 'Client authentication failed.', 401);
    }

    /** @var \Drupal\user\UserInterface|null $user */
    $user = $this->entityTypeManager->getStorage('user')->load($client->uid);
    if (!$user || $user->isBlocked()) {
      $this->flood->register(self::FLOOD_NAME, self::FLOOD_WINDOW, $identifier);
      return $this->error('invalid_client', 'The service account is unavailable.', 401);
    }

    // Build the token directly for the service account. This is the same shape
    // of site JWT that jwt_auth_issuer would produce (iat, exp, drupal.uid), so
    // it validates identically via the 'jwt_auth' provider, but the identity is
    // taken from the authenticated client rather than the current user.
    $now = $this->time->getRequestTime();
    $jwt = new JsonWebToken();
    $jwt->setClaim('iat', $now);
    $jwt->setClaim('exp', $now + self::TOKEN_LIFETIME);
    $jwt->setClaim(['drupal', 'uid'], (int) $user->id());
    $token = $this->transcoder->encode($jwt);

    if (empty($token)) {
      // Misconfiguration (no signing key). Not the client's fault.
      $this->getLogger('jwt_oauth_ccf')->error('Failed to generate a JWT; check the JWT signing key configuration.');
      return $this->error('server_error', 'Unable to issue a token.', 500);
    }

    $this->flood->clear(self::FLOOD_NAME, $identifier);
    $this->getLogger('jwt_oauth_ccf')
      ->info('Token generated for %name via @client.', [
        '%name' => $user->getAccountName(),
        '@client' => $client->clientId,
      ]);

    return $this->tokenResponse($token);
  }

  /**
   * Extracts client_id and client_secret from the request.
   *
   * Supports both the request body (RFC 6749 §2.3.1 form params) and the HTTP
   * Basic Authorization header, preferring the Basic header when present.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   A [client_id, client_secret] tuple of strings.
   */
  protected function extractCredentials(Request $request): array {
    $header = (string) $request->headers->get('Authorization', '');
    if (preg_match('/^Basic\s+(.+)$/i', $header, $matches)) {
      $decoded = base64_decode($matches[1], TRUE);
      if ($decoded !== FALSE && str_contains($decoded, ':')) {
        [$id, $secret] = explode(':', $decoded, 2);
        // Per RFC 6749, the parts are form-urlencoded before base64 encoding.
        return [urldecode($id), urldecode($secret)];
      }
    }
    return [
      (string) $request->request->get('client_id', ''),
      (string) $request->request->get('client_secret', ''),
    ];
  }

  /**
   * Builds a successful token response.
   *
   * @param string $token
   *   The encoded JWT.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response.
   */
  protected function tokenResponse(string $token): JsonResponse {
    $response = new JsonResponse([
      'access_token' => $token,
      'token_type' => 'Bearer',
      'expires_in' => self::TOKEN_LIFETIME,
    ]);
    // RFC 6749 §5.1: token responses must not be cached.
    $response->headers->set('Cache-Control', 'no-store');
    $response->headers->set('Pragma', 'no-cache');
    return $response;
  }

  /**
   * Builds an RFC 6749 §5.2 error response.
   *
   * @param string $error
   *   The error code.
   * @param string $description
   *   A human-readable description.
   * @param int $status
   *   The HTTP status code.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The error response.
   */
  protected function error(string $error, string $description, int $status): JsonResponse {
    $response = new JsonResponse([
      'error' => $error,
      'error_description' => $description,
    ], $status);
    $response->headers->set('Cache-Control', 'no-store');
    $response->headers->set('Pragma', 'no-cache');
    return $response;
  }

}
