<?php

declare(strict_types=1);

namespace Drupal\Tests\moderated_content_bulk_publish\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\views\Entity\View;
use Drupal\node\Entity\Node;

/**
 * Reproduces #3371439: "Publish latest revision" should publish the latest draft.
 *
 * Steps:
 * 1) Create an Article with body 'REV 1', log 'REV 1' (Draft).
 * 2) Bulk action: Publish latest revision -> node page shows 'REV 1' (published).
 * 3) Edit: change body to 'REV 2', log 'REV 2' (Draft, latest revision).
 * 4) Bulk action again: Publish latest revision -> node page should show 'REV 2'.
 * 5) Both 'REV 1' and 'REV 2' logs should exist in revision history.
 *
 * @group moderated_content_bulk_publish
 */
final class PublishLatestRevisionLatestDraftTest extends WebDriverTestBase {
  use ContentModerationTestTrait;

  protected $defaultTheme = 'claro';

  protected static $modules = [
    'node',
    'user',
    'system',
    'views',
    'workflows',
    'content_moderation',
    'moderated_content_bulk_publish',
  ];

  protected function setUp(): void {
    parent::setUp();

    $this->getSession()->resizeWindow(1280, 900, 'current');
    $this->config('system.theme')->set('admin', 'claro')->save();

    // Create 'article' and bind to Editorial workflow.
    $type = $this->drupalCreateContentType(['type' => 'article']);
    $workflow = $this->createEditorialWorkflow();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', $type->id());

    // Enable the admin/content dialog (module setting).
    $this->config('moderated_content_bulk_publish.settings')
      ->set('enable_dialog_admin_content', TRUE)
      ->save();

    $account = $this->drupalCreateUser([
      'access content overview',
      'access administration pages',
      'administer nodes',
      'bypass node access',
      'moderated content bulk publish',
      'moderated content bulk archive',
      'moderated content bulk unpublish',
      'use editorial transition publish',
      'use editorial transition archive',
    ]);
    $this->drupalLogin($account);

    $this->ensureAdminContentHasBulkForm();
  }

  /**
   * The regression reproduction test.
   */
  public function testPublishLatestUsesLatestDraftAndKeepsRevisions(): void {
    // 1) Create an initial draft "REV 1".
    $node = Node::create([
      'type' => 'article',
      'title' => 'Bug 3371439 Article',
      'status' => 0,
      'moderation_state' => 'draft',
      'body' => ['value' => 'REV 1'],
    ]);
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('REV 1');
    $node->save();
    $nid = (int) $node->id();

    // 2) Publish latest via the bulk action from /admin/content.
    $this->bulkPublishLatest([0]);

    // After batch completes, the published page (anonymous) should show REV 1.
    $this->drupalLogout();
    $this->drupalGet("/node/{$nid}");
    $this->assertSession()->pageTextContains('REV 1');

    // 3) Create a new draft revision "REV 2" programmatically.
    $this->drupalLogin($this->rootUser);
    $node = Node::load($nid);
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('REV 2');
    $node->set('body', ['value' => 'REV 2']);
    $node->set('moderation_state', 'draft');
    $node->save();

    // Sanity: published page still shows 'REV 1' before publishing again.
    $this->drupalLogout();
    $this->drupalGet("/node/{$nid}");
    $this->assertSession()->pageTextContains('REV 1');

    // 4) Bulk publish latest again. Expect published page to show 'REV 2'.
    $this->drupalLogin($this->rootUser);
    $this->bulkPublishLatest([0]);

    $this->drupalLogout();
    $this->drupalGet("/node/{$nid}");
    $this->assertSession()->pageTextContains('REV 2');

    // 5) Verify that both 'REV 1' and 'REV 2' revision logs still exist.
    // (Reported bug says they may be missing/overwritten.)
    $this->drupalLogin($this->rootUser);
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $node = Node::load($nid);
    $rev_ids = $storage->revisionIds($node);

    $logs = [];
    foreach ($rev_ids as $rid) {
      /** @var \Drupal\node\NodeInterface $rev */
      $rev = $storage->loadRevision($rid);
      $logs[] = (string) $rev->getRevisionLogMessage();
    }

    $this->assertTrue(in_array('REV 1', $logs, TRUE), 'Found revision with log message "REV 1".');
    $this->assertTrue(in_array('REV 2', $logs, TRUE), 'Found revision with log message "REV 2".');
  }

