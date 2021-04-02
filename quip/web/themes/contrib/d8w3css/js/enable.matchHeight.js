/**
 * @file
 * Defines Javascript behaviors for the drupal8 w3css theme.
 */

(function ($, Drupal) {
  'use strict';
  Drupal.behaviors.d8w3cssMatchHeight = {
    attach: function (context, settings) {

      let scrollWindowMH = function () {
        if ($("#layout-builder").length) {
          // Remove the match height on layout builder
          $('.top-region, .main-region, .bottom-region, .footer-region').matchHeight({
            remove: true
          });
        }
      };
      // Add and remove classes for match height.
      let mediaSizeMH = function () {
        let currentWidth = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
        if (currentWidth >= 993) {
          // Make sure all the inside regions have the same height.
          $('.top-region, .main-region, .bottom-region, .footer-region').matchHeight({
            property: 'height'
          });
        } else {
          // Remove the match height on small screen.
          $('.top-region, .main-region, .bottom-region, .footer-region').matchHeight({
            remove: true
          });
        }
      };

      mediaSizeMH();
      window.addEventListener('resize', mediaSizeMH);
      window.addEventListener('scroll', scrollWindowMH);
    }
  }

})(jQuery, Drupal);
