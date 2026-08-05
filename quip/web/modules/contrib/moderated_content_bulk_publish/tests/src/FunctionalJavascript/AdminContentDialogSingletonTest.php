<?php

declare(strict_types=1);

namespace Drupal\Tests\moderated_content_bulk_publish\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use Drupal\node\Entity\Node;

/**
 * Ensures only one dialog is shown from admin/content bulk actions.
 *
 * @group moderated_content_bulk_publish
 */
final class AdminContentDialogSingletonTest extends WebDriverTestBase {
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
    // Make sure we have a bundle and an Editorial workflow bound to it.
    $type = $this->drupalCreateContentType(['type' => 'article']);
    $workflow = $this->createEditorialWorkflow();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', $type->id());

    // Turn on the admin/content dialog.
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

    // Ensure /admin/content has the Node operations bulk form field configured.
    $this->ensureAdminContentHasBulkForm();
  }

  /**
   * Adds the 'node_bulk_form' field to /admin/content with CM actions selected.
   */
  protected function ensureAdminContentHasBulkForm(): void {
    $view = View::load('content');
    $display = $view->get('display');

    // Discover action plugin IDs across D10/D11 (fallback if names differ).
    $defs = array_keys(\Drupal::service('plugin.manager.action')->getDefinitions());
    $publish_id = in_array('publish_latest_revision_action', $defs, TRUE)
      ? 'publish_latest_revision_action'
      : (in_array('publish_latest_revision', $defs, TRUE) ? 'publish_latest_revision' : 'node_publish_action');
    $unpublish_id = in_array('unpublish_current_revision_action', $defs, TRUE)
      ? 'unpublish_current_revision_action'
      : (in_array('unpublish_current', $defs, TRUE) ? 'unpublish_current' : 'node_unpublish_action');

    // The admin/content page is typically 'page_1' and it overrides fields.
    $page_display_id = 'page_1';
    if (!isset($display[$page_display_id])) {
      // Fallback: pick the first page display if keys differ in future core.
      foreach ($display as $id => $def) {
        if (!empty($def['display_plugin']) && $def['display_plugin'] === 'page') {
          $page_display_id = $id;
          break;
        }
      }
    }

    // Work from the page display's fields, falling back to default.
    $page_fields = $display[$page_display_id]['display_options']['fields'] ?? ($display['default']['display_options']['fields'] ?? []);

    // Determine the correct base table for this view (usually node_field_data).
    $base_table = $view->get('base_table') ?? 'node_field_data';

    if (!isset($page_fields['node_bulk_form'])) {
      $page_fields['node_bulk_form'] = [
        'id' => 'node_bulk_form',
        'table' => $base_table,            // dynamic base table for safety
        'field' => 'node_bulk_form',
        'plugin_id' => 'node_bulk_form',
        'entity_type' => 'node',
        'label' => '',
        'exclude' => FALSE,
        'element_label_colon' => FALSE,
        'hide_empty' => FALSE,
        'empty_zero' => FALSE,
        'hide_alter_empty' => TRUE,
        // Keep this flexible: if these actions aren't available, the widget should
        // still render with whatever actions exist for the user.
        'selected_actions' => [
          $unpublish_id,
          $publish_id,
        ],
      ];
      // Ensure the page display actually overrides fields.
      $display[$page_display_id]['display_options']['defaults']['fields'] = FALSE;
      $display[$page_display_id]['display_options']['fields'] = $page_fields;

      $view->set('display', $display);
      $view->save();
    }
  }

  public function testSingleDialogOnBulkPublish(): void {
    // Two unpublished nodes.
    $nids = [];
    foreach (['A', 'B'] as $suffix) {
      $node = Node::create([
        'type' => 'article', // 'article' is available in test installs.
        'title' => 'N ' . $suffix,
        'status' => 0,
        'moderation_state' => 'draft',
      ]);
      $node->save();
      $nids[] = $node->id();
    }

    // Go to admin/content and wait for the *views form* (not the exposed filters).
    $this->drupalGet('/admin/content');
    $this->assertSession()->waitForElementVisible('css', '[data-drupal-selector="views-form-content-page-1"]');
    // Wait specifically for the bulk-actions container and the Action select.
    $this->assertSession()->waitForElementVisible('css', '#edit-bulk-actions-container');
    $this->assertSession()->waitForElementVisible('css', '[data-drupal-selector="views-form-content-page-1"] [data-drupal-selector="edit-action"]');
    $select = $this->findBulkActionSelect();

    // Select both rows first — some themes/behaviors disable Apply until selection.
    $this->getSession()->executeScript(
      'document.querySelectorAll("[data-drupal-selector=\'views-form-content-page-1\'] .views-table tbody input.form-checkbox").forEach(cb => cb.checked = true);'
    );

    // Choose the action that actually exists in your markup: value="publish_latest".
    $select = $this->assertSession()->elementExists(
      'css',
      '[data-drupal-selector="views-form-content-page-1"] [data-drupal-selector="edit-action"]'
    );
    // Prefer module-provided "publish_latest" if present; otherwise fall back.
    $value = $select->find('css', 'option[value="publish_latest"]') ? 'publish_latest'
      : ($select->find('css', 'option[value="node_publish_action"]') ? 'node_publish_action' : '');
    $this->assertNotEmpty($value, 'No suitable publish action option found.');
    $select->selectOption($value);
    // Fire change to satisfy any JS behaviors that enable the Apply button.
    $this->getSession()->executeScript(
      'var s=document.querySelector("[data-drupal-selector=\'views-form-content-page-1\'] [data-drupal-selector=\'edit-action\']"); if(s){s.dispatchEvent(new Event("change",{bubbles:true}));}'
    );

    // Click the correct Apply (handles both edit-submit and edit-action-apply).
    $this->clickBulkApplyOnce();
    $this->waitForModalOpen();
    $this->assertExactlyOneDialog();

    // Confirm and wait for completion.
    $this->assertSession()->elementExists('css', '.ui-dialog .button--primary')->click();
    $this->assertSession()->waitForElementRemoved('css', '.ui-dialog');

    // Wait for the batch to complete and the redirect to finish.
    $this->waitForBatchToFinish();

    // Ensure nodes are published.
    foreach ($nids as $nid) {
      $node = Node::load($nid);
      $this->assertTrue((bool) $node->isPublished());
    }
  }

  private function findBulkActionSelect(): \Behat\Mink\Element\NodeElement {
    $candidates = [
      'css', '[data-drupal-selector=\'views-form-content-page-1\'] [data-drupal-selector=\'edit-submit\']',
      'css', 'form.views-form select[name="action"]',
      'css', 'form.views-form .views-bulk-actions__item select',
    ];
    for ($i = 0; $i < count($candidates); $i += 2) {
      $this->assertSession()->waitForElementVisible($candidates[$i], $candidates[$i+1]);
      $el = $this->getSession()->getPage()->find($candidates[$i], $candidates[$i+1]);
      if ($el) {
        return $el;
      }
    }
    $this->fail('Bulk action <select> not found by any known selector.');
  }

  private function clickBulkApplyOnce(): void {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert = $this->assertSession();

    // Pick one row + the action that opens your dialog.
    $page->checkField('edit-node-bulk-form-0');
    $assert->elementExists('css', "[data-drupal-selector='edit-action']")
      ->selectOption('publish_latest'); // or your action value

    // Target the Apply button.
    $css = "[data-drupal-selector='views-form-content-page-1'] [data-drupal-selector='edit-submit']";
    $assert->waitForElementVisible('css', $css);

    // Center it to avoid sticky header overlap and try a normal click first.
    $session->executeScript(
      "var b=document.querySelector(" . json_encode($css) . ");
       if(b){ b.scrollIntoView({block:'center', inline:'center'}); }"
    );
    $btn = $page->find('css', $css);
    $this->assertNotNull($btn, 'Bulk Apply button should exist.');

    try {
      $btn->click();
    }
    catch (\Exception $e) {
      // Fallback: unstick headers and JS-click (CI-safe).
      $session->executeScript(
        "document.querySelectorAll('.sticky-header, .position-sticky').forEach(function(el){
           el.dataset._pos = el.style.position; el.style.position='static';
         });
         var b=document.querySelector(" . json_encode($css) . ");
         if(b){ b.scrollIntoView({block:'center', inline:'center'}); b.click(); }"
      );
    }
  }

  private function waitForModalOpen(int $timeoutMs = 3000): void {
    // Wait for Drupal’s jQuery UI modal to be visible.
    $cond = "(function(){
      var m = document.querySelector('#drupal-modal');
      if (!m) return false;
      if (getComputedStyle(m).display === 'none') return false;
      // When open, a .ui-dialog wrapper is created.
      var dlg = document.querySelector('.ui-dialog');
      return !!dlg && getComputedStyle(dlg).display !== 'none';
    })()";
    $this->getSession()->wait($timeoutMs, $cond);
  }

  private function assertExactlyOneDialog(): void {
    // Immediate assertion.
    $this->assertSession()->elementsCount('css', '.ui-dialog', 1);
    // And still one after a short settle (guards against double-open).
    $this->getSession()->wait(150);
    $this->assertSession()->elementsCount('css', '.ui-dialog', 1);
  }

  private function waitForBatchToFinish(int $timeoutMs = 20000): void {
    $this->getSession()->wait($timeoutMs, "(function(){
      // If a modal is still visible, keep waiting.
      var m = document.querySelector('#drupal-modal');
      if (m && getComputedStyle(m).display !== 'none') return false;

      // While batch/progress UI exists, keep waiting.
      if (document.querySelector('#progress, .progress, .progress__bar')) return false;

      // Consider done once we're back on the content listing.
      var path = location.pathname.replace(/\\/+$/,'');
      return /\\/admin\\/content$/.test(path) || /\\/web\\/admin\\/content$/.test(path);
    })()");
  }


}

