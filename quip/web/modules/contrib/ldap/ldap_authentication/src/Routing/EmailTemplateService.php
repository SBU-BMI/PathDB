<?php

declare(strict_types = 1);

namespace Drupal\ldap_authentication\Routing;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Email template service.
 *
 * @package Drupal\ldap_authentication
 */
class EmailTemplateService implements EventSubscriberInterface {

  /**
   * Config.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * Constructor.
   */
  public function __construct(ConfigFactory $config_factory) {
    $this->config = $config_factory->get('ldap_authentication.settings');
  }

  /**
   * Check for template if enabled.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   Response event.
   */
  public function checkTemplate(GetResponseEvent $event): void {
    if ($this->config->get('emailTemplateUsagePromptUser') === TRUE) {
      $this->checkForEmailTemplate();
    }

  }

  /**
   * Form submit callback to check for an email template and redirect if needed.
   */
  public static function checkForEmailTemplate(): void {
    if (self::profileNeedsUpdate()) {
      $url = Url::fromRoute('ldap_authentication.profile_update_form');
      // Not injected since we need to have this callback be static.
      $currentRoute = \Drupal::service('path.current')->getPath();
      if ($currentRoute !== '/user/ldap-profile-update' && $currentRoute !== '/user/logout') {
        $response = new RedirectResponse($url->toString());
        $response->send();
      }
    }
  }

  /**
   * Whether or not the user's profile is valid or needs to be updated on login.
   *
   * Currently this only checks if mail is valid or not according to the
   * authentication settings.
   *
   * @return bool
   *   TRUE if the user's profile is valid, otherwise FALSE.
   *
   * @todo This should not be called statically, so that we don't call
   * all these services without DI.
   */
  public static function profileNeedsUpdate(): bool {
    $proxy = \Drupal::currentUser();
    $result = FALSE;

    // We only want non-anonymous and non-1 users.
    // @todo Role exclusion needs to be checked, user 1 special case removed.
    if ($proxy->id() !== 1 && $proxy->isAuthenticated()) {
      $user = User::load($proxy->id());
      $regex = \Drupal::config('ldap_authentication.settings')
        ->get('emailTemplateUsagePromptRegex');

      $regex = sprintf('`%s`i', $regex);
      $mail = $user->get('mail')->value ?: '';
      if (preg_match($regex, $mail)) {
        $result = TRUE;
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::REQUEST][] = ['checkTemplate', 30];
    return $events;
  }

}
