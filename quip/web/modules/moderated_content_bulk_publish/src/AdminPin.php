<?php
namespace Drupal\moderated_content_bulk_publish;

use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\moderated_content_bulk_publish\AdminPin;

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
      \Drupal::logger('ADMIN_PIN_UNPIN')->notice(utf8_encode('Unpin action in moderated_content_bulk_publish'));
      //\Drupal::Messenger()->addStatus(utf8_encode('Unpin action in moderated_content_bulk_publish'));
      $entity_manager = \Drupal::entityTypeManager();
      $this->entity->setSticky(FALSE);
      if ($this->entity instanceof RevisionLogInterface) {
        $this->entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
        $msg = 'Bulk operation unpin content';
        $this->entity->setRevisionLogMessage($msg);
        $current_uid = \Drupal::currentUser()->id();
        $this->entity->setRevisionUserId($current_uid);
      }
      $this->entity->save();
      if ($this->entity->isSticky()) {
        $this->entity = $entity_manager->getStorage($this->entity->getEntityTypeId())->load($this->nid);
        $this->entity->setSticky(FALSE);
        if ($this->entity instanceof RevisionLogInterface) {
          $this->entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
          $msg = 'Bulk operation unpin content';
          $this->entity->setRevisionLogMessage($msg);
          $current_uid = \Drupal::currentUser()->id();
          $this->entity->setRevisionUserId($current_uid);
        }
        $this->entity->save();
        $this->entity = $entity_manager->getStorage($this->entity->getEntityTypeId())->load($this->nid);
      }
      return $this->entity;
    }

    /**
     * Pin Content.
     */
    public function pin() {
      \Drupal::logger('ADMIN_PIN_UNPIN')->notice(utf8_encode('Unpin action in moderated_content_bulk_publish'));
      //\Drupal::Messenger()->addStatus(utf8_encode('Unpin action in moderated_content_bulk_publish'));
      $entity_manager = \Drupal::entityTypeManager();
      $this->entity->setSticky(TRUE);
      if ($this->entity instanceof RevisionLogInterface) {
        $this->entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
        $msg = 'Bulk operation pin content';
        $this->entity->setRevisionLogMessage($msg);
        $current_uid = \Drupal::currentUser()->id();
        $this->entity->setRevisionUserId($current_uid);
      }
      $this->entity->save();
      if (!$this->entity->isSticky()) {
        $this->entity = $entity_manager->getStorage($this->entity->getEntityTypeId())->load($this->nid);
        $this->entity->setSticky(TRUE);
        if ($this->entity instanceof RevisionLogInterface) {
          $this->entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
          $msg = 'Bulk operation pin content';
          $this->entity->setRevisionLogMessage($msg);
          $current_uid = \Drupal::currentUser()->id();
          $this->entity->setRevisionUserId($current_uid);
        }
        $this->entity->save();
        $this->entity = $entity_manager->getStorage($this->entity->getEntityTypeId())->load($this->nid);
      }
      return $this->entity;
    }

    /**
     * 
     */
    private function privateSomething() {
        //not sure if need to update both translations or just one
        $this->entity->getTranslation("en")->set('moderation_state', 'published');
        $this->entity->getTranslation("fr")->set('moderation_state', 'published');
        $this->entity->save(); //not needed?
    }
}
