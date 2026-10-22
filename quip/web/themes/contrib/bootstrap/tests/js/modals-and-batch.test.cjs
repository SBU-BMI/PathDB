'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const { environment } = require('./support/harness.cjs');

function setup(settings = {}) {
  const env = environment(settings);
  env.load('js/attributes.js');
  env.load('js/drupal.bootstrap.js');
  return env;
}

test('modal plugin inherits Bootstrap options and opens on show request', () => {
  const { $, Drupal, Element, load } = setup({ modal_show: false, modal_keyboard: true });
  function NativeModal() {}
  NativeModal.DEFAULTS = { backdrop: true };
  NativeModal.prototype.show = function () { this.isShown = true; };
  NativeModal.prototype.hide = function () { this.isShown = false; };
  function nativePlugin() {}
  nativePlugin.Constructor = NativeModal;
  $.fn.modal = nativePlugin;
  load('js/modal.js');
  const element = new Element('div');
  const $modal = $(element);
  $modal.modal({ show: false });
  const instance = $modal.data('bs.modal');
  assert.equal(instance.options.keyboard, true);
  assert.equal(instance.options.show, false);
  assert.equal(instance.isShown, null);
  $modal.modal('show');
  assert.equal(instance.isShown, true);
  $modal.modal('hide');
  assert.equal(instance.isShown, false);
  assert.equal($.fn.modal.Constructor, Drupal.bootstrap.Modal);
});

test('dialog handler caches registered handlers and locates type from DOM', () => {
  const { $, Drupal, Element, load } = setup();
  function nativePlugin() {}
  $.fn.modal = nativePlugin;
  load('js/dialog.js');
  const Handler = Drupal.bootstrap.Dialog.Handler;
  Handler.register('custom', { prefix: 'custom', selectors: { dialog: '.custom-dialog' } });
  const a = Handler.get('custom');
  const element = new Element();
  element.dialogType = 'custom';
  assert.equal(Handler.get(element), a);
  assert.equal(Handler.get($(element)), a);
  assert.equal(a.prefix, 'custom');
  assert.equal(Handler.get('modal'), Handler.get('modal'));
});

test('batch completion stops polling and redirects to the finished operation', () => {
  const { Drupal, Element, load, window } = setup();
  load('js/misc/batch.js');
  const progress = new Element();
  const bar = new Element();
  bar.setAttribute('id', 'progress-id');
  progress.matches = { '.progress': [bar] };
  const context = new Element();
  context.matches = { '[data-drupal-progress]': [progress] };
  let instance;
  Drupal.ProgressBar = function (id, callback) {
    this.id = id;
    this.element = new Element();
    this.stopMonitoring = () => { this.stopped = true; };
    this.setProgress = (value, msg) => { this.initial = [value, msg]; };
    this.startMonitoring = (uri, interval) => { this.monitor = [uri, interval]; };
    this.finish = callback;
    instance = this;
  };
  Drupal.behaviors.batch.attach(context, { batch: { uri: '/batch?id=7', initMessage: 'Starting', errorMessage: 'Failed' } });
  assert.equal(instance.id, 'progress-id');
  assert.deepEqual(instance.monitor, ['/batch?id=7&op=do', 10]);
  instance.finish('100', 'Finished', instance);
  assert.equal(instance.stopped, true);
  assert.equal(window.location, '/batch?id=7&op=finished');
});
