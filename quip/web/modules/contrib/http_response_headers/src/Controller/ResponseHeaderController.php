<?php

namespace Drupal\http_response_headers\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * HTTP Response Header entity related controller.
 */
class ResponseHeaderController extends ControllerBase {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * ModalFormContactController constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger')
    );
  }

  /**
   * Set HTTP Response header active.
   */
  public function enable($response_header) {
    $response_header->set('status', TRUE);
    $response_header->save();
    $this->messenger->addMessage($this->t('%name header enabled', ['%name' => $response_header->label()]));
    return $this->redirect('entity.response_header.collection');
  }

  /**
   * Set HTTP Response header inactive.
   */
  public function disable($response_header) {
    $response_header->set('status', FALSE);
    $response_header->save();
    $this->messenger->addMessage($this->t('%name header disabled', ['%name' => $response_header->label()]));
    return $this->redirect('entity.response_header.collection');
  }

}
