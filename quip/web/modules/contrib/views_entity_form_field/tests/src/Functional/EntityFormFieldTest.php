<?php

declare(strict_types=1);

namespace Drupal\Tests\views_entity_form_field\Functional;

use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Views Entity Form Field functionality and access handling.
 */
#[Group('views_entity_form_field')]
#[RunTestsInSeparateProcesses]
class EntityFormFieldTest extends BrowserTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'node',
    'text',
    'views',
    'views_entity_form_field',
    'views_entity_form_field_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The path for the editable test view.
   */
  private const EDIT_PATH = 'views-entity-form-field-test';

  /**
   * The path for the fallback view mode test view.
   */
  private const FALLBACK_PATH = 'views-entity-form-field-fallback-test';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $this->createTestView('views_entity_form_field_test', self::EDIT_PATH);
    $this->createTestView('views_entity_form_field_fallback_test', self::FALLBACK_PATH, 'default');
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Tests that the module installs and exposes form field handlers to Views.
   */
  public function testViewsDataRegistersEntityFormFieldHandlers(): void {
    $views_data = $this->container->get('views.views_data')->get('node_field_data');

    $this->assertArrayHasKey('form_field_body', $views_data);
    $this->assertSame('entity_form_field', $views_data['form_field_body']['field']['id']);
    $this->assertSame('node', $views_data['form_field_body']['field']['entity_type']);
    $this->assertSame('body', $views_data['form_field_body']['field']['field_name']);
  }

  /**
   * Tests that an authorized user can update field values from a View.
   */
  public function testEditableUserCanUpdateFieldValues(): void {
    $first_node = $this->createArticle('First article', 'Original first body');
    $second_node = $this->createArticle('Second article', 'Original second body');

    $account = $this->drupalCreateUser([
      'access content',
      'edit any article content',
      'edit protected views entity form field',
    ]);
    $this->drupalLogin($account);

    $this->drupalGet(self::EDIT_PATH);
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);
    $assert_session->fieldExists($this->bodyValueName($first_node));
    $assert_session->fieldExists($this->bodyValueName($second_node));

    $this->submitForm([
      $this->bodyValueName($first_node) => 'Updated first body',
      $this->bodyValueName($second_node) => 'Updated second body',
    ], 'Save');

    $this->assertSame('Updated first body', $this->reloadBodyValue($first_node));
    $this->assertSame('Updated second body', $this->reloadBodyValue($second_node));
  }

  /**
   * Tests users without entity update access cannot use or forge the form.
   */
  public function testEntityUpdateAccessIsRequired(): void {
    $node = $this->createArticle('No entity access', 'Original body');

    $account = $this->drupalCreateUser([
      'access content',
      'edit protected views entity form field',
    ]);
    $this->drupalLogin($account);

    $this->drupalGet(self::EDIT_PATH);
    $this->assertFormFieldIsNotEditable($node);

    $this->submitForgedForm([
      $this->bodyValueName($node) => 'Forged entity update',
    ]);

    $this->assertSame('Original body', $this->reloadBodyValue($node));
  }

  /**
   * Tests users without field edit access cannot use or forge the form.
   */
  public function testFieldEditAccessIsRequired(): void {
    $node = $this->createArticle('No field access', 'Original body');

    $account = $this->drupalCreateUser([
      'access content',
      'edit any article content',
    ]);
    $this->drupalLogin($account);

    $this->drupalGet(self::EDIT_PATH);
    $this->assertFormFieldIsNotEditable($node);

    $this->submitForgedForm([
      $this->bodyValueName($node) => 'Forged field update',
    ]);

    $this->assertSame('Original body', $this->reloadBodyValue($node));
  }

  /**
   * Tests inaccessible rows can render through a configured fallback view mode.
   */
  public function testFallbackViewModeForInaccessibleField(): void {
    $node = $this->createArticle('Fallback article', 'Fallback body');

    $account = $this->drupalCreateUser([
      'access content',
      'edit any article content',
    ]);
    $this->drupalLogin($account);

    $this->drupalGet(self::FALLBACK_PATH);
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Fallback body');
    $assert_session->fieldNotExists($this->bodyValueName($node));
    $assert_session->buttonNotExists('Save');
  }

  /**
   * Creates a test article.
   */
  private function createArticle(string $title, string $body): NodeInterface {
    return $this->createNode([
      'type' => 'article',
      'title' => $title,
      'status' => NodeInterface::PUBLISHED,
      'body' => [
        'value' => $body,
        'format' => 'plain_text',
      ],
    ]);
  }

  /**
   * Creates a page View containing the body entity form field.
   */
  private function createTestView(string $id, string $path, string $fallback_view_mode = ''): void {
    View::create([
      'id' => $id,
      'label' => $id,
      'base_table' => 'node_field_data',
      'status' => TRUE,
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_title' => 'Default',
          'position' => 0,
          'display_options' => [
            'access' => [
              'type' => 'perm',
              'options' => [
                'perm' => 'access content',
              ],
            ],
            'cache' => [
              'type' => 'tag',
            ],
            'query' => [
              'type' => 'views_query',
              'options' => [],
            ],
            'pager' => [
              'type' => 'none',
              'options' => [
                'offset' => 0,
              ],
            ],
            'style' => [
              'type' => 'default',
              'options' => [],
            ],
            'row' => [
              'type' => 'fields',
              'options' => [],
            ],
            'fields' => [
              'title' => [
                'id' => 'title',
                'table' => 'node_field_data',
                'field' => 'title',
                'entity_type' => 'node',
                'entity_field' => 'title',
                'plugin_id' => 'field',
                'label' => 'Title',
              ],
              'form_field_body' => [
                'id' => 'form_field_body',
                'table' => 'node_field_data',
                'field' => 'form_field_body',
                'relationship' => 'none',
                'group_type' => 'group',
                'admin_label' => '',
                'entity_type' => 'node',
                'field_name' => 'body',
                'plugin_id' => 'entity_form_field',
                'label' => 'Body',
                'plugin' => [
                  'hide_title' => FALSE,
                  'hide_description' => TRUE,
                  'fallback_view_mode' => $fallback_view_mode,
                  'type' => 'text_textarea_with_summary',
                  'settings' => [
                    'rows' => 5,
                    'summary_rows' => 3,
                    'placeholder' => '',
                    'show_summary' => FALSE,
                  ],
                  'third_party_settings' => [],
                ],
              ],
            ],
            'filters' => [
              'status' => [
                'id' => 'status',
                'table' => 'node_field_data',
                'field' => 'status',
                'entity_type' => 'node',
                'entity_field' => 'status',
                'plugin_id' => 'boolean',
                'value' => '1',
              ],
              'type' => [
                'id' => 'type',
                'table' => 'node_field_data',
                'field' => 'type',
                'entity_type' => 'node',
                'entity_field' => 'type',
                'plugin_id' => 'bundle',
                'value' => [
                  'article' => 'article',
                ],
              ],
            ],
            'sorts' => [
              'nid' => [
                'id' => 'nid',
                'table' => 'node_field_data',
                'field' => 'nid',
                'entity_type' => 'node',
                'entity_field' => 'nid',
                'plugin_id' => 'standard',
                'order' => 'ASC',
              ],
            ],
          ],
        ],
        'page_1' => [
          'display_plugin' => 'page',
          'id' => 'page_1',
          'display_title' => 'Page',
          'position' => 1,
          'display_options' => [
            'path' => $path,
          ],
        ],
      ],
    ])->save();
  }

  /**
   * Gets the submitted body value element name for a node row.
   */
  private function bodyValueName(NodeInterface $node): string {
    return sprintf('form_field_body[%d][body][0][value]', $node->id());
  }

  /**
   * Gets the current stored body value for a node.
   */
  private function reloadBodyValue(NodeInterface $node): string {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$node->id()]);
    /** @var \Drupal\node\NodeInterface $reloaded_node */
    $reloaded_node = $storage->load($node->id());

    return $reloaded_node->get('body')->value;
  }

  /**
   * Asserts a body form field is not available to the current user.
   */
  private function assertFormFieldIsNotEditable(NodeInterface $node): void {
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);
    $assert_session->fieldNotExists($this->bodyValueName($node));
    $assert_session->buttonNotExists('Save');
  }

  /**
   * Submits a crafted POST against the current View form.
   */
  private function submitForgedForm(array $values): void {
    $post = [];
    foreach ($this->getSession()->getPage()->findAll('css', 'form input[type="hidden"]') as $element) {
      $name = $element->getAttribute('name');
      if ($name !== NULL) {
        $post[$name] = $element->getAttribute('value');
      }
    }
    $post += $values;
    $post['op'] = 'Save';

    $this->getSession()->getDriver()->getClient()->request('POST', $this->getUrl(), $post);
  }

}
