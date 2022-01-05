(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.moderated_content_bulk_publish = {
    attach: function (context, settings) {
      if (context == document) {
        if ($('body').hasClass('user-logged-in')) {
          // In the admin/content listing, add a confirmation dialog to all bulk operations.
          //using id contains selector id*= because id sometimes has a --2 atatched to end (edit-node-bulk-form--2)
          $('.view-content div[id*="edit-node-bulk-form"] .js-form-submit').bind('click.moderated_content_bulk_publish',function(e) {
            // For automated testing purposes you can unbind this click event as follows:
            // jQuery('*').unbind('click.moderated_content_bulk_publish'); // Disables confirm dialog.
            var titles = [];
            $('.views-table tbody .form-checkbox:checked').each(function() {
              titles.push($(this).closest('tr').find('.views-field-title a').text());
            });
            var cnt = titles.length;
            if (cnt == 0) {
              return false;
            }
            var action = $('#edit-action option:selected').text();
            // TODO: wrap this prompt text in a t() function.
            var prompt = 'Are you sure you want to ' + action + "?\n\n" + titles[0];
            if (cnt > 1) {
              prompt += "\n\n+ " + (cnt-1) + ' more';
            }
            if (confirm(prompt)) {
              return true;
            }
            return false;
          });

          // When editing any type of node, display a confirmation dialog any time the state is changing from
          // non-published to published.
          if ($('body').hasClass('path-node')) {
            $('#edit-submit').bind('click.moderated_content_bulk_publish', function(e) {
              // For automated testing purposes you can unbind this click event as follows:
              // jQuery('*').unbind('click.moderated_content_bulk_publish'); // Disables confirm dialog.
              // Get the current state. Need to clone this object and remove the label so that we can get just the state.
              var cur_state = '';
              var mod_state = $('#edit-moderation-state-0-current').clone();
              if (mod_state) {
                $('label', mod_state).remove();
                var cur_state = $(mod_state).text().trim();
                cur_state = Drupal.t(cur_state);
              }
              var new_state = $('#edit-moderation-state-0-state option:selected').text();
              new_state = Drupal.t(new_state);
              // If changing from un-published to published...

              if ((cur_state == '' || cur_state == Drupal.t('Draft')) && (new_state == Drupal.t('Published'))) {
                var confirm_message = Drupal.t('Are you sure you want to publish this item?');
                if (! confirm(confirm_message)) {
                  e.preventDefault();
                  return false;
                }
              }
              else if ((cur_state == Drupal.t('Published')) && (new_state == Drupal.t('Published'))) {
                var confirm_message = Drupal.t('Are you sure you want to publish this?');
                if (! confirm(confirm_message)) {
                  e.preventDefault();
                  return false;
                }
              }
              return true;
            });
            $('#edit-create-and-translate').click(function(e) {
              // Get the current state. Need to clone this object and remove the label so that we can get just the state.
              var cur_state = '';
              var mod_state = $('#edit-moderation-state-0-current').clone();
              if (mod_state) {
                $('label', mod_state).remove();
                var cur_state = $(mod_state).text().trim();
                cur_state = Drupal.t(cur_state);
              }
              var new_state = $('#edit-moderation-state-0-state option:selected').text();
              new_state = Drupal.t(new_state);
              // If changing from un-published to published...
              if ((cur_state == '' || cur_state == Drupal.t('Draft')) && (new_state == Drupal.t('Published'))) {
                // TODO: wrap this prompt text in a t() function.
                if (! confirm('Are you sure you want to publish this item?')) {
                  e.preventDefault();
                  return false;
                }
              }
              return true;
            });
          }
        }
      }
    }
  };
})(jQuery, Drupal, drupalSettings);