  /**
   * Adds the node_bulk_form field to /admin/content with publish/unpublish actions.
   */
  protected function ensureAdminContentHasBulkForm(): void {
    $view = View::load('content');
    $display = $view->get('display');

    $defs = array_keys(\Drupal::service('plugin.manager.action')->getDefinitions());
    $publish_id = in_array('publish_latest_revision_action', $defs, TRUE)
      ? 'publish_latest_revision_action'
      : (in_array('publish_latest_revision', $defs, TRUE) ? 'publish_latest_revision' : 'node_publish_action');
    $unpublish_id = in_array('unpublish_current_revision_action', $defs, TRUE)
      ? 'unpublish_current_revision_action'
      : (in_array('unpublish_current', $defs, TRUE) ? 'unpublish_current' : 'node_unpublish_action');

    $page_display_id = 'page_1';
    if (!isset($display[$page_display_id])) {
      foreach ($display as $id => $def) {
        if (!empty($def['display_plugin']) && $def['display_plugin'] === 'page') {
          $page_display_id = $id;
          break;
        }
      }
    }

    $page_fields = $display[$page_display_id]['display_options']['fields']
      ?? ($display['default']['display_options']['fields'] ?? []);

    $base_table = $view->get('base_table') ?? 'node_field_data';

    if (!isset($page_fields['node_bulk_form'])) {
      $page_fields['node_bulk_form'] = [
        'id' => 'node_bulk_form',
        'table' => $base_table,
        'field' => 'node_bulk_form',
        'plugin_id' => 'node_bulk_form',
        'entity_type' => 'node',
        'label' => '',
        'exclude' => FALSE,
        'element_label_colon' => FALSE,
        'hide_empty' => FALSE,
        'empty_zero' => FALSE,
        'hide_alter_empty' => TRUE,
        'selected_actions' => [
          $unpublish_id,
          $publish_id,
        ],
      ];
      $display[$page_display_id]['display_options']['defaults']['fields'] = FALSE;
      $display[$page_display_id]['display_options']['fields'] = $page_fields;

      $view->set('display', $display);
      $view->save();
    }
  }

  /**
   * Runs "Publish latest revision" on the content listing for selected rows.
   *
   * @param int[] $row_indexes
   *   Zero-based row indexes to check before applying the action.
   */
  protected function bulkPublishLatest(array $row_indexes): void {
    $this->drupalGet('/admin/content');
    $this->assertSession()->waitForElementVisible('css', '[data-drupal-selector="views-form-content-page-1"]');
    $this->assertSession()->waitForElementVisible('css', '#edit-bulk-actions-container');

    // Select target rows.
    foreach ($row_indexes as $i) {
      $this->assertSession()->elementExists('css', "[data-drupal-selector='edit-node-bulk-form-{$i}']")->click();
    }

    // Choose the module-provided action if present; else fall back.
    $select = $this->assertSession()->elementExists(
      'css',
      '[data-drupal-selector="views-form-content-page-1"] [data-drupal-selector="edit-action"]'
    );
    $value = $select->find('css', 'option[value="publish_latest"]') ? 'publish_latest'
      : ($select->find('css', 'option[value="node_publish_action"]') ? 'node_publish_action' : '');
    $this->assertNotEmpty($value, 'No suitable publish action option found.');
    $select->selectOption($value);

    // Click Apply.
    $apply_css = "[data-drupal-selector='views-form-content-page-1'] [data-drupal-selector='edit-submit']";
    $this->assertSession()->waitForElementVisible('css', $apply_css);
    $this->getSession()->getPage()->find('css', $apply_css)->click();

    // Confirm in the dialog (module’s UI).
    $this->waitForModalOpen();
    $this->assertSession()->elementExists('css', '.ui-dialog .button--primary')->click();
    $this->assertSession()->waitForElementRemoved('css', '.ui-dialog');

    // Wait for batch to complete and redirect back to listing.
    $this->waitForBatchToFinish();
  }

  private function waitForModalOpen(int $timeoutMs = 3000): void {
    $cond = "(function(){
      var m = document.querySelector('#drupal-modal');
      if (!m) return false;
      if (getComputedStyle(m).display === 'none') return false;
      var dlg = document.querySelector('.ui-dialog');
      return !!dlg && getComputedStyle(dlg).display !== 'none';
    })()";
    $this->getSession()->wait($timeoutMs, $cond);
  }

  private function waitForBatchToFinish(int $timeoutMs = 20000): void {
    $this->getSession()->wait($timeoutMs, "(function(){
      var m = document.querySelector('#drupal-modal');
      if (m && getComputedStyle(m).display !== 'none') return false;
      if (document.querySelector('#progress, .progress, .progress__bar')) return false;
      var path = location.pathname.replace(/\\/+$/,'');
      return /\\/admin\\/content$/.test(path) || /\\/web\\/admin\\/content$/.test(path);
    })()");
  }

}

