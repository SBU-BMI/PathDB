<?php

namespace Drupal\moderated_content_bulk_publish\EventSubscriber;

use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

class HandlerFor403AccessDenied extends HttpExceptionSubscriberBase {

  protected $currentUser;

  public function __construct(AccountInterface $current_user) {
    $this->currentUser = $current_user;
  }

  protected function getHandledFormats() {
    return ['html'];
  }

  public function on403(ExceptionEvent $event) {
    $base_path = \Drupal::request()->getBasePath();
    $request = $event->getRequest();
    $is_anonymous = $this->currentUser->isAnonymous();
    $route_name = $request->attributes->get('_route');
    $is_not_login = $route_name != 'user.login';
    $current_path = \Drupal::service('path.current')->getPath();

    if (stripos($current_path, 'latest') > 1 && stripos($current_path, 'ode') > 0 ) {
      $langId = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $new_path = $current_path;
      // Fait de la magique ici.
      // Latest revision doesn't exist in this language, redirect to node page.
      $new_path = $base_path . str_replace('/latest', '', $current_path);
      \Drupal::logger('moderated_content_bulk_publish')->notice(mb_convert_encoding('HandlerFor403AccessDenied: Redirecting from ' . $current_path . ' to ' . $new_path, 'UTF-8'));
      $base_url = \Drupal::request()->getSchemeAndHttpHost();
      $parts = array_filter(explode('/', \Drupal::request()->getRequestUri()));
      $has_prefix = count($parts) && $parts[1] == $langId;

      // Check if the language prefix exist in url.
      if ($has_prefix) {
        // TODO: figure out how to do this the Drupal 8 way for internal path but didn't because of language afterthought in Drupal 8.
        $returnResponse = new TrustedRedirectResponse($base_url . '/' . $langId . $new_path);
      }
      else {
        $returnResponse = new TrustedRedirectResponse($base_url . '/' . $new_path);
      }
      $event->setResponse($returnResponse);

      return;
    }
  }

}
