<?php

namespace Drupal\users_jwt\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\user\UserInterface;
use Drupal\users_jwt\UsersJwtKeyRepositoryInterface;
use Drupal\users_jwt\UsersKey;
use Symfony\Component\HttpFoundation\Request;
use Firebase\JWT\JWT;

/**
 * Authentication provider UsersJwtAuth.
 */
class UsersJwtAuth implements AuthenticationProviderInterface {

  /**
   * The user key repository service.
   *
   * @var \Drupal\users_jwt\UsersJwtKeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The site settings.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a HTTP basic authentication provider object.
   *
   * @param \Drupal\users_jwt\UsersJwtKeyRepositoryInterface $key_repository
   *   The user key repository service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Site\Settings $settings
   *   The site settings.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(UsersJwtKeyRepositoryInterface $key_repository, EntityTypeManagerInterface $entity_type_manager, Settings $settings, LoggerChannelFactoryInterface $logger_factory) {
    $this->keyRepository = $key_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->settings = $settings;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Checks whether suitable authentication credentials are on the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return bool
   *   TRUE if authentication credentials suitable for this provider are on the
   *   request, FALSE otherwise.
   */
  public function applies(Request $request) {
    return (bool) self::getJwtFromRequest($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {
    $raw_jwt = self::getJwtFromRequest($request);
    try {
      // @todo add Ed25519 here as allowed when it's supported. We verify the
      // algorithm from the key matches the header below so we can allow
      // multiple here.
      $payload = JWT::decode($raw_jwt, $this->keyRepository, ['RS256']);
    }
    catch (\Exception $e) {
      return $this->debugLog('JWT decode exception', $e);
    }
    // This approach requires the these two reserved claims. This prevents users
    // from issuing long-lived tokens that could be abused while not going as
    // far as requiring a unique JWT per request.
    // @todo provide a config for maximum token lifetime.
    if (!isset($payload->iat, $payload->exp) || ($payload->exp - $payload->iat > 24 * 3600)) {
      return $this->debugLog('Bad iat, exp claims', NULL, $payload);
    }
    // Unfortunately this JWT implementation does not save or allow the
    // header to be retrieved via a simple method, so we need to decode it
    // again. The decode call above has already validated it.
    $tks = explode('.', $raw_jwt);
    $headb64 = $tks[0];
    $header = JWT::jsonDecode(JWT::urlsafeB64Decode($headb64));
    $key = $this->keyRepository->getKey($header->kid);
    if ($header->alg !== $key->alg) {
      return $this->debugLog('Bad header alg', NULL, $payload, $key);
    }
    /** @var \Drupal\user\UserInterface $user */
    [$user, $reason] = $this->loadUserForJwt($payload);
    if (!$user) {
      return $this->debugLog($reason, NULL, $payload, $key);
    }
    if ($key->uid !== (int) $user->id()) {
      return $this->debugLog('User uid does not match key uid', NULL, $payload, $key, $user);
    }
    return $user;
  }

  /**
   * Find and load the user for a JWT.
   *
   * @todo Unify this code and the code in JwtAuthConsumerSubscriber.
   *
   * @param object $payload
   *   The JWT claims.
   *
   * @return array
   *   The user and reason if no user was loaded.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function loadUserForJwt(object $payload): array {
    foreach (['uid', 'uuid', 'name'] as $id_type) {
      if (isset($payload->drupal->{$id_type})) {
        $id = $payload->drupal->{$id_type};
        break;
      }
    }
    if ($id === NULL) {
      return [
        NULL,
        'No Drupal uid, uuid, or name was provided in the JWT payload.',
      ];
    }
    $user_storage = $this->entityTypeManager->getStorage('user');
    if ($id_type === 'uid') {
      $user = $user_storage->load($id);
    }
    else {
      $user = current($user_storage->loadByProperties([$id_type => $id]));
    }
    if (!$user) {
      return [NULL, 'User does not exist.'];
    }
    if ($user->isBlocked()) {
      return [NULL, 'User is blocked.'];
    }
    return [$user, NULL];
  }

  /**
   * Log the reason that a JWT could not be used to authenticate.
   *
   * @param string $cause
   *   The cause.
   * @param \Exception|null $e
   *   The caught exception.
   * @param \StdClass|null $payload
   *   The payload (claims) from the JWT.
   * @param \Drupal\users_jwt\UsersKey|null $key
   *   The key that was loaded.
   * @param \Drupal\user\UserInterface|null $user
   *   The user related to the key.
   *
   * @return null
   *   NULL to be returned instead of a user.
   */
  protected function debugLog($cause, \Exception $e = NULL, \StdClass $payload = NULL, UsersKey $key = NULL, UserInterface $user = NULL) {
    if ($this->settings::get('jwt.debug_log')) {
      $this->loggerFactory->get('users_jwt')
        ->error('Error authenticating with a JWT "%cause". Exception: "%exception" Payload: "%payload" Key: "%key" User: "%user"', [
          '%cause' => $cause,
          '%exception' => $e ? get_class($e) . ' ' . $e->getMessage() : 'null',
          '%payload' => $payload ? var_export($payload, TRUE) : 'null',
          '%key' => $key ? var_export($key, TRUE) : 'null',
          '%user' => $user ? var_export($user->toArray(), TRUE) : var_export($user, TRUE),
        ]);
    }
    return NULL;
  }

  /**
   * Gets a raw JsonWebToken from the current request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return string|bool
   *   Raw JWT String if on request, false if not.
   */
  public static function getJwtFromRequest(Request $request) {
    $auth_headers = [];
    $auth = $request->headers->get('Authorization');
    if ($auth) {
      $auth_headers[] = $auth;
    }
    // Check a second header used in combination with basic auth.
    $fallback = $request->headers->get('JWT-Authorization');
    if ($fallback) {
      $auth_headers[] = $fallback;
    }
    foreach ($auth_headers as $value) {
      if (preg_match('/^UsersJwt (.+)/', $value, $matches)) {
        return $matches[1];
      }
    }
    return FALSE;
  }

}
