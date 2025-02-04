(function ($, window, Drupal) {

  /**
   * Provide the summary information for the HTTP Response Headers conditions vertical tabs.
   */
  Drupal.behaviors.httpResponseHeadersSummary = {
    attach() {
      if (typeof $.fn.drupalSetSummary === 'undefined') {
        return;
      }

      /**
       * Create a summary for checkboxes in the provided context.
       */
      function checkboxesSummary(context) {
        const values = [];
        const $checkboxes = $(context).find(
          'input[type="checkbox"]:checked + label',
        );
        const il = $checkboxes.length;
        for (let i = 0; i < il; i++) {
          values.push($($checkboxes[i]).html());
        }
        if (!values.length) {
          values.push(Drupal.t('Not restricted'));
        }
        return values.join(', ');
      }

      $(
        '[data-drupal-selector="edit-visibility-node-type"], [data-drupal-selector="edit-visibility-entity-bundlenode"], [data-drupal-selector="edit-visibility-language"], [data-drupal-selector="edit-visibility-user-role"], [data-drupal-selector="edit-visibility-response-status"]',
      ).drupalSetSummary(checkboxesSummary);

      $('[data-drupal-selector="edit-visibility-request-path"]').drupalSetSummary((context) => {
        const $pages = $(context).find(
          'textarea[name="visibility[request_path][pages]"]',
        );
        if (!$pages.length || !$pages[0].value) {
          return Drupal.t('Not restricted');
        }
        return Drupal.t('Restricted to certain pages');
      });
    },
  };

})(jQuery, window, Drupal);
