<?php

namespace Drupal\tac_lite\Cache;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CalculatedCacheContextInterface;
use Drupal\Core\Cache\Context\UserCacheContextBase;
use Drupal\tac_lite\Form\SchemeForm;

/**
 * Defines the cache context service for TAC-Lite-based taxonomy access grants.
 *
 * Cache context ID: 'user.tac_lite_grants' (to vary by all schemes).
 * Calculated cache context ID: 'user.tac_lite_grants:%scheme', e.g.
 * 'user.node_grants:1' (to vary by the grants in scheme 1).
 *
 * This allows for TAC-lite-sensitive caching when listing taxonomy terms.
 *
 * @see tac_lite_query_term_access_alter()
 */
class TacLiteGrantsCacheContext extends UserCacheContextBase implements CalculatedCacheContextInterface {

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t("TAC Lite access grants");
  }

  /**
   * {@inheritdoc}
   *
   * @noinspection PhpParameterNameChangedDuringInheritanceInspection
   */
  public function getContext($scheme_id = NULL): string {
    // If the current user can administer TAC Lite, we don't need to determine
    // the exact TAC Lite grants.
    if ($this->user->hasPermission('administer tac_lite')) {
      return 'all';
    }

    // When no specific scheme operation is specified, check the grants for all
    // schemes.
    if ($scheme_id === NULL) {
      $scheme_ids = $this->getSchemeIdsAffectingTermVisibility();

      if (empty($scheme_ids)) {
        return 'all';
      }
      else {
        $result = array_map(
          function ($scheme_id) {
            return $this->getSchemeGrants($scheme_id);
          },
          $scheme_ids
        );

        return implode('-', $result);
      }
    }
    else {
      return $this->getSchemeGrants($scheme_id);
    }
  }

  /**
   * Gets the IDs of all TAC Lite schemes that affect term visibility.
   *
   * @return int[]
   *   The ID of each TAC Lite scheme that is configured to affect term
   *   visibility.
   */
  protected function getSchemeIdsAffectingTermVisibility(): array {
    // TODO: This pattern of loading and enumerating schemes is repeated in at
    // least 7 places in the TAC Lite code . It should be refactored out into a
    // settings repository service object. That's too big a lift for a security
    // patch, so it is not being tackled at this time.
    $settings = \Drupal::config('tac_lite.settings');
    $schemes = $settings->get('tac_lite_schemes');

    $scheme_ids = [];

    for ($scheme_index = 1; $scheme_index <= $schemes; $scheme_index++) {
      $config = SchemeForm::tacLiteConfig($scheme_index);

      if ($config['term_visibility']) {
        $scheme_ids[] = $scheme_index;
      }
    }

    return $scheme_ids;
  }

  /**
   * {@inheritdoc}
   *
   * @noinspection PhpParameterNameChangedDuringInheritanceInspection
   */
  public function getCacheableMetadata($scheme_id = NULL): CacheableMetadata {
    $cacheable_metadata = new CacheableMetadata();

    $configured_schemes = $this->getSchemeIdsAffectingTermVisibility();

    if (empty($configured_schemes) ||
        ($scheme_id !== NULL && !in_array($scheme_id, $configured_schemes))) {
      // No impact to cacheability.
      return $cacheable_metadata;
    }

    // TAC Lite grants may change if the user is updated.
    $cacheable_metadata->setCacheTags(['user:' . $this->user->id()]);

    return $cacheable_metadata;
  }

  /**
   * Checks the current user's grants for the given TAC Lite scheme.
   *
   * @param int $scheme_id
   *   The operation to check the node grants for.
   *
   * @return string
   *   The string representation of the grants in the given scheme.
   */
  protected function getSchemeGrants(int $scheme_id): string {
    $grants = _tac_lite_user_tids($this->user, $scheme_id);

    return $scheme_id . '.' . implode(',', $grants);
  }

}
