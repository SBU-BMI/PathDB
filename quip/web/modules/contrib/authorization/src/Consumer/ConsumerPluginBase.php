<?php

namespace Drupal\authorization\Consumer;

use Drupal\authorization\Plugin\ConfigurableAuthorizationPluginBase;

/**
 * Base class for Authorization consumer plugins.
 */
abstract class ConsumerPluginBase extends ConfigurableAuthorizationPluginBase implements ConsumerInterface {

  /**
   * Defines the type, for example used by getToken().
   *
   * @var string
   */
  protected $type = 'consumer';

  /**
   * Whether this plugins supports consumer target creation.
   *
   * @var bool
   */
  protected $allowConsumerTargetCreation = FALSE;

  /**
   * {@inheritdoc}
   */
  public function consumerTargetCreationAllowed() {
    return $this->allowConsumerTargetCreation;
  }

  /**
   * {@inheritdoc}
   */
  public function filterProposals(array $proposals, array $consumer_mapping) {
    if (!empty($proposals)) {
      $property = array_pop($consumer_mapping);
      return [$property => $property];
    }
    else {
      return [];
    }
  }

}
