<?php
namespace Drupal\moderated_content_bulk_publish;

use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\moderated_content_bulk_publish\AdminHelper;

/**
 * A Helper Class to assist with the publishing, archiving and unpublishing bulk action.
 *   - Called by Publish Latest Revision, Archive Latest Revision and Unpublish Current Revision Bulk Operations
 *   - Easy one-stop shop to make modifications to these bulk actions.
 */
class AdminModeration
{
    //set this to true to send to $testEmailList
    private $testMode = false;
    private $entity = null;
    private $id = 0;
    private $status = 0; // Default is 0, unpublish.

    public function __construct($entity, $status)
    {
      $this->entity = $entity;
      if (!is_null($status)) {
        $this->status = $status;
      }
      $this->id = $this->entity->id();
    }

    /**
     * Unpublish current revision.
     */
    public function unpublish() {
      $user = \Drupal::currentUser();
      $currentLang = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $allLanguages = AdminHelper::getAllEnabledLanguages();
      $archived_state = $this->getConfig()
        ->get('unpublish.state.archived');
      if (empty($archived_state) || is_null($archived_state) || !isset($archived_state)) {
        $archived_state = 'published';
      }
      foreach ($allLanguages as $langcode => $languageName) {
        if ($this->entity->hasTranslation($langcode)) {
          \Drupal::logger('moderated_content_bulk_publish')->notice(
            utf8_encode("Unpublish $langcode for " . $this->id . " in moderated_content_bulk_publish")
          );
          $this->entity = $this->entity->getTranslation($langcode);
          $this->entity->set('moderation_state', $archived_state);
          if ($this->entity instanceof RevisionLogInterface) {
            // $now = time();
            $this->entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
            $msg = t('Bulk operation create archived revision');
            $this->entity->setRevisionLogMessage($msg);
            $current_uid = \Drupal::currentUser()->id();
            $this->entity->setRevisionUserId($current_uid);
          }
//          $this->entity->setSyncing(TRUE);
          $this->entity->setRevisionTranslationAffected(TRUE);
          if ($user->hasPermission('moderated content bulk unpublish')) {
//            $this->entity->save();
            if($langcode == $currentLang) {
              $this->entity->save();
            }
            else {
              drupal_register_shutdown_function('Drupal\moderated_content_bulk_publish\AdminHelper::bulkPublishShutdown', $this->entity, $langcode, $archived_state);
            }
          }
          else {
            \Drupal::logger('moderated_content_bulk_publish')->notice(
              utf8_encode("Bulk unpublish not permitted, check permissions")
            );
          }
        }
      }

      $draft_state = $this->getConfig()
        ->get('unpublish.state.draft');
      if (empty($draft_state) || is_null($draft_state) || !isset($draft_state)) {
        $draft_state = 'draft';
      }
      foreach ($allLanguages as $langcode => $languageName) {
        if ($this->entity->hasTranslation($langcode)) {
          $this->entity = $this->entity->getTranslation($langcode);
          $this->entity->set('moderation_state', $draft_state);
          if ($this->entity instanceof RevisionLogInterface) {
            // $now = time();
            $this->entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
            $msg = t('Bulk operation create draft revision');
            $this->entity->setRevisionLogMessage($msg);
            $current_uid = \Drupal::currentUser()->id();
            $this->entity->setRevisionUserId($current_uid);
          }
          $this->entity->setSyncing(TRUE);
          $this->entity->setRevisionTranslationAffected(TRUE);
          if ($user->hasPermission('moderated content bulk unpublish')) {
//            $this->entity->save();
              if($langcode == $currentLang) {
                $this->entity->save();
              }
              else {
                drupal_register_shutdown_function('Drupal\moderated_content_bulk_publish\AdminHelper::bulkPublishShutdown', $this->entity, $langcode, $draft_state);
              }
          }
          else {
            \Drupal::logger('moderated_content_bulk_publish')->notice(
              utf8_encode("Bulk unpublish not permitted, check permissions.")
            );
          }
        }
      }
      return $this->entity;
    }


