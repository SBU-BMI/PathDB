<?php
namespace Drupal\moderated_content_bulk_publish;

use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\moderated_content_bulk_publish\AdminHelper;

/**
 * A Helper Class to assist with the pin and unpin bulk action.
 *   - Called by Pin and Unpin Content Bulk Operations
 *   - Easy one-stop shop to make modifications to these bulk actions.
 */
class AdminPin
{
    //set this to true to send to $testEmailList
    private $testMode = false;
    private $entity = null;
    private $nid = 0;
    private $status = 0; // Default is 0, unpin.

    public function __construct($entity, $status)
    {
        $this->entity = $entity;
	if (!is_null($status)) {
          $this->status = $status;
        }
        $this->nid = $this->entity->id();
    }

    /**
     * Unpin current revision.
     */
    public function unpin() {
      $user = \Drupal::currentUser();
      \Drupal::logger('PIN_UNPIN')->notice(utf8_encode('Unpin action in moderated_content_bulk_publish'));
      $allLanguages = AdminHelper::getAllEnabledLanguages();
      foreach ($allLanguages as $langcode => $languageName) {
        if ($this->entity->hasTranslation($langcode)) {
          $this->entity = $this->entity->getTranslation($langcode);
          $this->entity->setSticky(FALSE);
          if ($this->entity instanceof RevisionLogInterface) {
            $this->entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
            $msg = 'Bulk operation unpin content';
            $this->entity->setRevisionLogMessage($msg);
            $current_uid = \Drupal::currentUser()->id();
            $this->entity->setRevisionUserId($current_uid);
          }
          if ($user->hasPermission('moderated content bulk unpin content')) {
            $this->entity->save();
          }
          else {
            \Drupal::logger('moderated_content_bulk_publish')->notice(
              utf8_encode("Bulk unpin not permitted, check permissions.")
            );
          }
          if ($this->entity->isSticky()) {
            $entity_manager = \Drupal::entityTypeManager();
            $this->entity = $entity_manager->getStorage($this->entity->getEntityTypeId())->load($this->nid);
            $this->entity->setSticky(FALSE);
            if ($this->entity instanceof RevisionLogInterface) {
              $this->entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
              $msg = 'Bulk operation unpin content';
              $this->entity->setRevisionLogMessage($msg);
              $current_uid = \Drupal::currentUser()->id();
              $this->entity->setRevisionUserId($current_uid);
            }
            if ($user->hasPermission('moderated content bulk unpin content')) {
              $this->entity->save();
            }
            else {
              \Drupal::logger('moderated_content_bulk_publish')->notice(
                utf8_encode("Bulk unpin not permitted, check permissions.")
              );
            }
          }
        }
      }
      return $this->entity;
    }

    /**
     * Pin Content.
     */
    public function pin() {
      $user = \Drupal::currentUser();
      \Drupal::logger('PIN_UNPIN')->notice(utf8_encode('Pin action in moderated_content_bulk_publish'));
      $allLanguages = AdminHelper::getAllEnabledLanguages();
      foreach ($allLanguages as $langcode => $languageName) {
        if ($this->entity->hasTranslation($langcode)) {
          $this->entity = $this->entity->getTranslation($langcode);
          $this->entity->setSticky(TRUE);
          if ($this->entity instanceof RevisionLogInterface) {
            $this->entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
            $msg = 'Bulk operation pin content';
            $this->entity->setRevisionLogMessage($msg);
            $current_uid = \Drupal::currentUser()->id();
            $this->entity->setRevisionUserId($current_uid);
          }
          if ($user->hasPermission('moderated content bulk pin content')) {
            $this->entity->save();
          }
          else {
            \Drupal::logger('moderated_content_bulk_publish')->notice(
              utf8_encode("Bulk pin not permitted, check permissions.")
            );
          }
          if (!$this->entity->isSticky()) {
            $entity_manager = \Drupal::entityTypeManager();
            $this->entity = $entity_manager->getStorage($this->entity->getEntityTypeId())->load($this->nid);
            $this->entity->setSticky(TRUE);
            if ($this->entity instanceof RevisionLogInterface) {
              $this->entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
              $msg = 'Bulk operation pin content';
              $this->entity->setRevisionLogMessage($msg);
              $current_uid = \Drupal::currentUser()->id();
              $this->entity->setRevisionUserId($current_uid);
            }
            if ($user->hasPermission('moderated content bulk pin content')) {
              $this->entity->save();
            }
            else {
              \Drupal::logger('moderated_content_bulk_publish')->notice(
                utf8_encode("Bulk pin not permitted, check permissions.")
              );
            }
            $this->entity = $entity_manager->getStorage($this->entity->getEntityTypeId())->load($this->nid);
          }
        }
      }
      return $this->entity;
    }

}
