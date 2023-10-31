<?php

namespace Drupal\moderated_content_bulk_publish;

use Drupal\Core\Language\LanguageInterface;

class AdminHelper {

  static public function addMessage($message) {
    \Drupal::messenger()->addMessage($message);
  }

  static public function addToLog($message, $DEBUG = FALSE) {
    //$DEBUG = TRUE;
    if ($DEBUG) {
      \Drupal::logger('moderated_content_bulk_publish')->notice($message);
    }
  }

  /**
   * Helper function to get all enabled languages, excluding current language.
   */
  static public function getOtherEnabledLanguages() {
    // Get the list of all languages
    $language = \Drupal::languageManager()->getCurrentLanguage();
    $languages = \Drupal::languageManager()->getLanguages(LanguageInterface::STATE_ALL);
    $other_languages = array();

    // Add each enabled language, aside from the current language to an array.
    foreach ($languages as $field_language_code => $field_language) {
      if ($field_language_code != $language->getId()) {
        $other_languages[$field_language_code] = $field_language->getName();
      }
    }
    return $other_languages;
  }

  /**
   * Helper function get current language.
   */
  static public function getDefaultLangcode() {
    $language = \Drupal::languageManager()->getDefaultLanguage();
    return $language->getId();
  }

  /**
   * Helper function to get all enabled languages, including the current language.
   */
  static public function getAllEnabledLanguages() {
    $other_languages = self::getOtherEnabledLanguages();
    $language = \Drupal::languageManager()->getCurrentLanguage();
    $current_language = [];
    // We need the current language first in the array.
    $other_languages = array_reverse($other_languages);
    $all_languages = $other_languages;
    $all_languages[$language->getId()] = $language->getName();
    $all_languages = array_reverse($all_languages);
    return $all_languages;
  }

  /**
   * Helper function for doing stuff after shutdown function to ensure previous db transaction is committed.
   * Make sure the moderation state is processed correctly.
   */
  static public function bulkPublishShutdown($entity, $langcode, $moderation_state) {
    $pathauto_enabled = \Drupal::service('module_handler')->moduleExists('pathauto');
    if (!empty($moderation_state)) {
      $vid = 0;
      $latest_revision = self::bulkLatestRevision($entity, $entity->id(), $vid, $langcode);
      $latest_state = $moderation_state;
      $latest_is_valid = TRUE;
      if ($latest_revision == FALSE) {
        $latest_is_valid = FALSE;
      }
      else {
        $latest_state = $latest_revision->get('moderation_state')->getString();
      }
      if ($latest_is_valid) {
        $latest_revision->setSyncing(TRUE);
        $latest_revision->setRevisionTranslationAffected(TRUE);
        $latest_revision->set('moderation_state', $moderation_state);
        $latest_revision->save();
        // Ensure the alias gets updated.
        if ($pathauto_enabled) {
          \Drupal::service('pathauto.generator')->updateEntityAlias($latest_revision, 'insert', array('language' => $langcode));
          \Drupal::service('pathauto.generator')->updateEntityAlias($latest_revision, 'update', array('language' => $langcode));
        }
      }
      elseif ($pathauto_enabled) {
        // Ensure the alias gets updated.
        \Drupal::service('pathauto.generator')->updateEntityAlias($entity, 'insert', array('language' => $langcode));
        \Drupal::service('pathauto.generator')->updateEntityAlias($entity, 'update', array('language' => $langcode));
      }
    }
    elseif ($pathauto_enabled) {
      // Ensure the alias gets updated.
      \Drupal::service('pathauto.generator')->updateEntityAlias($entity, 'insert', array('language' => $langcode));
      \Drupal::service('pathauto.generator')->updateEntityAlias($entity, 'update', array('language' => $langcode));
    }
  }

  /**
   * Retrieve the latest node revision of $lang.
   */
  static public function bulkLatestRevision($entity, $id, &$vid, $lang) {
    $entity_type = $entity->getEntityTypeId();
    $query = \Drupal::entityTypeManager()->getStorage($entity_type)->getQuery();
    $query->latestRevision();
    $query->condition($entity->getEntityType()->getKey('id'), $id, '=');

    $query->accessCheck(TRUE);
    $latestRevisionResult = $query->execute();
    if (count($latestRevisionResult)) {
      $node_revision_id = key($latestRevisionResult);
      $vid = $node_revision_id;
      $latestRevision = \Drupal::entityTypeManager()->getStorage($entity_type)->loadRevision($node_revision_id);
      if ($latestRevision->hasTranslation($lang) && $latestRevision->language()->getId() != $lang) {
        $latestRevision = $latestRevision->getTranslation($lang);
      }
      return $latestRevision;
    }
    return FALSE;
  }
}

