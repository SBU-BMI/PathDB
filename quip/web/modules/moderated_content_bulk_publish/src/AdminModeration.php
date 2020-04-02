<?php
namespace Drupal\moderated_content_bulk_publish;

use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\moderated_content_bulk_publish\AdminModeration;

/**
 * A Helper Class to assist with the publishing and unpublishing bulk action.
 *   - Called by Publish Latest Revision and Unpublish Current Revision Bulk Operations
 *   - Easy one-stop shop to make modifications to these bulk actions.
 */
class AdminModeration
{
    //set this to true to send to $testEmailList
    private $testMode = false;
    private $entity = null;
    private $nid = 0;
    private $status = 0; // Default is 0, unpublish.

    public function __construct($entity, $status)
    {
        $this->entity = $entity;
	if (!is_null($status)) {
          $this->status = $status;
        }
        $this->nid = $this->entity->id();
    }

    /**
     * Unpublish current revision.
     */
    public function unpublish() {
      \Drupal::logger('moderated_content_bulk_publish')->notice(utf8_encode('Unpublish action in moderated_content_bulk_publish'));
      //\Drupal::Messenger()->addStatus(utf8_encode('Unpublish action in moderated_content_bulk_publish'));
      $entity_manager = \Drupal::entityTypeManager();
      $this->entity->set('moderation_state', 'archived');
      if ($this->entity->hasTranslation('en')) {
        $this->entity = $this->entity->getTranslation("en");
        $this->entity->set('moderation_state', 'archived');
        //$this->entity->getTranslation("en")->set('moderation_state', 'archived');
      }
      if ($this->entity instanceof RevisionLogInterface) {
        // $now = time();
        $this->entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
        $msg = 'Bulk operation create archived revision';
        $this->entity->setRevisionLogMessage($msg);
        $current_uid = \Drupal::currentUser()->id();
        $this->entity->setRevisionUserId($current_uid);
      }
      $this->entity->save();
      if ($this->entity->hasTranslation('fr')) {
        // Now do french archived.
        $this->entity = $this->entity->getTranslation("fr");
        if ($this->entity instanceof RevisionLogInterface) {
          $this->entity->getTranslation("fr")->set('moderation_state', 'archived');
          //$this->entity->set('moderation_state', 'archived');
          // $now = time();
          $this->entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
          $msg = 'Bulk operation create archived revision french';
          $this->entity->setRevisionLogMessage($msg);
          $current_uid = \Drupal::currentUser()->id();
          $this->entity->setRevisionUserId($current_uid);
        }
        $this->entity->save();
      }
      $this->entity->set('moderation_state', 'draft');
      if ($this->entity->hasTranslation('en')) {
        $this->entity = $this->entity->getTranslation("en");
        $this->entity->set('moderation_state', 'draft');
        //$this->entity->getTranslation("en")->set('moderation_state', 'draft');
      }
      if ($this->entity instanceof RevisionLogInterface) {
        // $now = time();
        $this->entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
        $msg = 'Bulk operation create draft revision';
        $this->entity->setRevisionLogMessage($msg);
        $current_uid = \Drupal::currentUser()->id();
        $this->entity->setRevisionUserId($current_uid);
      }
      $this->entity->save();
      if ($this->entity->hasTranslation('fr')) {
        // Now do french draft.
        $this->entity = $this->entity->getTranslation("fr");
        $this->entity->set('moderation_state', 'draft');
        $this->entity->getTranslation("fr")->set('moderation_state', 'draft');
        if ($this->entity instanceof RevisionLogInterface) {
          // $now = time();
          $this->entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
          $msg = 'Bulk operation create draft revision french';
          $this->entity->setRevisionLogMessage($msg);
          $current_uid = \Drupal::currentUser()->id();
          $this->entity->setRevisionUserId($current_uid);
        }
        $this->entity->save();
        //$this->entity = $entity_manager->getStorage($this->entity->getEntityTypeId())->load($this->nid);
      }
      return $this->entity;
    }

    /**
     * Publish Latest Revision.
     */
    public function publish() {
      \Drupal::logger('moderated_content_bulk_publish')->notice(utf8_encode('Publish latest revision bulk operation'));
      //\Drupal::Messenger()->addStatus(utf8_encode('Publish latest revision bulk operation'));
      $entity_manager = \Drupal::entityTypeManager();
      $this->entity->set('moderation_state', 'published');
      if ($this->entity instanceof RevisionLogInterface) {
        // $now = time();
        $this->entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
        $msg = 'Bulk operation publish revision';
        $this->entity->setRevisionLogMessage($msg);
        $current_uid = \Drupal::currentUser()->id();
        $this->entity->setRevisionUserId($current_uid);
      }
      $this->entity->save();
      if ($this->entity->hasTranslation('fr')) {
        $this->entity = $this->entity->getTranslation("fr");
        $this->entity->set('moderation_state', 'published');
        if ($this->entity instanceof RevisionLogInterface) {
          // $now = time();
          $this->entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
          $msg = 'Bulk operation publish revision french';
          $this->entity->setRevisionLogMessage($msg);
          $current_uid = \Drupal::currentUser()->id();
          $this->entity->setRevisionUserId($current_uid);
        }
        $this->entity->save();
        $entity_manager = \Drupal::entityTypeManager();
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
