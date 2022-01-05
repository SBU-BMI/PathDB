<?php

namespace Drupal\moderated_content_bulk_publish\EventSubscriber;

use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
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

  public function on403(GetResponseForExceptionEvent $event) {
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
      $new_path = str_replace('/latest', '', $current_path);
      \Drupal::logger('moderated_content_bulk_publish')->notice(utf8_encode('HandlerFor403AccessDenied: Redirecting from ' . $current_path . ' to ' . $new_path));
      $base_url = \Drupal::request()->getSchemeAndHttpHost();
      $returnResponse = new \Drupal\Core\Routing\TrustedRedirectResponse($base_url . '/' . $langId . $new_path); // TODO: figure out how to do this the Drupal 8 way for internal path but didn't because of language afterthought in Drupal 8.
      $event->setResponse($returnResponse);

      return;
    }
  }

}
