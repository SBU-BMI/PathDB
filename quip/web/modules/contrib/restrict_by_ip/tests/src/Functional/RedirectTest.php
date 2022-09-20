<?php

namespace Drupal\Tests\restrict_by_ip\Functional;

use Drupal\Core\Url;

/**
 * Tests user is redirected when login denied.
 *
 * @group restrict_by_ip
 */
class RedirectTest extends RestrictByIPWebTestBase {

  /**
   * Test node.
   *
   * @var \Drupal\node\Entity\Node
   */
  private $loginDeniedNode;

  /**
   * Required modules.
   *
   * @var array
   */
  public static $modules = [
    'restrict_by_ip',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create a page users will get redirected to when denied login.
    $type = $this->drupalCreateContentType();
    $this->loginDeniedNode = $this->drupalCreateNode(['type' => $type->id()]);
    $this->conf->set('error_page', 'node/' . $this->loginDeniedNode->id())->save();
  }

  // User redirected when outside global range and no destination query.

  /**
   * Parameter is present.
   */
  public function testIpDifferGlobalNoDestination() {
    // Add global IP range.
    $this->conf->set('login_range', $this->outOfRangeCIDR)->save();
    $this->assertRedirected();
  }

  // User redirected when outside user range and no destination query parameter.

  /**
   * Is present.
   */
  public function testIpDifferUserNoDestination() {
    // Add out of range user IP.
    $this->conf->set('user.' . $this->regularUser->id(), $this->outOfRangeCIDR)->save();
    $this->assertRedirected();
  }

  // User redirected when outside global range and a destination query parameter.

  /**
   * Is present.
   */
  public function testIpDifferGlobalWithDestination() {
    // Add global IP range.
    $this->conf->set('login_range', $this->outOfRangeCIDR)->save();
    $this->assertRedirected('node');
  }

  // User redirected when outside user range and a destination query parameter.

  /**
   * Is present.
   */
  public function testIpDifferUserWithDestination() {
    // Add out of range user IP.
    $this->conf->set('user.' . $this->regularUser->id(), $this->outOfRangeCIDR)->save();
    $this->assertRedirected('node');
  }

  /**
   * Assert user gets redirected when login denied.
   */
  private function assertRedirected($destination = NULL) {
    $edit = [
      'name' => $this->regularUser->label(),
      'pass' => $this->regularUser->pass_raw,
    ];

    $options = ['external' => FALSE];
    if (isset($destination)) {
      $options['query'] = ['destination' => $destination];
    }

    $this->drupalGet(Url::fromRoute('user.login', [], $options));
    $this->submitForm($edit, t('Log in'));

    $this->assertFalse($this->drupalUserIsLoggedIn($this->regularUser));

    $this->assertSession()->pageTextContains($this->loginDeniedNode->label());
  }

}
