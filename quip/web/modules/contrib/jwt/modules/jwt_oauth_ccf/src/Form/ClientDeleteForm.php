<?php

namespace Drupal\jwt_oauth_ccf\Form;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\jwt_oauth_ccf\ClientCredential;
use Drupal\jwt_oauth_ccf\ClientCredentialRepositoryInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Confirms deletion of an OAuth client credential.
 */
class ClientDeleteForm extends ConfirmFormBase {

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
   * The credential being deleted.
   *
   * @var \Drupal\jwt_oauth_ccf\ClientCredential
   */
  protected ClientCredential $client;

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
    return 'jwt_oauth_ccf_client_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $client_id = NULL, ?UserInterface $user = NULL) {
    if (!$user) {
      throw new NotFoundHttpException();
    }
    $client = $this->repository->getClient((string) $client_id);
    if (!$client || $client->uid !== (int) $user->id()) {
      throw new NotFoundHttpException();
    }
    $this->client = $client;

    $form['client'] = [
      '#type' => 'value',
      '#value' => $client,
    ];
    $form['summary'] = [
      '#type' => 'table',
      '#header' => [$this->t('Label'), $this->t('Client ID')],
      '#rows' => [[$client->label, $client->clientId]],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete this OAuth client credential?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Integrations using it will no longer be able to obtain new tokens. Tokens already issued remain valid until they expire. This cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('jwt_oauth_ccf.client_list', ['user' => $this->client->uid]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\jwt_oauth_ccf\ClientCredential $client */
    $client = $form_state->getValue('client');
    $this->repository->deleteClient($client->clientId);
    $this->cacheTagsInvalidator->invalidateTags(['jwt_oauth_ccf:' . $client->uid]);
    $this->messenger()->addStatus($this->t('The OAuth client credential %label has been deleted.', ['%label' => $client->label]));
    $form_state->setRedirect('jwt_oauth_ccf.client_list', ['user' => $client->uid]);
  }

}
