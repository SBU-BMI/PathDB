<?php

namespace Drupal\file_replace\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;

/**
 * Determines access to replacing a file.
 */
class FileReplaceAccessCheck implements AccessInterface {

  /**
   * The file usage service.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface
   */
  protected $fileUsage;

  /**
   * Constructs a EntityCreateAccessCheck object.
   *
   * @param \Drupal\file\FileUsage\FileUsageInterface $file_usage
   *   The file usage service.
   */
  public function __construct(FileUsageInterface $file_usage) {
    $this->fileUsage = $file_usage;
  }

  /**
   * Checks access for replacing a file.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param \Drupal\file\FileInterface $file
   *   The file to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, FileInterface $file) {
    return AccessResult::allowedIf($account->hasPermission('replace files') && $file->isPermanent());
  }

}
