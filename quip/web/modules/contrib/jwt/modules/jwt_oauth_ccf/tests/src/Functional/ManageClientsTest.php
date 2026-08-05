<?php

namespace Drupal\Tests\jwt_oauth_ccf\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the per-user OAuth client credential management UI.
 *
 * @group jwt
 */
class ManageClientsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'jwt',
    'jwt_oauth_ccf',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The credential repository.
   *
   * @var \Drupal\jwt_oauth_ccf\ClientCredentialRepositoryInterface
   */
  protected $repository;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->repository = $this->container->get('jwt_oauth_ccf.client_repository');
  }

  /**
   * Returns the client-list URL for a user.
   */
  protected function listUrl($account): Url {
    return Url::fromRoute('jwt_oauth_ccf.client_list', ['user' => $account->id()]);
  }

  /**
   * An owner with the "manage own" permission can manage their credentials.
   *
   * This also guards against a strict-comparison regression in the access
   * check, where the current user's integer id would never match the upcast
   * user entity's string id.
   */
  public function testOwnerCanManageOwnCredentials(): void {
    $owner = $this->drupalCreateUser(['manage own oauth client credentials']);
    $this->drupalLogin($owner);

    $this->drupalGet($this->listUrl($owner));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('No OAuth client credentials found.');
  }

  /**
   * A user cannot reach another account's credential pages.
   */
  public function testOwnerCannotManageOthers(): void {
    $owner = $this->drupalCreateUser(['manage own oauth client credentials']);
    $other = $this->drupalCreateUser(['manage own oauth client credentials']);
    $this->drupalLogin($owner);

    $this->drupalGet($this->listUrl($other));
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Without the permission even your own page is forbidden.
   */
  public function testNoPermissionIsForbidden(): void {
    $account = $this->drupalCreateUser([]);
    $this->drupalLogin($account);

    $this->drupalGet($this->listUrl($account));
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * An administrator can manage any account's credentials.
   */
  public function testAdminCanManageAnyAccount(): void {
    $admin = $this->drupalCreateUser(['administer oauth client credentials']);
    $target = $this->drupalCreateUser([]);
    $this->drupalLogin($admin);

    $this->drupalGet($this->listUrl($target));
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * A blank secret is auto-generated and streamed as a file download.
   */
  public function testGenerateDownloadsSecret(): void {
    $owner = $this->drupalCreateUser(['manage own oauth client credentials']);
    $this->drupalLogin($owner);

    $this->drupalGet(Url::fromRoute('jwt_oauth_ccf.client_generate', ['user' => $owner->id()]));
    $this->submitForm(['label' => 'My integration'], 'Generate');

    // The response is a text file attachment, not an HTML page.
    $disposition = $this->getSession()->getResponseHeader('Content-Disposition');
    $this->assertStringContainsString('attachment', $disposition);
    $content = $this->getSession()->getPage()->getContent();
    $this->assertStringContainsString('Client ID:', $content);
    $this->assertStringContainsString('Client secret:', $content);

    // The credential is now stored for the owner.
    $clients = $this->repository->getUserClients((int) $owner->id());
    $this->assertCount(1, $clients);
    $client = reset($clients);
    $this->assertSame('My integration', $client->label);
  }

  /**
   * A supplied secret is stored without a download, with a confirmation.
   */
  public function testGenerateWithSuppliedSecret(): void {
    $owner = $this->drupalCreateUser(['manage own oauth client credentials']);
    $this->drupalLogin($owner);

    $this->drupalGet(Url::fromRoute('jwt_oauth_ccf.client_generate', ['user' => $owner->id()]));
    $this->submitForm([
      'label' => 'Supplied',
      'secret' => 'a-long-enough-secret-value',
    ], 'Generate');

    // No download: we are redirected back to the list with a status message.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('was created with client ID');
    $this->assertSession()->pageTextContains('Supplied');
  }

  /**
   * A too-short supplied secret is rejected by validation.
   */
  public function testGenerateRejectsShortSecret(): void {
    $owner = $this->drupalCreateUser(['manage own oauth client credentials']);
    $this->drupalLogin($owner);

    $this->drupalGet(Url::fromRoute('jwt_oauth_ccf.client_generate', ['user' => $owner->id()]));
    $this->submitForm([
      'label' => 'Too short',
      'secret' => 'short',
    ], 'Generate');

    $this->assertSession()->pageTextContains('The client secret must be at least 16 characters');
    $this->assertCount(0, $this->repository->getUserClients((int) $owner->id()));
  }

  /**
   * A non-admin owner does not see the client ID field.
   */
  public function testOwnerCannotSetClientId(): void {
    $owner = $this->drupalCreateUser(['manage own oauth client credentials']);
    $this->drupalLogin($owner);

    $this->drupalGet(Url::fromRoute('jwt_oauth_ccf.client_generate', ['user' => $owner->id()]));
    $this->assertSession()->fieldNotExists('client_id');
  }

  /**
   * An administrator can set an explicit client ID.
   */
  public function testAdminCanSetClientId(): void {
    $admin = $this->drupalCreateUser(['administer oauth client credentials']);
    $target = $this->drupalCreateUser([]);
    $this->drupalLogin($admin);

    $this->drupalGet(Url::fromRoute('jwt_oauth_ccf.client_generate', ['user' => $target->id()]));
    $this->assertSession()->fieldExists('client_id');
    $this->submitForm([
      'client_id' => 'my-chosen-id',
      'label' => 'Admin set',
      'secret' => 'a-long-enough-secret-value',
    ], 'Generate');

    $this->assertSession()->pageTextContains('was created with client ID my-chosen-id');
    $client = $this->repository->getClient('my-chosen-id');
    $this->assertNotNull($client);
    $this->assertSame((int) $target->id(), $client->uid);
  }

  /**
   * A blank client ID still auto-generates one for an administrator.
   */
  public function testAdminBlankClientIdAutoGenerates(): void {
    $admin = $this->drupalCreateUser(['administer oauth client credentials']);
    $target = $this->drupalCreateUser([]);
    $this->drupalLogin($admin);

    $this->drupalGet(Url::fromRoute('jwt_oauth_ccf.client_generate', ['user' => $target->id()]));
    $this->submitForm([
      'label' => 'Auto id',
      'secret' => 'a-long-enough-secret-value',
    ], 'Generate');

    $this->assertSession()->pageTextContains('was created with client ID');
    $clients = $this->repository->getUserClients((int) $target->id());
    $this->assertCount(1, $clients);
    $client = reset($clients);
    $this->assertStringStartsWith('ccf_', $client->clientId);
  }

  /**
   * A duplicate client ID is rejected by validation.
   */
  public function testAdminDuplicateClientIdRejected(): void {
    $admin = $this->drupalCreateUser(['administer oauth client credentials']);
    $target = $this->drupalCreateUser([]);
    $this->repository->createClient((int) $target->id(), 'Existing', 'a-long-enough-secret-value', 'taken-id');
    $this->drupalLogin($admin);

    $this->drupalGet(Url::fromRoute('jwt_oauth_ccf.client_generate', ['user' => $target->id()]));
    $this->submitForm([
      'client_id' => 'taken-id',
      'label' => 'Dupe',
      'secret' => 'a-long-enough-secret-value',
    ], 'Generate');

    $this->assertSession()->pageTextContains('The client ID taken-id is already in use');
    // No second credential was created.
    $this->assertCount(1, $this->repository->getUserClients((int) $target->id()));
  }

  /**
   * A client ID with invalid characters is rejected by validation.
   */
  public function testAdminInvalidClientIdRejected(): void {
    $admin = $this->drupalCreateUser(['administer oauth client credentials']);
    $target = $this->drupalCreateUser([]);
    $this->drupalLogin($admin);

    $this->drupalGet(Url::fromRoute('jwt_oauth_ccf.client_generate', ['user' => $target->id()]));
    $this->submitForm([
      'client_id' => 'bad id/with slash',
      'label' => 'Invalid',
      'secret' => 'a-long-enough-secret-value',
    ], 'Generate');

    $this->assertSession()->pageTextContains('The client ID may contain only letters, numbers');
    $this->assertCount(0, $this->repository->getUserClients((int) $target->id()));
  }

  /**
   * A credential can be deleted through the confirm form.
   */
  public function testDeleteCredential(): void {
    $owner = $this->drupalCreateUser(['manage own oauth client credentials']);
    $this->drupalLogin($owner);
    $client = $this->repository->createClient((int) $owner->id(), 'To delete', 'a-long-enough-secret-value');

    $this->drupalGet($this->listUrl($owner));
    $this->assertSession()->pageTextContains('To delete');

    $this->drupalGet(Url::fromRoute('jwt_oauth_ccf.client_delete', [
      'user' => $owner->id(),
      'client_id' => $client->clientId,
    ]));
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Confirm');

    $this->assertSession()->pageTextContains('has been deleted');
    $this->assertCount(0, $this->repository->getUserClients((int) $owner->id()));
  }

  /**
   * Deleting a credential that is not the user's returns a 404.
   */
  public function testDeleteForeignCredentialNotFound(): void {
    $owner = $this->drupalCreateUser(['manage own oauth client credentials']);
    $other = $this->drupalCreateUser(['manage own oauth client credentials']);
    // A credential that belongs to $other, addressed through $owner's path.
    $client = $this->repository->createClient((int) $other->id(), 'Foreign', 'a-long-enough-secret-value');

    $this->drupalLogin($owner);
    $this->drupalGet(Url::fromRoute('jwt_oauth_ccf.client_delete', [
      'user' => $owner->id(),
      'client_id' => $client->clientId,
    ]));
    $this->assertSession()->statusCodeEquals(404);
  }

}
