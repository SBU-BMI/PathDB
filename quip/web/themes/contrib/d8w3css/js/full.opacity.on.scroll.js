/**
 * @file
 * Defines Javascript behaviors for the W3CSS Theme.
 */

(function ($, Drupal) {
  'use strict';

  let scrollOn = false;

  let fadeBox = function () {
    scrollOn = true;
    let animationHeight = $(window).innerHeight() * 0.25;
    let ratio = Math.round((1 / animationHeight) * 10000) / 10000;
    $('.d8-fade').each(function () {
      let objectTop = $(this).offset().top;
      let windowBottom = $(window).scrollTop() + $(window).innerHeight();
      if (objectTop < windowBottom) {
        if (objectTop < windowBottom - animationHeight) {
          $(this).css({
            transition: 'opacity 1s linear',
            opacity: 1
          });
        } else {
          $(this).css({
            transition: 'opacity 0.5s linear',
            opacity: (windowBottom - objectTop) * ratio
          });
        }
      } else {
        $(this).css('opacity', 0);
      }
    });
  };

  setInterval(function () {
    if (scrollOn) {
      scrollOn = false;
    }
  }, 100);

  Drupal.behaviors.d8w3cssFullOpacity = {
    attach: function (context, settings) {

      // Disable show on scroll if layout builder is active.
      if (!document.getElementById("layout-builder")) {
        $(context)
          .find('.d8-fade')
          .once('.d8-fade')
          .css('opacity', 0);
        fadeBox();
        window.addEventListener('scroll', fadeBox);
      }

    }
  };
})(jQuery, Drupal);
