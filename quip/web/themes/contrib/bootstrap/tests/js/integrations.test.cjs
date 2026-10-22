'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const { environment } = require('./support/harness.cjs');

function setup(settings = {}) {
  const env = environment(settings);
  env.load('js/drupal.bootstrap.js');
  return env;
}

test('AJAX dialog responses inherit type and trigger without losing core callback', () => {
  const { $, Drupal, Element, load } = setup();
  load('js/misc/dialog.ajax.js');
  const ajax = new Drupal.Ajax();
  ajax.dialogType = 'modal';
  ajax.element = new Element('a');
  const response = [{ dialogOptions: { width: 500 } }, { command: 'insert' }];
  assert.equal(ajax.success(response), response);
  assert.equal(response[0].dialogType, 'modal');
  assert.equal(response[0].dialogOptions.dialogType, 'modal');
  assert.equal(response[0].$trigger[0], ajax.element);
  assert.equal(response[1].dialogType, undefined);
  assert.equal(response[0].dialogOptions.$trigger[0], ajax.element);
});

test('AJAX dialog serialization sends the dialog type and parent ID', () => {
  const { Drupal, Element, load } = setup();
  load('js/misc/dialog.ajax.js');
  const ajax = new Drupal.Ajax();
  ajax.dialogType = 'modal';
  ajax.element = new Element('button');
  const container = new Element('div');
  container.setAttribute('id', 'dialog-7');
  ajax.element.parentElement = container;
  const options = { data: {} };
  assert.equal(ajax.beforeSerialize(ajax.element, options), true);
  assert.equal(options.data['ajax_page_state[dialogType]'], 'modal');
  assert.equal(options.data['ajax_page_state[dialogId]'], 'dialog-7');
});

test('dialog button synchronization updates label and disabled state', () => {
  const { $, Drupal, Element, load, document } = setup();
  load('js/misc/dialog.ajax.js');
  const behavior = Drupal.behaviors.dialog;
  const actual = new Element('button');
  const proxy = new Element('button');
  $(actual).html('<b>Save</b>').prop('disabled', true);
  behavior.ajaxCurrentButton = $(proxy);
  behavior.ajaxOriginalButton = $(actual);
  $(document).trigger('ajaxSend');
  assert.equal($(proxy).html(), '<b>Save</b>');
  assert.equal($(proxy).prop('disabled'), true);
  $(document).trigger('ajaxComplete');
  assert.equal(behavior.ajaxCurrentButton, null);
  assert.equal(behavior.ajaxOriginalButton, null);
});

test('AJAX glyphicon progress starts, stops and notifies screen readers', () => {
  const { $, Drupal, Element, load } = setup({ tooltip_enabled: true });
  load('js/misc/ajax.js');
  const input = new Element('input');
  const form = new Element();
  const icon = new Element('span');
  const parent = new Element();
  icon.parentElement = parent;
  input.closestElement = form;
  form.matches = { '.ajax-progress.glyphicon': [icon] };
  const ajax = new Drupal.Ajax();
  ajax.element = input;
  ajax.progress = { message: 'Loading...' };
  ajax.setProgressIndicatorThrobber();
  assert.equal($(icon).hasClass('glyphicon-spin'), true);
  assert.equal(icon.tooltipCalls.at(-1), undefined);
  assert.equal(icon.getAttribute('title'), 'Loading...');
  assert.equal(ajax.progress.glyphicon, true);
  assert.equal(parent.children.length, 1); // Screen-reader message appended.
  ajax.glyphiconStop(input);
  assert.equal($(icon).hasClass('glyphicon-spin'), false);
});

test('Autocomplete response callback retains widget context and clears throbber', () => {
  const { $, Drupal, Element, load } = setup();
  let started = 0;
  let stopped = 0;
  Drupal.Ajax.prototype.glyphiconStart = () => started++;
  Drupal.Ajax.prototype.glyphiconStop = () => stopped++;
  load('js/misc/autocomplete.js');
  assert.equal($.registeredWidget.name, 'ui.autocomplete');
  const widget = $.registeredWidget.implementation;
  const input = new Element('input');
  const messages = [];
  const instance = {
    element: $(input), pending: 0, requestIndex: 0,
    _response: widget._response,
    source(opts, cb) { assert.equal(opts.term, 'abc'); cb(['match']); },
    __response(content) { messages.push(...content); },
  };
  widget._search.call(instance, 'abc');
  assert.equal(started, 1);
  assert.equal(stopped, 1);
  assert.equal(instance.pending, 0);
  assert.deepEqual(messages, ['match']);
});