    /**
     * Publish Latest Revision.
     */
    public function publish(&$error_message = '', &$msgdetail_isToken = '', &$msgdetail_isPublished ='', &$msgdetail_isAbsoluteURL = '') {
      $user = \Drupal::currentUser();
      $allLanguages = AdminHelper::getAllEnabledLanguages();
      $published_state = $this->getConfig()
        ->get('publish.state.published');
      if (empty($published_state) || is_null($published_state) || !isset($published_state)) {
        $published_state = 'published';
      }
      // Initialize.
      $validate_failure = FALSE;
      foreach ($allLanguages as $langcode => $languageName) {
        if ($this->entity->hasTranslation($langcode)) {
          \Drupal::logger('moderated_content_bulk_publish')->notice(
            utf8_encode("Publish latest revision $langcode for " . $this->id . " in moderated_content_bulk_publish")
          );
          $latest_revision = self::_latest_revision($this->entity->id(), $vid, $langcode);
          if (!$latest_revision === FALSE) {
            $this->entity = $latest_revision;
          }
          // Add a hook that allows verifications outside of moderated_content_bulk_publish.
          $entity = $this->entity->getTranslation($langcode);
          $bodyfields = $entity->getFields();
          $bodyfield = NULL;
          if (isset($bodyfields['body'])) {
            $bodyfield = $bodyfields['body'];
          }
          if (isset($bodyfield)) {
            $fieldval = $bodyfield->getValue();
            if (isset($fieldval)) {
              if (isset($fieldval[0])) {
                $hookObject = new HookObject();
                $hookObject->nid = $this->entity->id();
                $hookObject->body_field_val = $fieldval[0]['value'];
                $hookObject->validate_failure = $validate_failure;
                \Drupal::moduleHandler()->invokeAll('moderated_content_bulk_publish_verify_publish', [$hookObject]);
                if ($hookObject->validate_failure) {
                  $error_message = $hookObject->error_message;
                  $msgdetail_isToken = $hookObject->msgdetail_isToken;
                  $msgdetail_isPublished = $hookObject->msgdetail_isPublished;
                  $msgdetail_isAbsoluteURL = $hookObject->msgdetail_isAbsoluteURL;
                  return NULL;
                }
              }
            }
          }
          $this->entity = $this->entity->getTranslation($langcode);
          $this->entity->set('moderation_state', $published_state);
          if ($this->entity instanceof RevisionLogInterface) {
            // $now = time();
            $this->entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
            $msg = t('Bulk operation publish revision');
            $this->entity->setRevisionLogMessage($msg);
            $current_uid = \Drupal::currentUser()->id();
            $this->entity->setRevisionUserId($current_uid);
          }
          $this->entity->setSyncing(TRUE);
          $this->entity->setRevisionTranslationAffected(TRUE);
          if ($user->hasPermission('moderated content bulk publish')) {
            $this->entity->save();
          }
          else {
            \Drupal::logger('moderated_content_bulk_publish')->notice(
              utf8_encode("Bulk publish not permitted, check permissions.")
            );
          }
        }
      }
      return $this->entity;
    }

    /**
     * Get the latest revision.
     */
    public static function _latest_revision($nid, &$vid, $langcode = NULL) {
      // Can be removed once we move to Drupal >= 8.6.0 , currently on 8.5.0.
      // See change record here: https://www.drupal.org/node/2942013 .
      $lang = $langcode;
      if (!isset($lang)) {
        $lang = \Drupal::languageManager()->getCurrentLanguage()->getId();
      }
      $latestRevisionResult = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
        ->latestRevision()
        ->condition('nid', $nid, '=')
        ->execute();
      if (count($latestRevisionResult)) {
        $node_revision_id = key($latestRevisionResult);
        if ($node_revision_id == $vid) {
          // There is no pending revision, the current revision is the latest.
          return FALSE;
        }
        $vid = $node_revision_id;
        $latestRevision = \Drupal::entityTypeManager()->getStorage('node')->loadRevision($node_revision_id);
        if ($latestRevision->language()->getId() != $lang && $latestRevision->hasTranslation($lang)) {
          $latestRevision = $latestRevision->getTranslation($lang);
        }
        return $latestRevision;
      }
      return FALSE;
    }

    /**
     * Archive current revision.
     */
    public function archive(&$error_message = '', &$markup = '') {
      $user = \Drupal::currentUser();
      $currentLang = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $allLanguages = AdminHelper::getAllEnabledLanguages();
      $archived_state = $this->getConfig()
        ->get('archive.state.archived');
      if (empty($archived_state) || is_null($archived_state) || !isset($archived_state)) {
        $archived_state = 'archived';
      }
      // Initialize.
      foreach ($allLanguages as $langcode => $languageName) {
        if ($this->entity->hasTranslation($langcode)) {
          // Add a hook that allows verifications outside of moderated_content_bulk_publish.
          $bundle = $this->entity->bundle();
          $nid = $this->entity->id();
          $hookObject = new HookObject();
          $hookObject->nid = $nid;
          $hookObject->bundle = $bundle;
          $hookObject->show_button = TRUE;
          $hookObject->markup = $markup;
          $hookObject->error_message = $error_message;
          \Drupal::moduleHandler()->invokeAll('moderated_content_bulk_publish_verify_archived', [$hookObject]);
          if (!$hookObject->show_button) {
            $markup = $hookObject->markup;
            $error_message = $hookObject->error_message;
            return NULL;
          }
          \Drupal::logger('moderated_content_bulk_publish')->notice(
            utf8_encode("Archive $langcode for " . $this->id . " in moderated_content_bulk_publish")
          );
          $this->entity = $this->entity->getTranslation($langcode);
          $this->entity->set('moderation_state', $archived_state);
          if ($this->entity instanceof RevisionLogInterface) {
            // $now = time();
            $this->entity->setRevisionCreationTime(\Drupal::time()->getRequestTime());
            $msg = t('Bulk operation create archived revision');
            $this->entity->setRevisionLogMessage($msg);
            $current_uid = \Drupal::currentUser()->id();
            $this->entity->setRevisionUserId($current_uid);
          }
          // $this->entity->setSyncing(TRUE);  Removing and using shutdown call to complete save of alt lang.

          $this->entity->setRevisionTranslationAffected(TRUE);
          if ($user->hasPermission('moderated content bulk archive')) {
            if($langcode == $currentLang) {
              $this->entity->save();
            }
            else {
              drupal_register_shutdown_function('Drupal\moderated_content_bulk_publish\AdminHelper::bulkPublishShutdown', $this->entity, $langcode, $archived_state);
            }
          }
          else {
            \Drupal::logger('moderated_content_bulk_publish')->notice(
              utf8_encode("Bulk archive not permitted, check permissions")
            );
          }
        }
      }
      return $this->entity;
    }

    /**
     * Returns config with module settings.
     *
     * @return \Drupal\Core\Config\Config
     *   The config.
     */
    protected function getConfig() {
      return \Drupal::config('moderated_content_bulk_publish.settings');
    }

}
