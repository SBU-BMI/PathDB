<?php

namespace Drupal\bootstrap\Plugin\Provider;

/**
 * Class ProviderException retrieves the provider instance during exceptions.
 */
class ProviderException extends \RuntimeException {

  /**
   * The CDN Provider that threw the exception.
   *
   * @var \Drupal\bootstrap\Plugin\Provider\ProviderInterface
   */
  protected $provider;

  /**
   * ProviderException constructor.
   *
   * @param \Drupal\bootstrap\Plugin\Provider\ProviderInterface $provider
   *   The CDN Provider that threw the exception.
   * @param string $message
   *   The exception message.
   * @param int $code
   *   The exception code.
   * @param \Throwable|null $previous
   *   A previous exception.
   */
  public function __construct(ProviderInterface $provider, $message = "", $code = 0, ?\Throwable $previous = NULL) {
    parent::__construct($message, $code, $previous);
    $this->provider = $provider;
  }

  /**
   * Retrieves the CDN Provider instance.
   *
   * @return \Drupal\bootstrap\Plugin\Provider\ProviderInterface
   *   The CDN Provider instance.
   */
  public function getProvider() {
    return $this->provider;
  }

}