test('message rendering sets Bootstrap classes, accessibility labels and close button', () => {
  const { Drupal, load } = setup();
  load('js/misc/message.js');
  assert.equal(Drupal.Message.getMessageTypeClass('error'), 'alert alert-danger');
  assert.equal(Drupal.Message.getMessageTypeRole('error'), 'alert');
  const wrapper = Drupal.theme('message', { text: 'Problem' }, { id: 'm1', type: 'error' });
  assert.equal(wrapper.getAttribute('data-drupal-message-id'), 'm1');
  assert.equal(wrapper.getAttribute('aria-label'), 'Error message');
  assert.equal(wrapper.children[1].getAttribute('data-dismiss'), 'alert');
  assert.equal(wrapper.children[2].innerHTML, 'Problem');
  const persistent = Drupal.theme('message', { text: 'Saved' }, { type: 'status', dismissible: false });
  assert.equal(persistent.children.length, 2);
  assert.equal(persistent.getAttribute('role'), 'status');
});

test('progress bar updates aria percentage, status, and callback', () => {
  const { Drupal, Element, load } = setup();
  load('js/misc/progress.js');
  const root = new Element();
  const bar = new Element();
  const label = new Element();
  const percentage = new Element();
  const msg = new Element();
  root.matches = { '.progress-bar': [bar], '.percentage': [percentage], '.message': [msg], '.progress-label': [label] };
  const callback = [];
  const progress = new Drupal.ProgressBar();
  progress.element = root;
  progress.updateCallback = (pct) => callback.push(pct);
  progress.setProgress(67, 'Working ', 'Almost done');
  assert.equal(bar.getAttribute('aria-valuenow'), '67');
  assert.equal(percentage.innerHTML, '67%');
  assert.equal(label.innerHTML, 'Almost done');
  assert.deepEqual(callback, [67]);
  assert.match(Drupal.theme.progressBar('progress-1'), /aria-valuemin="0"/);
});

test('jQuery UI dialog bridge preserves button callback this binding', () => {
  const { $, Drupal, Element, Event, window, load } = setup();
  load('js/attributes.js');
  const Bootstrap = Drupal.bootstrap;
  function Modal() {}
  Modal.DEFAULTS = {};
  Bootstrap.Modal = Modal;
  function plugin() {}
  plugin.Constructor = Modal;
  $.fn.modal = plugin;
  Bootstrap.Dialog = { Handler: {
    get: () => ({ selectors: { buttons: '.modal-buttons' } }),
    register() {},
  } };
  load('js/modal.jquery.ui.bridge.js');
  const footer = new Element();
  const dialog = new Element();
  let callbackContext;
  Drupal.theme.bootstrapModalDialogButton = () => '<button class="btn">OK</button>';
  const modal = {
    $footer: $(footer),
    $element: $(dialog),
    options: { dialogOptions: { buttons: [{ label: 'OK', click() { callbackContext = this; } }] } },
  };
  Modal.prototype.createButtons.call(modal);
  const proxyButton = footer.children[0].children[0];
  assert.equal(typeof proxyButton.jqueryListeners.click, 'function');
  proxyButton.jqueryListeners.click.call(proxyButton, new Event('click'));
  assert.equal(callbackContext, modal.$element);
});

test('AJAX dialog proxy buttons forward mousedown, mouseup and click', () => {
  const { $, Drupal, Element, Event, load } = setup();
  load('js/misc/dialog.ajax.js');
  const dialog = new Element('div');
  const actions = new Element('div');
  const submit = new Element('button');
  submit.setAttribute('class', 'btn use-ajax-submit');
  submit.innerHTML = '<b>Save</b>';
  dialog.matches = { '.form-actions': [actions] };
  actions.matches = { 'button, input[type=submit], a.button, .btn': [submit] };
  const fired = [];
  for (const type of ['mousedown', 'mouseup', 'click']) {
    submit.addEventListener(type, () => fired.push(type));
  }
  const buttons = Drupal.behaviors.dialog.prepareDialogButtons($(dialog));
  assert.equal(buttons.length, 1);
  assert.equal(buttons[0].text, 'Save');
  assert.equal(buttons[0].class.includes('use-ajax-submit'), false);
  const proxy = new Element('button');
  const event = new Event('click', { target: proxy });
  buttons[0].click(event);
  assert.deepEqual(fired, ['mousedown', 'mouseup', 'click']);
  assert.equal(event.defaultPrevented, true);
});
