<?php

namespace Drupal\bootstrap\Plugin\Provider;

use Drupal\bootstrap\Bootstrap;

/**
 * The "drupal_bootstrap_styles" CDN Provider plugin.
 *
 * @ingroup plugins_provider
 *
 * @BootstrapProvider(
 *   id = "drupal_bootstrap_styles",
 *   label = @Translation("Drupal Bootstrap Styles"),
 *   description = @Translation("Provides styles that bridge the gap between Drupal and Bootstrap."),
 *   hidden = true,
 * )
 */
class DrupalBootstrapStyles extends JsDelivr {

  const KNOWN_FALL_BACK_VERSION = '0.0.1';

  /**
   * Retrieves the latest version of the published NPM package.
   *
   * While this isn't technically necessary, jsDelivr have been known to not
   * favor "version-less" requests. This ensures that a specific and published
   * NPM version is always used.
   *
   * @return string
   *   The latest version.
   */
  protected function getLatestVersion() {
    $json = $this->request('https://data.jsdelivr.com/v1/package/npm/@unicorn-fail/drupal-bootstrap-styles', ['ttl' => static::TTL_ONE_WEEK])->getData();
    return $json['tags']['latest'] ?? static::KNOWN_FALL_BACK_VERSION;
  }

  /**
   * {@inheritdoc}
   */
  protected function getApiAssetsUrlTemplate() {
    $latest = $this->getLatestVersion();
    return "https://cdn.jsdelivr.net/npm/@unicorn-fail/drupal-bootstrap-styles@$latest/dist/api.json";
  }

  /**
   * {@inheritdoc}
   */
  protected function getApiVersionsUrlTemplate() {
    $latest = $this->getLatestVersion();
    return "https://cdn.jsdelivr.net/npm/@unicorn-fail/drupal-bootstrap-styles@$latest/dist/api.json";
  }

  /**
   * {@inheritdoc}
   */
  protected function getCdnUrlTemplate() {
    $latest = $this->getLatestVersion();
    return "https://cdn.jsdelivr.net/npm/@unicorn-fail/drupal-bootstrap-styles@$latest/@file";
  }

  /**
   * {@inheritdoc}
   */
  protected function mapVersion($version, $library = NULL) {
    $mapped = [];

    // Map Bootstrap versions to available drupal-bootstrap-styles versions.
    // Map newer versions that don't exist to the latest available (3.4.1).
    $mapped['3.4.2'] = '3.4.1';
    $mapped['3.4.3'] = '3.4.1';
    $mapped['3.4.4'] = '3.4.1';
    $mapped['3.4.5'] = '3.4.1';
    $mapped['3.4.6'] = '3.4.1';
    $mapped['3.4.7'] = '3.4.1';
    $mapped['3.4.8'] = '3.4.1';

    return $mapped[$version] ?? $version;
  }

  /**
   * {@inheritdoc}
   */
  protected function parseAssets(array $data, $library, $version, ?CdnAssets $assets = NULL) {
    if (!isset($assets)) {
      $assets = new CdnAssets();
    }

    // Use mapped version to find files that actually exist in drupal-bootstrap-styles.
    $mapped_version = $this->mapVersion($version, $library);

    $files = array_filter($data['files'] ?? [], function ($file) use ($library, $mapped_version) {
      if (isset($file['name'])) {
        if (!str_starts_with($file['name'], '/dist/' . $mapped_version . '/' . Bootstrap::PROJECT_BRANCH . '/')) {
          return FALSE;
        }
        $theme = !!preg_match("`drupal-bootstrap-([\w]+)(\.min)?\.css$`", $file['name']);
        return ($library === 'bootstrap' && !$theme) || ($library === 'bootswatch' && $theme);
      }
      else {
        return FALSE;
      }
    });

    foreach ($files as $file) {
      $assets->append($this->getCdnUrl('drupal-bootstrap-styles', $version, !empty($file['symlink']) ? $file['symlink'] : $file['name'], $file));
    }

    return $assets;
  }

  /**
   * {@inheritdoc}
   */
  protected function parseVersions(array $data = []) {
    $versions = [];
    $files = $data['files'] ?? [];
    foreach ($files as $file) {
      if (preg_match("`^/?dist/(\d+\.\d+\.\d+)/(\d\.x-\d\.x)/drupal-bootstrap-?([\w]+)?(\.min)?\.css$`", $file['name'], $matches)) {
        $version = $matches[1];
        $branch = $matches[2];
        if ($branch === Bootstrap::PROJECT_BRANCH && $this->isValidVersion($version)) {
          $versions[$version] = $version;
        }
      }
    }
    return $versions;
  }

}
