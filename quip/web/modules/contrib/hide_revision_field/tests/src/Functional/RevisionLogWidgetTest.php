<?php

namespace Drupal\Tests\hide_revision_field\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test hide_revision_field_log_widget.
 *
 * @group hide_revision_field
 */
class RevisionLogWidgetTest extends BrowserTestBase {

  /**
   * Modules to enable for this test.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'user',
    'hide_revision_field',
  ];

  /**
   * An user account with full permission for revision log fields.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * An basic user account that can only access some revision log fields.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $webUser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The Entity Form Display for the article node type.
   *
   * @var \Drupal\Core\Entity\Entity\EntityFormDisplay
   */
  protected $form;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create users.
    $this->adminUser = $this->drupalCreateUser([
      'bypass node access',
      'administer revision field personalization',
      'access revision field',
    ]);
    $this->webUser = $this->drupalCreateUser([
      'bypass node access',
    ]);

    $this->drupalCreateContentType([
      'type' => 'article',
      'new_revision' => TRUE,
    ]);
    $entityTypeManager = $this->container->get('entity_type.manager');
    $this->form = $entityTypeManager->getStorage('entity_form_display')
      ->load('node.article.default');
  }

  /**
   * Tests the behavior of the 'hide_revision_field_log_widget' widget.
   */
  public function testWidget() {
    $this->drupalLogin($this->webUser);
    $this->drupalGet('node/add/article');

    $session = $this->assertSession();

    // Confirm field visible with default options.
    $session->fieldExists('revision_log[0][value]');
    $session->fieldValueEquals('revision_log[0][value]', '');

    // Confirm field hidden when set to hide.
    $this->form->setComponent('revision_log', [
      'type' => 'hide_revision_field_log_widget',
      'settings' => [
        'show' => FALSE,
      ],
    ])->save();
    $this->drupalGet('node/add/article');
    $session->fieldNotExists('revision_log[0][value]');
    $session->pageTextContains('Revision information');

    // Confirm field fully hidden when set to hide.
    $this->form->setComponent('revision_log', [
      'type' => 'hide_revision_field_log_widget',
      'settings' => [
        'show' => FALSE,
        'hide_revision' => TRUE,
      ],
    ])->save();
    $this->drupalGet('node/add/article');
    $session->pageTextNotContains('Revision information');

    // Confirm field hidden correctly based on permissions.
    $this->form->setComponent('revision_log', [
      'type' => 'hide_revision_field_log_widget',
      'settings' => [
        'show' => TRUE,
        'permission_based' => TRUE,
        'default' => 'A new log message',
      ],
    ])->save();

    $this->drupalGet('node/add/article');
    $session->fieldNotExists('revision_log[0][value]');
    $session->hiddenFieldValueEquals('revision_log[0][value]', 'A new log message');

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('node/add/article');
    $session->fieldExists('revision_log[0][value]');
    $session->fieldValueEquals('revision_log[0][value]', 'A new log message');
  }

  /**
   * Test User personalization.
   */
  public function testUserPersonalization() {
    $this->drupalLogin($this->webUser);
    $this->drupalGet("user/{$this->webUser->id()}/edit");

    $session = $this->assertSession();
    $session->fieldNotExists('hide_revision_field[node][article]');
    $session->pageTextNotContains('Revision Field Settings');

    $this->drupalLogin($this->adminUser);
    $this->drupalGet("user/{$this->adminUser->id()}/edit");

    $session = $this->assertSession();
    $session->fieldExists('hide_revision_field[node][article]');
    $session->fieldValueEquals('hide_revision_field[node][article]', TRUE);
    $session->pageTextContains('Revision Field Settings');

    $edit = ['hide_revision_field[node][article]' => FALSE];
    $this->submitForm($edit, 'op');

    $this->drupalGet('node/add/article');
    $session->fieldNotExists('revision_log[0][value]');

    // Confirm that there isn't an empty fieldset on the user edit page
    // if there are no fields allowing user personalization.
    $this->form->setComponent('revision_log', [
      'type' => 'hide_revision_field_log_widget',
      'settings' => [
        'allow_user_settings' => FALSE,
      ],
    ])->save();

    $this->drupalGet("user/{$this->adminUser->id()}/edit");
    $session->fieldNotExists('hide_revision_field[node][article]');
    $session->pageTextNotContains('Revision Field Settings');
  }

}
