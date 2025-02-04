<?php

namespace Drupal\http_response_headers\EventSubscriber;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Plugin\Exception\MissingValueContextException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Condition\ConditionAccessResolverTrait;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides AddHTTPHeaders.
 */
class AddHTTPHeaders implements EventSubscriberInterface {

  use ConditionAccessResolverTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The plugin context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * The context manager service.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * Constructs a new Google Tag response subscriber.
   * @param EntityTypeManagerInterface $entity_type_manager
   * @param ContextHandlerInterface $context_handler
   * @param ContextRepositoryInterface $context_repository
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ContextHandlerInterface $context_handler, ContextRepositoryInterface $context_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->contextHandler = $context_handler;
    $this->contextRepository = $context_repository;
  }

  /**
   * Sets extra HTTP headers.
   * @param ResponseEvent $event
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onRespond(ResponseEvent $event) {
    if (!$event->isMainRequest()) {
      return;
    }
    $response = $event->getResponse();
    $all_headers = $response->headers->allPreserveCaseWithoutCookies();

    $headers = $this->entityTypeManager->getStorage('response_header')->loadMultiple();
    if (!empty($headers)) {

      foreach ($headers as $key => $header) {
        if ($header->get('status')) {
          $pass_checked = TRUE;
          if (!empty($header->get('visibility'))) {
            $conditions = [];
            $missing_context = FALSE;
            $missing_value = FALSE;
            foreach ($header->getVisibilityConditions() as $condition_id => $condition) {
              if ($condition instanceof ContextAwarePluginInterface) {
                try {
                  $contexts = $this->contextRepository->getRuntimeContexts(array_values($condition->getContextMapping()));
                  $this->contextHandler->applyContextMapping($condition, $contexts);
                }
                catch (MissingValueContextException $e) {
                  $missing_value = TRUE;
                }
                catch (ContextException $e) {
                  $missing_context = TRUE;
                }
              }
              $conditions[$condition_id] = $condition;
            }
            if ($missing_context) {
              $pass_checked = FALSE;
            }
            elseif ($missing_value) {
              $pass_checked = FALSE;
            }
            elseif ($this->resolveConditions($conditions, 'and') !== FALSE) {
              $pass_checked = TRUE;
            }
            else {
              $pass_checked = FALSE;
            }
          }
          $header_key = $header->get('name');
          if ($pass_checked) {
            if (!empty($header->get('value'))) {
              if (!empty($all_headers[$header_key])) {
                unset($all_headers[$header_key]);
              }
              $all_headers[$header_key] = $header->get('value');
              $response->headers->replace($all_headers);
            }
            else {
              if (!empty($all_headers[$header_key])) {
                unset($all_headers[$header_key]);
                $response->headers->replace($all_headers);
              }
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::RESPONSE][] = ['onRespond', -100];
    return $events;
  }

}
