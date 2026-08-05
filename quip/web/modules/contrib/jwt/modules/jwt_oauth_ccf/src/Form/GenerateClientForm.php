<?php

namespace Drupal\jwt_oauth_ccf\Form;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\jwt_oauth_ccf\ClientCredentialRepositoryInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Generates a new OAuth client credential and downloads the secret once.
 *
 * The plaintext secret is only ever available at generation time (the store
 * keeps a hash), so the submit handler streams it as a file download, mirroring
 * the users_jwt private-key flow.
 */
class GenerateClientForm extends FormBase {

  /**
   * Minimum length for a user-supplied client secret.
   */
  protected const MIN_SECRET_LENGTH = 16;

  /**
   * Maximum length for an administrator-supplied client id.
   */
  protected const MAX_CLIENT_ID_LENGTH = 128;

  /**
   * Permission required to set a client id explicitly.
   */
  protected const ADMINISTER_PERMISSION = 'administer oauth client credentials';

  /**
   * The client credential repository.
   *
   * @var \Drupal\jwt_oauth_ccf\ClientCredentialRepositoryInterface
   */
  protected ClientCredentialRepositoryInterface $repository;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected CacheTagsInvalidatorInterface $cacheTagsInvalidator;

  /**
   * Constructs the form.
   *
   * @param \Drupal\jwt_oauth_ccf\ClientCredentialRepositoryInterface $repository
   *   The client credential repository.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator.
   */
  public function __construct(ClientCredentialRepositoryInterface $repository, CacheTagsInvalidatorInterface $cache_tags_invalidator) {
    $this->repository = $repository;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('jwt_oauth_ccf.client_repository'),
      $container->get('cache_tags.invalidator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'jwt_oauth_ccf_generate_client_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?UserInterface $user = NULL) {
    if (!$user) {
      return $form;
    }

    $form['user'] = [
      '#type' => 'value',
      '#value' => $user,
    ];
    $form['instructions'] = [
      '#type' => 'item',
      '#markup' => $this->t('A new client id is generated together with a secret. Leave the secret blank to have a strong one generated and downloaded once, or enter your own below. Either way, anyone holding the secret can obtain tokens that act as %name, so store it securely and treat it like a password. The secret is stored only as a hash and cannot be recovered.', ['%name' => $user->getDisplayName()]),
    ];
    if ($this->currentUser()->hasPermission(self::ADMINISTER_PERMISSION)) {
      $form['client_id'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Client ID'),
        '#description' => $this->t('Optional. Leave blank to auto-generate a random client ID. May contain only letters, numbers, and the characters . _ - and must be unique across all accounts.'),
        '#required' => FALSE,
        '#maxlength' => self::MAX_CLIENT_ID_LENGTH,
        '#weight' => 5,
      ];
    }
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#description' => $this->t('A name to help you recognize this credential later (e.g. the integration it is used by).'),
      '#required' => TRUE,
      '#maxlength' => 128,
      '#weight' => 10,
    ];
    $form['secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client secret'),
      '#description' => $this->t('Optional. Leave blank to auto-generate a secret (downloaded once). If you set your own, it must be at least @min characters. It will be hashed on save and cannot be shown again.', ['@min' => self::MIN_SECRET_LENGTH]),
      '#required' => FALSE,
      // Capped to avoid any silent truncation by the password hasher.
      '#maxlength' => 72,
      '#weight' => 20,
    ];
    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => 30,
    ];
    $form['actions']['download'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate'),
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#attributes' => ['class' => ['button']],
      '#url' => Url::fromRoute('jwt_oauth_ccf.client_list', ['user' => $user->id()]),
    ];
    $form['#attached']['library'][] = 'jwt_oauth_ccf/download_redirect';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $secret = (string) $form_state->getValue('secret');
    if ($secret !== '' && mb_strlen($secret) < self::MIN_SECRET_LENGTH) {
      $form_state->setErrorByName('secret', $this->t('The client secret must be at least @min characters, or left blank to auto-generate one.', ['@min' => self::MIN_SECRET_LENGTH]));
    }
    if ($this->currentUser()->hasPermission(self::ADMINISTER_PERMISSION)) {
      $client_id = trim((string) $form_state->getValue('client_id'));
      if ($client_id !== '') {
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $client_id)) {
          $form_state->setErrorByName('client_id', $this->t('The client ID may contain only letters, numbers, and the characters . _ -'));
        }
        elseif ($this->repository->getClient($client_id) !== NULL) {
          $form_state->setErrorByName('client_id', $this->t('The client ID %id is already in use. Choose a different one or leave it blank to auto-generate.', ['%id' => $client_id]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\user\UserInterface $user */
    $user = $form_state->getValue('user');
    $label = trim((string) $form_state->getValue('label'));
    $secret_input = trim((string) $form_state->getValue('secret'));
    $secret = $secret_input === '' ? NULL : $secret_input;

    $client_id = NULL;
    if ($this->currentUser()->hasPermission(self::ADMINISTER_PERMISSION)) {
      $client_id_input = trim((string) $form_state->getValue('client_id'));
      $client_id = $client_id_input === '' ? NULL : $client_id_input;
    }

    $client = $this->repository->createClient((int) $user->id(), $label, $secret, $client_id);
    $this->cacheTagsInvalidator->invalidateTags(['jwt_oauth_ccf:' . $user->id()]);

    // A user-supplied secret is already known to them, so there is nothing to
    // download: confirm and return to the list.
    if ($client->secret === NULL) {
      $this->messenger()->addStatus($this->t('The OAuth client credential %label was created with client ID %id.', [
        '%label' => $client->label,
        '%id' => $client->clientId,
      ]));
      $form_state->setRedirect('jwt_oauth_ccf.client_list', ['user' => $user->id()]);
      return;
    }

    $token_url = Url::fromRoute('jwt_oauth_ccf.token')->setAbsolute()->toString();
    $body = $this->buildCredentialFile($client->clientId, $client->secret, $client->label, $token_url);

    $filename = $user->getAccountName() . '__oauth-client__' . $client->clientId . '.txt';
    $response = new Response($body);
    $response->setPrivate();
    // Clear the cookie set in JavaScript so the page can redirect afterwards.
    $response->headers->clearCookie('jwt_oauth_ccf_download', '/', NULL, FALSE, FALSE);
    $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
    $response->headers->set('Content-Disposition', $disposition);
    $response->headers->set('Content-Type', 'text/plain; charset=utf-8');
    $form_state->setResponse($response);
  }

  /**
   * Builds the human-readable credential file contents.
   *
   * @param string $client_id
   *   The client id.
   * @param string $secret
   *   The plaintext secret.
   * @param string $label
   *   The credential label.
   * @param string $token_url
   *   The absolute token endpoint URL.
   *
   * @return string
   *   The file contents.
   */
  protected function buildCredentialFile(string $client_id, string $secret, string $label, string $token_url): string {
    return <<<TXT
    OAuth 2.0 client credentials
    ============================

    Label:         {$label}
    Client ID:     {$client_id}
    Client secret: {$secret}

    Keep the client secret safe. It is not stored in a recoverable form and
    cannot be shown again. If lost, delete this credential and generate a new
    one.

    Token endpoint
    --------------
    {$token_url}

    Example (curl):

      curl -X POST {$token_url} \\
        -d grant_type=client_credentials \\
        -d client_id={$client_id} \\
        -d client_secret=YOUR_CLIENT_SECRET

    The response contains a short-lived Bearer JWT. Send it as:
      Authorization: Bearer <access_token>

    TXT;
  }

}
