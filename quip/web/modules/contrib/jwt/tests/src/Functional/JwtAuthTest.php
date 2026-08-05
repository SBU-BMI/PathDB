<?php

namespace Drupal\Tests\jwt\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Url;
use Drupal\jwt_test\EventSubscriber\JwtTestAuthIssuerSubscriber;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests for jwt authentication provider.
 *
 * @see \Drupal\Tests\basic_auth\Functional\BasicAuthTest
 *
 * @group jwt
 */
class JwtAuthTest extends BrowserTestBase {

  /**
   * Modules installed for all tests.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'router_test',
    'key',
    'jwt',
    'jwt_auth_issuer',
    'jwt_auth_consumer',
    'jwt_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test jwt authentication.
   */
  public function testJwtAuth() {
    // Enable page caching.
    $config = $this->config('system.performance');
    $config->set('cache.page.max_age', 300);
    $config->save();

    $account = $this->drupalCreateUser(['access content']);
    /** @var \Drupal\jwt\Transcoder\JwtTranscoderInterface $transcoder */
    $transcoder = $this->container->get('jwt.transcoder');
    $this->setCurrentUser($account);
    /** @var \Drupal\jwt\Authentication\Provider\JwtAuth $auth */
    $auth = $this->container->get('jwt.authentication.jwt');
    $token = $auth->generateToken();
    $decoded_jwt = $transcoder->decode($token);
    $this->assertEquals($account->id(), $decoded_jwt->getClaim(['drupal', 'uid']));
    $expected_cache_header = version_compare(\Drupal::VERSION, '10.4.0', '>=') ? 'UNCACHEABLE (request policy)' : NULL;
    foreach (['jwt_test.11.1', 'jwt_test.11.2'] as $route_name) {
      $url = Url::fromRoute($route_name);
      foreach (['Authorization', 'JWT-Authorization'] as $header_name) {
        $headers = [
          $header_name => 'Bearer ' . $token,
        ];
        $this->drupalGet($url, [], $headers);
        $this->assertSession()->statusCodeEquals(200);
        $this->assertSession()->pageTextContains($account->getAccountName());
        self::assertEquals($expected_cache_header, $this->getSession()->getResponseHeader('X-Drupal-Cache'));
        self::assertFalse(strpos($this->getSession()->getResponseHeader('Cache-Control'), 'public'), 'Cache-Control is not set to public');
        $account->block()->save();
        $this->drupalGet($url, [], $headers);
        $this->assertSession()->statusCodeEquals(403);
        $account->activate()->save();
        // This is needed to prevent the Authorization header from the last loop
        // being sent again by the mink session.
        $this->mink->resetSessions();
        $headers = [
          $header_name => 'Bearer ' . $this->randomMachineName(),
        ];
        $this->drupalGet($url, [], $headers);
        // Bad jwt token does not authenticate the user.
        $this->assertSession()->pageTextNotContains($account->getAccountName());
        $this->assertSession()->statusCodeEquals(403);
        $this->mink->resetSessions();
      }
    }
    // Test the 300 seconds of default leeway on nbf, iat, and exp values.
    $url = Url::fromRoute('jwt_test.11.2');
    $time = time();
    $test_cases = [
      ['claims' => ['iat' => $time + 250], 'code' => 200],
      ['claims' => ['nbf' => $time + 250], 'code' => 200],
      ['claims' => ['exp' => $time - 250], 'code' => 200],
      ['claims' => ['iat' => $time + 350], 'code' => 403],
      ['claims' => ['nbf' => $time + 350], 'code' => 403],
      ['claims' => ['iat' => $time, 'nbf' => $time + 350], 'code' => 403],
      ['claims' => ['exp' => $time - 350], 'code' => 403],
    ];
    foreach ($test_cases as $case) {
      JwtTestAuthIssuerSubscriber::$modifications = $case['claims'];
      $token = $auth->generateToken();
      $headers = [
        'Authorization' => 'Bearer ' . $token,
      ];
      $this->drupalGet($url, [], $headers);
      $this->assertSession()->statusCodeEquals($case['code']);
      if ($case['code'] === 200) {
        $this->assertSession()->pageTextContains($account->getAccountName());
      }
      $this->mink->resetSessions();
    }
    JwtTestAuthIssuerSubscriber::$modifications = [];
    // The front page should return a 200 even for an invalid JWT.
    foreach (['Authorization', 'JWT-Authorization'] as $header_name) {
      $headers = [
        $header_name => 'Bearer ' . $this->randomMachineName(),
      ];
      $this->drupalGet('<front>', [], $headers);
      // Bad jwt token does not authenticate the user.
      $this->assertSession()->pageTextNotContains($account->getAccountName());
      $this->assertSession()->statusCodeEquals(200);
      $this->mink->resetSessions();
    }
    // Ensure that pages already in the page cache aren't returned from page
    // cache if jwt credentials are provided.
    $url = Url::fromRoute('jwt_test.10');
    $this->drupalGet($url);
    $this->assertEquals($this->getSession()->getResponseHeader('X-Drupal-Cache'), 'MISS');
    $this->drupalGet($url);
    $this->assertEquals($this->getSession()->getResponseHeader('X-Drupal-Cache'), 'HIT');
    $token = $auth->generateToken();
    foreach (['Authorization', 'JWT-Authorization'] as $header_name) {
      $headers = [
        $header_name => 'Bearer ' . $token,
      ];
      $this->drupalGet($url, [], $headers);
      $this->assertSession()->statusCodeEquals(200);
      self::assertEquals($expected_cache_header, $this->getSession()->getResponseHeader('X-Drupal-Cache'));
      $this->assertFalse(strpos($this->getSession()->getResponseHeader('Cache-Control'), 'public'), 'No page cache response when requesting a cached page with jwt credentials.');
      // This is needed to prevent the Authorization header from the last loop
      // being sent again by the mink session.
      $this->mink->resetSessions();
    }
    // Verify the fallback header can be used in combination with basic_auth.
    $modules = ['basic_auth'];
    $success = $this->container->get('module_installer')->install($modules, TRUE);
    $this->assertTrue($success, new FormattableMarkup('Enabled modules: %modules', ['%modules' => implode(', ', $modules)]));
    $username = $account->getAccountName();
    $password = $account->pass_raw;
    $url = Url::fromRoute('jwt_test.11.2');
    $headers = ['Authorization' => 'Basic ' . base64_encode("$username:$password")];
    $this->drupalGet($url, [], $headers);
    $this->assertSession()->statusCodeEquals(200);
    // Account name is displayed.
    $this->assertSession()->pageTextContains($account->getAccountName());
    $this->mink->resetSessions();
    // This simulates a site where the basic auth is validated by the
    // webserver or shield module or otherwise is not valid as a user login.
    $headers = ['Authorization' => 'Basic ' . $this->randomMachineName()];
    $this->drupalGet($url, [], $headers);
    // The response seems to vary between 401 and 403, either is fine.
    $code = (int) $this->getSession()->getStatusCode();
    $this->assertTrue(in_array($code, [401, 403], TRUE), 'Access is not granted.');
    $this->mink->resetSessions();
    $token = $auth->generateToken();
    $headers += ['JWT-Authorization' => 'Bearer ' . $token];
    $this->drupalGet($url, [], $headers);
    $this->assertSession()->statusCodeEquals(200);
    // Account name is displayed.
    $this->assertSession()->pageTextContains($account->getAccountName());
  }

}
