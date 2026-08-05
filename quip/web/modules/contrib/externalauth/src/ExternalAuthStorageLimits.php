<?php

namespace Drupal\externalauth;

/**
 * Shared storage length limits for externalauth.
 */
final class ExternalAuthStorageLimits {

  /**
   * The maximum supported length for authmap provider values.
   */
  public const AUTHMAP_PROVIDER_MAX_LENGTH = 128;

  /**
   * The maximum supported length for authmap authname values.
   */
  public const AUTHMAP_AUTHNAME_MAX_LENGTH = 128;

}
