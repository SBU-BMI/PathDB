<?php

declare(strict_types=1);

namespace Drupal\Tests\moderated_content_bulk_publish\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\node\Entity\Node;
use Drupal\Core\Url;

/**
 * Ensures the node edit confirmation dialog opens once and bypasses on confirm.
 *
 * @group moderated_content_bulk_publish
 */
final class NodeEditDialogSingletonTest extends WebDriverTestBase {
  use ContentModerationTestTrait;

  /** @var string */
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
    $extensions = \Drupal::service('extension.list.module')->reset()->getList();
    $this->config('system.theme')->set('admin', 'claro')->save();

    // Content type.
    $type = $this->drupalCreateContentType(['type' => 'page']);

    // Editorial workflow with Draft/Published etc., then attach to 'page'.
    $workflow = $this->createEditorialWorkflow();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', $type->id());

    // Turn on this module's node-edit dialog.
    $this->config('moderated_content_bulk_publish.settings')
      ->set('enable_dialog_node_edit_form', TRUE)
      ->save();

    // User with perms to publish via workflow.
    $account = $this->drupalCreateUser([
      'bypass node access',
      'administer nodes',
      'view latest version',
      'create page content',
      'edit any page content',
      'use editorial transition publish',
    ]);
    $this->drupalLogin($account);
  }

  public function testSingleDialogAndBypassOnNodeEdit(): void {
    // Draft node.
    $node = Node::create([
      'type' => 'page',
      'title' => 'Draft page',
      'moderation_state' => 'draft',
      'status' => 0,
    ]);
    $node->save();

    // Go to edit form and change moderation state to Published.
    $this->drupalGet(Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]));
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('moderation_state[0][state]', 'published');

    // Double-click submit (attempt to create duplicate dialogs).
    $this->getSession()->executeScript('
      var btn = document.querySelector("#gin-sticky-edit-submit") || document.querySelector("#edit-submit");
      btn.click(); btn.click();
    ');

    // Assert exactly one dialog exists.
    $this->assertSession()->waitForElementVisible('css', '.ui-dialog');
    $this->assertSession()->elementsCount('css', '.ui-dialog', 1);

    // Confirm in the dialog.
    $this->assertSession()->elementExists('css', '.ui-dialog .button--primary')->click();

    // Dialog disappears; submit proceeds (bypass should prevent re-open).
    $this->assertSession()->waitForElementRemoved('css', '.ui-dialog');

    // Verify node is published.
    $node = Node::load($node->id());
    $this->assertSame('published', $node->get('moderation_state')->value);
  }
}

