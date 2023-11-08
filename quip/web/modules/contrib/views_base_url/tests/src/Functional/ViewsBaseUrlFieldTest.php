<?php

namespace Drupal\Tests\views_base_url\Functional;

use Drupal\Component\Utility\Random;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Basic test for views base url.
 *
 * @group views_base_url
 */
class ViewsBaseUrlFieldTest extends BrowserTestBase {

  use TestFileCreationTrait {
    getTestFiles as drupalGetTestFiles;
  }

  /**
   * A user with various administrative privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * The installation profile to use with this test.
   *
   * This test class requires the "tags" taxonomy field.
   *
   * @var string
   */
  protected $profile = 'standard';

  /**
   * Node count.
   *
   * Number of nodes to be created in the tests.
   *
   * @var int
   */
  protected $nodeCount = 5;

  /**
   * Nodes.
   *
   * The nodes that is going to be created in the tests.
   *
   * @var array
   */
  protected $nodes;

  /**
   * Path alias storage.
   *
   * @var \Drupal\path_alias\PathAliasStorage
   */
  protected $pathAliasStorage;

  /**
   * Path alias manager.
   *
   * @var \Drupal\path_alias\AliasManager
   */
  protected $pathAliasManager;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'views_base_url_test',
  ];

  /**
   * Definition of File System Interface.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'create article content',
    ]);
    $random = new Random();

    /** @var \Drupal\path_alias\PathAliasStorage $pathAliasStorage */
    $this->pathAliasStorage = $this->container->get('entity_type.manager')->getStorage('path_alias');
    /** @var \Drupal\path_alias\AliasManager $pathAliasManager */
    $this->pathAliasManager = $this->container->get('path_alias.manager');
    /** @var \Drupal\Core\File\FileSystemInterface $fileSystem */;
    $this->fileSystem = $this->container->get('file_system');
    // Create $this->nodeCount nodes.
    $this->drupalLogin($this->adminUser);
    for ($i = 1; $i <= $this->nodeCount; $i++) {
      // Create node.
      $title = $random->name();
      $image = current($this->drupalGetTestFiles('image'));
      $edit = [
        'title[0][value]' => $title,
        'files[field_image_0]' => $this->fileSystem->realpath($image->uri),
      ];
      $this->drupalGet('node/add/article');
      $this->submitForm($edit, t('Save'));
      $this->submitForm(['field_image[0][alt]' => $title], t('Save'));

      $this->nodes[$i] = $this->drupalGetNodeByTitle($title);
      $path_alias = $this->pathAliasStorage->create([
        'path' => '/node/' . $this->nodes[$i]->id(),
        'alias' => "/content/" . $title,
      ]);
      $path_alias->save();
    }
    $this->drupalLogout();
  }

  /**
   * Tests views base url field when `show_link` enabled and no link settings.
   */
  protected function assertViewsBaseUrlLinkNoSettings() {
    global $base_url;

    $this->drupalGet('views-base-url-link-no-settings-test');
    $this->assertSession()->statusCodeEquals(200);

    $elements = $this->xpath('//div[contains(@class,"view-views-base-url-link-no-settings-test")]/div[@class="view-content"]/div[contains(@class,"views-row")]');
    $this->assertEquals(count($elements), $this->nodeCount, t('There are @count rows', [
      '@count' => $this->nodeCount,
    ]));

    $link_path = $base_url;
    $link_text = $link_path;
    $elements = $this->xpath('//a[@href=:path and text()=:text]', [
      ':path' => $link_path,
      ':text' => $link_text,
    ]);
    $this->assertEquals(count($elements), $this->nodeCount, 'Views base url rendered as link with no settings set');
  }

  /**
   * Tests views base url field when `show_link` is disabled.
   */
  public function testViewsBaseUrlNoLink() {
    global $base_url;

    $this->drupalGet('views-base-url-nolink-test');
    $this->assertSession()->statusCodeEquals(200);

    $elements = $this->xpath('//div[contains(@class,"view-views-base-url-no-link-test")]/div[@class="view-content"]/div[contains(@class,"views-row")]');
    $this->assertEquals(count($elements), $this->nodeCount, t('There are @count rows', [
      '@count' => $this->nodeCount,
    ]));

    $elements = $this->xpath('//div[contains(@class,"views-field-base-url")]/span[@class="field-content" and text()=:value]', [
      ':value' => $base_url,
    ]);
    $this->assertEquals(count($elements), $this->nodeCount, t('Base url is displayed @count times', [
      '@count' => $this->nodeCount,
    ]));
  }

  /**
   * Tests views base url field when `show_link` enabled and all settings set.
   */
  public function testViewsBaseUrlLinkAllSettings() {
    global $base_url;

    $this->drupalGet('views-base-url-link-all-settings-test');
    $this->assertSession()->statusCodeEquals(200);

    $elements = $this->xpath('//div[contains(@class,"view-views-base-url-link-all-settings-test")]/div[@class="view-content"]/div[contains(@class,"views-row")]');
    $this->assertEquals(count($elements), $this->nodeCount, t('There are @count rows', [
      '@count' => $this->nodeCount,
    ]));

    foreach ($this->nodes as $node) {
      $link_class = 'views-base-url-test';
      $link_title = $node->getTitle();
      $link_text = $node->getTitle();
      $link_rel = 'rel-attribute';
      $link_target = '_blank';
      $link_path = Url::fromUri($base_url . $this->pathAliasManager->getAliasByPath('/node/' . $node->id()), [
        'attributes' => [
          'class' => $link_class,
          'title' => $link_title,
          'rel' => $link_rel,
          'target' => $link_target,
        ],
        'fragment' => 'new',
        'query' => [
          'destination' => 'node',
        ],
      ])->toUriString();

      $elements = $this->xpath('//a[@href=:path and @class=:class and @title=:title and @rel=:rel and @target=:target and text()=:text]', [
        ':path' => $link_path,
        ':class' => $link_class,
        ':title' => $link_title,
        ':rel' => $link_rel,
        ':target' => $link_target,
        ':text' => $link_text,
      ]);
      $this->assertEquals(count($elements), 1, 'Views base url rendered as link with all settings');
    }
  }

  /**
   * Tests views base url field when `show_link` enabled and `link_path` set.
   */
  public function testViewsBaseUrlLinkLinkPath() {
    global $base_url;

    $this->drupalGet('views-base-url-link-link-path-test');
    $this->assertSession()->statusCodeEquals(200);

    $elements = $this->xpath('//div[contains(@class,"view-views-base-url-link-link-path-test")]/div[@class="view-content"]/div[contains(@class,"views-row")]');
    $this->assertEquals(count($elements), $this->nodeCount, t('There are @count rows', [
      '@count' => $this->nodeCount,
    ]));

    foreach ($this->nodes as $node) {
      $link_path = Url::fromUri($base_url . $this->pathAliasManager->getAliasByPath('/node/' . $node->id()))->toUriString();
      $link_text = $link_path;

      $elements = $this->xpath('//a[@href=:path and text()=:text]', [
        ':path' => $link_path,
        ':text' => $link_text,
      ]);
      $this->assertEquals(count($elements), 1, 'Views base url rendered as link with link path set');
    }
  }

  /**
   * Tests views base url field when `show_link` enabled and no `link_path`.
   */
  public function testViewsBaseUrlLinkNoLinkPath() {
    $this->assertViewsBaseUrlLinkNoSettings();
  }

  /**
   * Tests views base url field when `show_link` enabled and `link_text` set.
   */
  public function testViewsBaseUrlLinkLinkText() {
    global $base_url;

    $this->drupalGet('views-base-url-link-link-text-test');
    $this->assertSession()->statusCodeEquals(200);

    $elements = $this->xpath('//div[contains(@class,"view-views-base-url-link-link-text-test")]/div[@class="view-content"]/div[contains(@class,"views-row")]');
    $this->assertEquals(count($elements), $this->nodeCount, t('There are @count rows', [
      '@count' => $this->nodeCount,
    ]));

    foreach ($this->nodes as $node) {
      $link_path = $base_url;
      $link_text = $node->getTitle();

      $elements = $this->xpath('//a[@href=:path and text()=:text]', [
        ':path' => $link_path,
        ':text' => $link_text,
      ]);
      $this->assertEquals(count($elements), 1, 'Views base url rendered as link with link text set');
    }
  }

  /**
   * Tests views base url field when `show_link` enabled and no `link_text` set.
   */
  public function testViewsBaseUrlLinkNoLinkText() {
    $this->assertViewsBaseUrlLinkNoSettings();
  }

  /**
   * Tests views base url field when `show_link` enabled and `link_query` set.
   */
  public function testViewsBaseUrlLinkLinkQuery() {
    global $base_url;

    $this->drupalGet('views-base-url-link-link-query-test');
    $this->assertSession()->statusCodeEquals(200);

    $elements = $this->xpath('//div[contains(@class,"view-views-base-url-link-link-query-test")]/div[@class="view-content"]/div[contains(@class,"views-row")]');
    $this->assertEquals(count($elements), $this->nodeCount, t('There are @count rows', [
      '@count' => $this->nodeCount,
    ]));

    $link_path = $base_url;
    $link_text = $link_path;

    $elements = $this->xpath('//a[@href=:path and text()=:text]', [
      ':path' => Url::fromUri($link_path, [
        'query' => [
          'destination' => 'node',
        ],
      ])->toUriString(),
      ':text' => $link_text,
    ]);
    $this->assertEquals(count($elements), $this->nodeCount, 'Views base url rendered as link with link query set');
  }

  /**
   * Tests views base url field when `show_link` enabled and no `link_query`.
   */
  public function testViewsBaseUrlLinkNoLinkQuery() {
    $this->assertViewsBaseUrlLinkNoSettings();
  }

  /**
   * Tests views base url field when rendered as image.
   */
  public function testViewsBaseUrlImage() {
    global $base_url;

    $this->drupalGet('views-base-url-image-test');
    $this->assertSession()->statusCodeEquals(200);

    $elements = $this->xpath('//div[contains(@class,"view-views-base-url-image-test")]/div[@class="view-content"]/div[contains(@class,"views-row")]');
    $this->assertEquals(count($elements), $this->nodeCount, t('There are @count rows', [
      '@count' => $this->nodeCount,
    ]));

    foreach ($this->nodes as $node) {
      $field = $node->get('field_image');
      $value = $field->getValue();

      $image_uri = \Drupal::service('file_url_generator')->generateString($field->entity->getFileUri());
      $image_alt = $value[0]['alt'];
      $image_width = $value[0]['width'];
      $image_height = $value[0]['height'];

      $link_class = 'views-base-url-test';
      $link_title = $node->getTitle();
      $link_rel = 'rel-attribute';
      $link_target = '_blank';
      $link_path = Url::fromUri($base_url . $this->pathAliasManager->getAliasByPath('/node/' . $node->id()), [
        'attributes' => [
          'class' => $link_class,
          'title' => $link_title,
          'rel' => $link_rel,
          'target' => $link_target,
        ],
        'fragment' => 'new',
        'query' => [
          'destination' => 'node',
        ],
      ])->toUriString();

      $elements = $this->xpath('//a[@href=:path and @class=:class and @title=:title and @rel=:rel and @target=:target]/img[@src=:url and @width=:width and @height=:height and @alt=:alt]', [
        ':path' => $link_path,
        ':class' => $link_class,
        ':title' => $link_title,
        ':rel' => $link_rel,
        ':target' => $link_target,
        ':url' => $image_uri,
        ':width' => $image_width,
        ':height' => $image_height,
        ':alt' => $image_alt,
      ]);
      $this->assertEquals(count($elements), 1, 'Views base url rendered as link image');
    }
  }

}
