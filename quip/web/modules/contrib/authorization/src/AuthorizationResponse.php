<?php

declare(strict_types = 1);

namespace Drupal\authorization;

/**
 * Response object for the output of grantsAndRevokes().
 */
class AuthorizationResponse {

  /**
   * Label of authorization message plus optional information (e.g. skipped).
   *
   * @var string
   */
  private $message;

  /**
   * Whether the profile was skipped outright.
   *
   * @var bool
   */
  private $skipped;

  /**
   * Any authorizations applied.
   *
   * @var array
   */
  private $authorizationsApplied = [];

  /**
   * AuthorizationResponse constructor.
   *
   * @param string $message
   *   Message.
   * @param bool $skipped
   *   Authorization skipped.
   * @param array $authorizations_applied
   *   Authorizations applied.
   */
  public function __construct(string $message, bool $skipped, array $authorizations_applied) {
    $this->message = $message;
    $this->skipped = $skipped;
    $this->authorizationsApplied = $authorizations_applied;
  }

  /**
   * Get the message.
   *
   * @return string
   *   The message.
   */
  public function getMessage(): string {
    return $this->message;
  }

  /**
   * If the authorization was skipped.
   *
   * @return bool
   *   If skipped.
   */
  public function getSkipped(): bool {
    return $this->skipped;
  }

  /**
   * The authorizations applied.
   *
   * @return array
   *   Authorizations.
   */
  public function getAuthorizationsApplied(): array {
    return $this->authorizationsApplied;
  }

}
