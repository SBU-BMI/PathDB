<?php

namespace Drupal\user_current_paths\Controller;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Path\PathValidator;

/**
 * Defines the User Current Paths controller.
 */
class UserCurrentPathsController extends ControllerBase {

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidator
   */
  protected $pathValidator;

  /**
   * Constructs a UserCurrentPathsController object.
   *
   * @param \Drupal\Core\Path\PathValidator $path_validator
   *   The path validator.
   */
  public function __construct(PathValidator $path_validator) {
    $this->pathValidator = $path_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('path.validator')
    );
  }

  /**
   * Handles wildcard (user/current/*) redirects for the current user.
   *
   * Replaces the second "current" parameter in the URL with the currently
   * logged in user and redirects to the target if the resulting path is valid.
   * Ohterwise throws a NotFoundHttpException. This is safe because the redirect
   * is handled as if the user entered the URL manually with all security
   * checks.
   *
   * @param string $wildcardaction
   *   The wildcart action.
   */
  public function wildcardActionRedirect(string $wildcardaction) {
    $path = '/user/' . $this->currentUser()->id();
    if ($wildcardaction != 'view') {
      // /view doesn't exist for user entities
      $path .= '/' . $wildcardaction;
    }

    $url = $this->pathValidator->getUrlIfValid($path);
    if ($url !== FALSE) {
      // Valid internal path:
      return $this->redirect($url->getRouteName(), $url->getRouteParameters(), $url->getOptions());
    }

    throw new NotFoundHttpException();
  }

  /**
   * Handles redirects to the user edit page for the currently logged in user.
   */
  public function editRedirect() {
    $route_name = 'entity.user.edit_form';
    $route_parameters = [
      'user' => $this->currentUser()->id(),
    ];

    return $this->redirect($route_name, $route_parameters);
  }

}
