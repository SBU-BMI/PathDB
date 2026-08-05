<?php

namespace Drupal\jwt_oauth_ccf\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Url;
use Drupal\jwt_oauth_ccf\ClientCredentialRepositoryInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists a user's OAuth client credentials.
 */
class ClientListController extends ControllerBase {

  /**
   * The client credential repository.
   *
   * @var \Drupal\jwt_oauth_ccf\ClientCredentialRepositoryInterface
   */
  protected ClientCredentialRepositoryInterface $repository;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * Constructs the controller.
   *
   * @param \Drupal\jwt_oauth_ccf\ClientCredentialRepositoryInterface $repository
   *   The client credential repository.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   */
  public function __construct(ClientCredentialRepositoryInterface $repository, DateFormatterInterface $date_formatter) {
    $this->repository = $repository;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('jwt_oauth_ccf.client_repository'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Builds the credential list table.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user whose credentials are listed.
   *
   * @return array
   *   A render array.
   */
  public function listClients(UserInterface $user): array {
    $clients = $this->repository->getUserClients((int) $user->id());
    $header = [
      $this->t('Label'),
      $this->t('Client ID'),
      $this->t('Created'),
      $this->t('Operations'),
    ];
    $rows = [];
    foreach ($clients as $client) {
      $rows[] = [
        'label' => $client->label,
        'client_id' => $client->clientId,
        'created' => $this->dateFormatter->format($client->created, 'short'),
        'operations' => [
          'data' => [
            '#type' => 'dropbutton',
            '#links' => [
              'delete' => [
                'title' => $this->t('Delete'),
                'url' => Url::fromRoute('jwt_oauth_ccf.client_delete', [
                  'user' => $client->uid,
                  'client_id' => $client->clientId,
                ]),
              ],
            ],
          ],
        ],
      ];
    }

    return [
      '#cache' => ['tags' => ['jwt_oauth_ccf:' . $user->id()]],
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No OAuth client credentials found.'),
    ];
  }

}
