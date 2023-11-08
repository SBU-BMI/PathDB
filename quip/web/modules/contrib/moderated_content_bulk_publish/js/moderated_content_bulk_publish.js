(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.moderated_content_bulk_publish = {
    attach: function (context, settings) {
      if (context == document) {
        if ($('body').hasClass('user-logged-in') && typeof settings.moderated_content_bulk_publish !== 'undefined') {
          if (settings.moderated_content_bulk_publish.enable_dialog_admin_content) {
            // In the admin/content listing, add a confirmation dialog to all bulk operations.
            //using id contains selector id*= because id sometimes has a --2 atatched to end (edit-node-bulk-form--2)
            $('.view-content div[id*="edit-node-bulk-form"] .js-form-submit, .view-content form[id*="views-form-moderated-content-moderated-content"] input#edit-submit').bind('click.moderated_content_bulk_publish',function(e) {
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
              var prompt = Drupal.t('Are you sure you want to ') + action.toLowerCase() + "?<br/><br/>" + titles[0];
              if (cnt > 1) {
                prompt += "<br/><br/>+ " + (cnt-1) + ' more';
              }
              // build a Drupal modal dialog window
              var content  = '<div><p id="version-confirm-form-text">' + prompt + '</p></div>';
              var modalwindowtitle = action + "?";
              confirmationDialog = Drupal.dialog(content, {
                dialogClass: 'confirm-dialog',
                resizable: false,
                closeOnEscape: false,
                width:500,
                title: modalwindowtitle,
                buttons: [{
                  text: Drupal.t('Yes'),
                  class: 'button button--primary',
                  click: function click(e) {
                    confirmationDialog.close();
                    $('.view-content div[id*="edit-node-bulk-form"] .js-form-submit').unbind('click.moderated_content_bulk_publish');
                    $('.view-content form[id*="views-form-moderated-content-moderated-content"] input#edit-submit').unbind('click.moderated_content_bulk_publish');
                    $('.view-content div[id*="edit-node-bulk-form"] .js-form-submit').trigger('click.moderated_content_bulk_publish');
                    $('.view-content form[id*="views-form-moderated-content-moderated-content"] input#edit-submit').trigger('click.moderated_content_bulk_publish');
                    $(e.target).remove();
                    return true;
                  },
                  primary: true
                }, {
                  text: Drupal.t('Cancel'),
                  class: 'button',
                  click: function click() {
                    confirmationDialog.close();
                  }
                }],
                create: function () {
                },
                beforeClose: false,
                close: function (event) {
                  $(event.target).remove();
                }
              });

              e.preventDefault();
              confirmationDialog.showModal();
              return false;
            });

            // Fix a bug about button (id = edit-submit) was not handled in the previous version
            $('.view-content form[id*="views-form-content-page"] input#edit-submit').unbind('click.moderated_content_bulk_publish').bind('click.moderated_content_bulk_publish',function(e) {
              var titles = [];
              $('.views-table tbody .form-checkbox:checked').each(function() {
                titles.push($(this).closest('tr').find('.views-field-title a').text());
              });
              var cnt = titles.length;
              if (cnt == 0) {
                return false;
              }
              var action = $('#edit-action option:selected').text();
              var prompt = Drupal.t('Are you sure you want to ') + action.toLowerCase() + "?<br/><br/>" + titles[0];
              if (cnt > 1) {
                prompt += "<br/><br/>+ " + (cnt-1) + ' more';
              }
              // build a Drupal modal dialog window
              var content  = '<div><p id="version-confirm-form-text">' + prompt + '</p></div>';
              var modalwindowtitle = action + "?";
              var options = {
                dialogClass: 'confirm-dialog',
                resizable: false,
                closeOnEscape: false,
                width:500,
                title: modalwindowtitle,
                buttons: [{
                  text: Drupal.t('Yes'),
                  class: 'button button--primary',
                  click: function click(e) {
                    confirmationDialog.close();
                    $('.view-content form[id*="views-form-content-page"] input#edit-submit').unbind('click.moderated_content_bulk_publish');
                    $('.view-content form[id*="views-form-content-page"] input#edit-submit').trigger('click.moderated_content_bulk_publish');
                    $(e.target).remove();
                    return true;
                  },
                  primary: true
                }, {
                  text: Drupal.t('Cancel'),
                  class: 'button',
                  click: function click() {
                    confirmationDialog.close();
                  }
                }],
                create: function () {
                },
                beforeClose: false,
                close: function (event) {
                  $(event.target).parent().parent().find('.ui-widget-overlay').remove();
                  $(event.target).remove();
                }
              };
              confirmationDialog = Drupal.dialog(content, options);
              e.preventDefault();
              confirmationDialog.showModal();
              return false;
            });
          }
          if (settings.moderated_content_bulk_publish.enable_dialog_node_edit_form) {
            // When editing any type of node, display a confirmation dialog any time the state is changing from
            // non-published to published.
            if ($('body').hasClass('path-node')) {
              $('#edit-submit').unbind('click.moderated_content_bulk_publish').bind('click.moderated_content_bulk_publish', function(e) {
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

                // build a Drupal modal dialog window
                var msg =  Drupal.t('Are you sure you want to publish this item?');
                var content  = '<div><p id="version-confirm-form-text">' + msg + '</p></div>';
                confirmationDialog_publish = Drupal.dialog(content, {
                  dialogClass: 'confirm-dialog',
                  resizable: false,
                  closeOnEscape: false,
                  width:500,
                  title: Drupal.t('Publish this?'),
                  buttons: [{
                    text: Drupal.t('Yes'),
                    class: 'button button--primary',
                    click: function click(e) {
                      confirmationDialog_publish.close();
                      $(".node-form #edit-submit, .node-layout-builder-form  #edit-submit").unbind('click.moderated_content_bulk_publish');
                      $(".node-form #edit-submit, .node-layout-builder-form  #edit-submit").trigger('click.moderated_content_bulk_publish');
                      $(e.target).remove();
                      return true;
                    },
                    primary: true
                  }, {
                    text: Drupal.t('Cancel'),
                    class: 'button',
                    click: function click() {
                      confirmationDialog_publish.close();
                    }
                  }],
                  create: function () {
                  },
                  beforeClose: false,
                  close: function (event) {
                    $(event.target).parent().parent().find('.ui-widget-overlay').remove();
                    $(event.target).remove();
                  }
                });

                if ((cur_state == '' || cur_state == Drupal.t('Draft')) && (new_state == Drupal.t('Published'))) {
                  e.preventDefault();
                  confirmationDialog_publish.showModal();
                  return false;
                }
                else if ((cur_state == Drupal.t('Published')) && (new_state == Drupal.t('Published'))) {
                  e.preventDefault();
                  confirmationDialog_publish.showModal();
                  return false;
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
                  e.preventDefault();
                  confirmationDialog_publish.showModal();
                  return false;
                }
                return true;
              });
            }
          }
        }
      }
    }
  };
})(jQuery, Drupal, drupalSettings);
