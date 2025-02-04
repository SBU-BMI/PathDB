<?php

namespace Drupal\redirect_after_login\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class for old settings form url redirection.
 */
class LoginSettingsRedirection extends ControllerBase {

  /**
   * Method for old settings form url redirection.
   */
  public function settingsRedirect() {
    return $this->redirect('redirect_after_login.admin_settings');
  }

}
