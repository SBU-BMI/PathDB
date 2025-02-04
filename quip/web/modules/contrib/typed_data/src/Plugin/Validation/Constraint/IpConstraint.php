<?php

namespace Drupal\typed_data\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraints\Ip;
use Symfony\Component\Validator\Constraints\IpValidator;

/**
 * IP address constraint.
 */
#[Constraint(
  id: 'Ip',
  label: new TranslatableMarkup('IP', [], ['context' => 'Validation']),
  type: 'ip_address'
)]
class IpConstraint extends Ip {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return IpValidator::class;
  }

}
