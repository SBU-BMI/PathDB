<?php

namespace Drupal\views_base_url\Plugin\views\field;

use Drupal\Component\Utility\Xss;

/**
 * Token replacement helper methods.
 */
trait TokenTrait {

  /**
   * Processes a template string with token replacements.
   *
   * This method replaces tokens in the format {{ tokenName }}
   *
   * @param string $text
   *   The template string containing tokens.
   * @param array $tokens
   *   An associative array of values where keys are token names.
   * @param bool $xss_filter
   *   Whether to apply XSS filtering to the result.
   *
   * @return string
   *   The processed string with tokens replaced.
   */
  public function simpleTokenReplace(string $text, array $tokens, bool $xss_filter = FALSE): string {
    $pattern = '/\{\{\s*([^}]+)\s*}}/';
    $result = preg_replace_callback($pattern, function ($matches) use ($tokens) {
      $tokenName = trim($matches[1]);
      return $tokens['{{ ' . $tokenName . ' }}'] ?? '';
    }, $text);
    if ($xss_filter) {
      $result = Xss::filterAdmin($result);
    }
    return $result;
  }

  /**
   * Returns a list of the available fields and arguments for token replacement.
   *
   * @return array
   *   Array of default help text and list of tokens.
   */
  protected function getReplacementTokens() {
    // Setup the tokens for fields.
    $previous = $this->getPreviousFieldLabels();
    $optgroup_arguments = (string) $this->t('Arguments');
    $optgroup_fields = (string) $this->t('Fields');
    foreach ($previous as $id => $label) {
      $options[$optgroup_fields]["{{ $id }}"] = substr(strrchr($label, ":"), 2);
    }
    // Add the field to the list of options.
    $options[$optgroup_fields]["{{ {$this->options['id']} }}"] = substr(strrchr($this->adminLabel(), ":"), 2);

    foreach ($this->view->display_handler->getHandlers('argument') as $arg => $handler) {
      $options[$optgroup_arguments]["{{ arguments.$arg }}"] = $this->t('@argument title', [
        '@argument' => $handler->adminLabel(),
      ]);
      $options[$optgroup_arguments]["{{ raw_arguments.$arg }}"] = $this->t('@argument input', [
        '@argument' => $handler->adminLabel(),
      ]);
    }

    $this->documentSelfTokens($options[$optgroup_fields]);

    // Default text.
    $output[] = [
      [
        '#markup' => '<p>' . $this->t('You must add some additional fields to this display before using this field. These fields may be marked as <em>Exclude from display</em> if you prefer. Note that due to rendering order, you cannot use fields that come after this field; if you need a field not listed here, rearrange your fields.') . '</p>',
      ],
      [
        '#markup' => '<p>' . $this->t("The following replacement tokens are available for this field. Note that due to rendering order, you cannot use fields that come after this field; if you need a field not listed here, rearrange your fields.") . '</p>',
      ],
    ];

    foreach (array_keys($options) as $type) {
      if (!empty($options[$type])) {
        $items = [];
        foreach ($options[$type] as $key => $value) {
          $items[] = $key . ' == ' . $value;
        }
        $item_list = [
          '#theme' => 'item_list',
          '#items' => $items,
        ];
        $output[] = $item_list;
      }
    }

    return $output;
  }

}
