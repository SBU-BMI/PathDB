/**
 * @file
 * MIT jquery-match-height master by @liabru http://brm.io/jquery-match-height/.
 */

(function (factory) {
  'use strict';
/* global define*/
/* eslint no-undef: ["error", { "typeof": true }] */
  if (typeof define === 'function' && define.amd) {
        // AMD.
    define(['jquery'], factory);
  }
  else if (typeof module !== 'undefined' && module.exports) {
        // CommonJS.
    module.exports = factory(require('jquery'));
  }
  else {
        // Global.
    factory(jQuery);
  }
})(function ($) {
  'use strict';

    /*
     *  internal
     */

  var _previousResizeWidth = -1;
  var _updateTimeout = -1;

    /*
     *  _parse
     *  value parse utility function
     */

  var _parse = function (value) {
        // Parse value and convert NaN to 0.
    return parseFloat(value) || 0;
  };

    /*
     *  _rows
     *  utility function returns array of jQuery selections representing each row
     *  (as displayed after float wrapping applied by browser)
     */

  var _rows = function (elements) {
    var tolerance = 1;
    var $elements = $(elements);
    var lastTop = null;
    var rows = [];

        // Group elements by their top position.
    $elements.each(
            function () {
              var $that = $(this);
              var top = $that.offset().top - _parse($that.css('margin-top'));
              var lastRow = rows.length > 0 ? rows[rows.length - 1] : null;

              if (lastRow === null) {
                    // First item on the row, so just push it.
                rows.push($that);
              }
              else {
                    // If the row top is the same, add to the row group.
                if (Math.floor(Math.abs(lastTop - top)) <= tolerance) {
                  rows[rows.length - 1] = lastRow.add($that);
                }
                else {
                        // Otherwise start a new row group.
                  rows.push($that);
                }
              }

                // Keep track of the last row top.
              lastTop = top;
            }
        );

    return rows;
  };

    /*
     *  _parseOptions
     *  handle plugin options
     */

  var _parseOptions = function (options) {
    var opts = {
      byRow: true,
      property: 'height',
      target: null,
      remove: false
    };

    if (typeof options === 'object') {
      return $.extend(opts, options);
    }

    if (typeof options === 'boolean') {
      opts.byRow = options;
    }
    else if (options === 'remove') {
      opts.remove = true;
    }

    return opts;
  };

    /*
     *  matchHeight
     *  plugin definition
     */

  var matchHeight = $.fn.matchHeight = function (options) {
    var opts = _parseOptions(options);

        // Handle remove.
    if (opts.remove) {
      var that = this;

            // Remove fixed height from all selected elements.
      this.css(opts.property, '');

            // Remove selected elements from all groups.
      $.each(
                matchHeight._groups, function (key, group) {
                  group.elements = group.elements.not(that);
                }
            );

      return this;
    }

    if (this.length <= 1 && !opts.target) {
      return this;
    }

        // Keep track of this group so we can re-apply later on load and resize events.
    matchHeight._groups.push(
      {
        elements: this,
        options: opts
      }
        );

        // Match each element's height to the tallest element in the selection.
    matchHeight._apply(this, opts);

    return this;
  };

    /*
     *  plugin global options
     */

  matchHeight.version = 'master';
  matchHeight._groups = [];
  matchHeight._throttle = 80;
  matchHeight._maintainScroll = false;
  matchHeight._beforeUpdate = null;
  matchHeight._afterUpdate = null;
  matchHeight._rows = _rows;
  matchHeight._parse = _parse;
  matchHeight._parseOptions = _parseOptions;

    /*
     *  matchHeight._apply
     *  apply matchHeight to given elements
     */

  matchHeight._apply = function (elements, options) {
    var opts = _parseOptions(options);
    var $elements = $(elements);
    var rows = [$elements];

        // Take note of scroll position.
    var scrollTop = $(window).scrollTop();
    var htmlHeight = $('html').outerHeight(true);

        // Get hidden parents.
    var $hiddenParents = $elements.parents().filter(':hidden');

        // Cache the original inline style.
    $hiddenParents.each(
            function () {
              var $that = $(this);
              $that.data('style-cache', $that.attr('style'));
            }
        );

        // Temporarily must force hidden parents visible.
    $hiddenParents.css('display', 'block');

        // Get rows if using byRow, otherwise assume one row.
    if (opts.byRow && !opts.target) {

            // Must first force an arbitrary equal height so floating elements break evenly.
      $elements.each(
                function () {
                  var $that = $(this);
                  var display = $that.css('display');

                    // Temporarily force a usable display value.
                  if (display !== 'inline-block' && display !== 'flex' && display !== 'inline-flex') {
                    display = 'block';
                  }

                    // Cache the original inline style.
                  $that.data('style-cache', $that.attr('style'));

                  $that.css(
                    {
                      'display': display,
                      'padding-top': '0',
                      'padding-bottom': '0',
                      'margin-top': '0',
                      'margin-bottom': '0',
                      'border-top-width': '0',
                      'border-bottom-width': '0',
                      'height': '100px',
                      'overflow': 'hidden'
                    }
                    );
                }
            );

                // Get the array of rows (based on element top position)
      rows = _rows($elements);

                // Revert original inline styles.
      $elements.each(
                function () {
                  var $that = $(this);
                  $that.attr('style', $that.data('style-cache') || '');
                }
            );
    }

    $.each(
            rows, function (key, row) {
              var $row = $(row);
              var targetHeight = 0;

              if (!opts.target) {
                    // Skip apply to rows with only one item.
                if (opts.byRow && $row.length <= 1) {
                  $row.css(opts.property, '');
                  return;
                }

                    // Iterate the row and find the max height.
                $row.each(
                        function () {
                          var $that = $(this);
                          var style = $that.attr('style');
                          var display = $that.css('display');

                            // Temporarily force a usable display value.
                          if (display !== 'inline-block' && display !== 'flex' && display !== 'inline-flex') {
                            display = 'block';
                          }

                            // Ensure we get the correct actual height (and not a previously set height value)
                          var css = {
                            display: display
                          };
                          css[opts.property] = '';
                          $that.css(css);

                            // Find the max height (including padding, but not margin)
                          if ($that.outerHeight(false) > targetHeight) {
                            targetHeight = $that.outerHeight(false);
                          }

                            // Revert styles.
                          if (style) {
                            $that.attr('style', style);
                          }
                          else {
                            $that.css('display', '');
                          }
                        }
                    );
              }
              else {
                    // If target set, use the height of the target element.
                targetHeight = opts.target.outerHeight(false);
              }

                // Iterate the row and apply the height to all elements.
              $row.each(
                    function () {
                      var $that = $(this);
                      var verticalPadding = 0;

                        // don't apply to a target.
                      if (opts.target && $that.is(opts.target)) {
                        return;
                      }

                        // Handle padding and border correctly (required when not using border-box)
                      if ($that.css('box-sizing') !== 'border-box') {
                        verticalPadding += _parse($that.css('border-top-width')) + _parse($that.css('border-bottom-width'));
                        verticalPadding += _parse($that.css('padding-top')) + _parse($that.css('padding-bottom'));
                      }

                        // Set the height (accounting for padding and border)
                      $that.css(opts.property, (targetHeight - verticalPadding) + 'px');
                    }
                );
            }
        );

        // Revert hidden parents.
    $hiddenParents.each(
            function () {
              var $that = $(this);
              $that.attr('style', $that.data('style-cache') || null);
            }
        );

        // Restore scroll position if enabled.
    if (matchHeight._maintainScroll) {
      $(window).scrollTop((scrollTop / htmlHeight) * $('html').outerHeight(true));
    }

    return this;
  };

    /*
     *  matchHeight._applyDataApi
     *  applies matchHeight to all elements with a data-match-height attribute
     */

  matchHeight._applyDataApi = function () {
    var groups = {};

        // Generate groups by their groupId set by elements using data-match-height.
    $('[data-match-height], [data-mh]').each(
            function () {
              var $this = $(this);
              var groupId = $this.attr('data-mh') || $this.attr('data-match-height');

              if (groupId in groups) {
                groups[groupId] = groups[groupId].add($this);
              }
              else {
                groups[groupId] = $this;
              }
            }
        );

        // Apply matchHeight to each group.
    $.each(
            groups, function () {
              this.matchHeight(true);
            }
        );
  };

    /*
     *  matchHeight._update
     *  updates matchHeight on all current groups with their correct options
     */

  var _update = function (event) {
    if (matchHeight._beforeUpdate) {
      matchHeight._beforeUpdate(event, matchHeight._groups);
    }

    $.each(
            matchHeight._groups, function () {
              matchHeight._apply(this.elements, this.options);
            }
        );

    if (matchHeight._afterUpdate) {
      matchHeight._afterUpdate(event, matchHeight._groups);
    }
  };

  matchHeight._update = function (throttle, event) {
        // Prevent update if fired from a resize event
        // where the viewport width hasn't actually changed
        // fixes an event looping bug in IE8.
    if (event && event.type === 'resize') {
      var windowWidth = $(window).width();
      if (windowWidth === _previousResizeWidth) {
        return;
      }
      _previousResizeWidth = windowWidth;
    }

        // Throttle updates.
    if (!throttle) {
      _update(event);
    }
    else if (_updateTimeout === -1) {
      _updateTimeout = setTimeout(
                function () {
                  _update(event);
                  _updateTimeout = -1;
                }, matchHeight._throttle
            );
    }
  };

    /*
     *  bind events
     */

    // Apply on DOM ready event.
  $(matchHeight._applyDataApi);

    // Use on or bind where supported.
  var on = $.fn.on ? 'on' : 'bind';

    // Update heights on load and resize events.
  $(window)[on]('load', function (event) {
    matchHeight._update(false, event);
  });

    // Throttled update heights on resize events.
  $(window)[on]('resize orientationchange', function (event) {
    matchHeight._update(true, event);
  });

});
