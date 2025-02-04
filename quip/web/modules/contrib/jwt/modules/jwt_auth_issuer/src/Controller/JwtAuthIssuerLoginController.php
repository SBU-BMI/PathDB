<?php

namespace Drupal\jwt_auth_issuer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\jwt\Authentication\Provider\JwtAuth;
use Drupal\user\Controller\UserAuthenticationController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * JWT Auth Issuer Login Controller adds a JSON-encoded JWT to user.login.http.
 */
class JwtAuthIssuerLoginController extends ControllerBase {

  /**
   * The JWT Auth Service.
   *
   * @var \Drupal\jwt\Authentication\Provider\JwtAuth
   */
  private JwtAuth $auth;

  /**
   * The user authentication controller to wrap.
   *
   * @var \Drupal\user\Controller\UserAuthenticationController
   */
  private UserAuthenticationController $userController;

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  private Serializer $serializer;

  /**
   * Constructs a JwtAuthIssuerLoginController.
   *
   * @param \Drupal\jwt\Authentication\Provider\JwtAuth $auth
   *   The JWT auth service.
   * @param \Symfony\Component\Serializer\Serializer $serializer
   *   The serializer.
   * @param \Drupal\user\Controller\UserAuthenticationController $userController
   *   The user authentication controller.
   */
  public function __construct(JwtAuth $auth, Serializer $serializer, UserAuthenticationController $userController) {
    $this->auth = $auth;
    $this->serializer = $serializer;
    $this->userController = $userController;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $auth = $container->get('jwt.authentication.jwt');
    if ($container->hasParameter('serializer.formats') && $container->has('serializer')) {
      $serializer = $container->get('serializer');
    }
    else {
      $serializer = new Serializer([], [new JsonEncoder()]);
    }
    return new static($auth, $serializer, UserAuthenticationController::create($container));
  }

  /**
   * Logs in a user.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response which contains the ID, CSRF token, and JWT token.
   */
  public function login(Request $request): Response {
    $response = $this->userController->login($request);

    // Ensure not error response.
    if ($response->getStatusCode() !== 200) {
      return $response;
    }

    // Note that the call to UserAuthenticationController::login() will have
    // already validated that the format is supported.
    $format = $request->getRequestFormat();

    // Decode and add JWT token.
    if ($content = $response->getContent()) {
      if ($decoded = $this->serializer->decode($content, $format)) {
        // Add JWT access_token.
        $decoded['access_token'] = $this->auth->generateToken();
        // Re-encode data with the JWT token added.
        $response->setContent($this->serializer->encode($decoded, $format));
      }
    }

    return $response;
  }

}
