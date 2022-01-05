<?php

namespace Drupal\rules\Plugin\RulesExpression;

use Drupal\rules\Engine\ConditionExpressionContainer;
use Drupal\rules\Engine\ExecutionStateInterface;

/**
 * Evaluates a group of conditions with a logical OR.
 *
 * @RulesExpression(
 *   id = "rules_or",
 *   label = @Translation("Condition set (OR)")
 * )
 */
class OrExpression extends ConditionExpressionContainer {

  /**
   * {@inheritdoc}
   */
  public function evaluate(ExecutionStateInterface $state) {
    foreach ($this->conditions as $condition) {
      if ($condition->executeWithState($state)) {
        return TRUE;
      }
    }
    // An empty OR should return TRUE. Otherwise, if all conditions evaluate
    // to FALSE we return FALSE.
    return empty($this->conditions);
  }

  /**
   * {@inheritdoc}
   */
  protected function allowsMetadataAssertions() {
    // We cannot guarantee child expressions are executed, thus we cannot allow
    // metadata assertions.
    return FALSE;
  }

}
