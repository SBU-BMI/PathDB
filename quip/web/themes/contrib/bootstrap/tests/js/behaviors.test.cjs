'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const { environment } = require('./support/harness.cjs');

function setup(settings = {}) {
  const env = environment(settings);
  env.load('js/drupal.bootstrap.js');
  return env;
}

test('dropdown proxies mouse and keyboard events, but not Tab', () => {
  const { Drupal, Element, Event, document, load } = setup();
  load('js/dropdown.js');
  const context = new Element('div');
  const button = new Element('a');
  const target = new Element('button');
  button.dataset.dropdownTarget = '#original';
  context.matches = { '.dropdown [data-dropdown-target]': [button] };
  document.matches = { '#original': [target] };
  const received = [];
  target.addEventListener('click', () => received.push('click'));
  target.addEventListener('keydown', () => received.push('keydown'));
  const behavior = Drupal.behaviors.bootstrapDropdown;
  behavior.attach(context);
  behavior.attach(context); // Attach must not accumulate duplicate handlers.
  button.dispatchEvent(new Event('click'));
  button.dispatchEvent(new Event('keydown', { keyCode: 13 }));
  const tab = new Event('keydown', { keyCode: 9 });
  button.dispatchEvent(tab);
  assert.deepEqual(received, ['click', 'keydown']);
  assert.equal(tab.defaultPrevented, false);
});

test('tooltips use theme defaults, honor per-element options, and detach', () => {
  const { Drupal, $, Element, load } = setup({ tooltip_enabled: true, tooltip_placement: 'top', tooltip_delay: '100' });
  function Tooltip() {}
  Tooltip.DEFAULTS = {};
  $.fn.tooltip.Constructor = Tooltip;
  load('js/tooltip.js');
  const el = new Element('span');
  el.store = { placement: 'bottom' };
  const context = new Element('div');
  context.matches = { '[data-toggle="tooltip"]': [el] };
  Drupal.behaviors.bootstrapTooltips.attach(context);
  assert.equal(el.tooltipCalls[0].placement, 'bottom');
  assert.equal(el.tooltipCalls[0].delay, 100);
  Drupal.behaviors.bootstrapTooltips.detach(context);
  assert.equal(el.tooltipCalls[1], 'destroy');
});

test('disabled tooltips are never initialized', () => {
  const { Drupal, $, Element, load } = setup({ tooltip_enabled: false });
  function Tooltip() {}
  Tooltip.DEFAULTS = {};
  $.fn.tooltip.Constructor = Tooltip;
  load('js/tooltip.js');
  const el = new Element('span');
  const context = new Element();
  context.matches = { '[data-toggle="tooltip"]': [el] };
  Drupal.behaviors.bootstrapTooltips.attach(context);
  assert.equal(el.tooltipCalls, undefined);
});

test('popover click trigger is manual and auto-close handles Escape', () => {
  const { Drupal, $, Element, Event, document, load } = setup({
    popover_enabled: true, popover_auto_close: true, popover_trigger: 'click', popover_delay: '0',
  });
  function Popover() {}
  Popover.DEFAULTS = {};
  $.fn.popover.Constructor = Popover;
  load('js/popover.js');
  const trigger = new Element('button');
  const context = new Element('div');
  context.matches = { '[data-toggle=popover]': [trigger], '[data-toggle="popover"]': [trigger] };
  const behavior = Drupal.behaviors.bootstrapPopovers;
  behavior.attach(context);
  assert.equal(trigger.popoverCalls[0].trigger, 'manual');
  assert.equal(trigger.popoverCalls[0].originalTrigger, 'click');
  trigger.jqueryListeners['click.drupal.bootstrap.popover'].call(trigger, new Event('click'));
  assert.equal(trigger.popoverCalls[1], 'toggle');
  trigger.store = { 'bs.popover': { options: trigger.popoverCalls[0] } };
  document.jqueryListeners['show.bs.popover'].call(trigger, new Event('show.bs.popover'));
  assert.equal(behavior.$activePopover[0], trigger);
  document.jqueryListeners['keyup.bs.popover'](new Event('keyup', { which: 27 }));
  assert.equal(trigger.popoverCalls[2], 'hide');
  assert.equal(behavior.$activePopover, null);
  behavior.detach(context);
  assert.equal(trigger.popoverCalls[3], 'destroy');
});

test('form error classes clear on a changed input and return when reset', () => {
  const { Drupal, $, Element, load } = setup({ forms_has_error_value_toggle: true });
  load('js/misc/form.js');
  const form = new Element('div');
  const item = new Element('div');
  const input = new Element('input');
  input.defaultValue = 'original';
  input.value = 'original';
  $(item).addClass('has-error');
  $(input).addClass('error');
  form.matches = { '.form-item.has-error:not(.form-type-password.has-feedback)': [item] };
  item.matches = { ':input': [input] };
  Drupal.behaviors.bootstrapForm.attach(form);
  input.value = 'changed';
  $(input).trigger('keyup');
  assert.equal($(item).hasClass('has-error'), false);
  assert.equal($(input).hasClass('error'), false);
  input.value = 'original';
  $(input).trigger('blur');
  assert.equal($(item).hasClass('has-error'), true);
  assert.equal($(input).hasClass('error'), true);
});

test('states handler disables descendants only on dependency-triggered changes', () => {
  const { $, Element, Event, document, load } = setup();
  load('js/misc/states.js');
  const input = new Element('input');
  const form = new Element('div');
  const child = new Element('input');
  input.closestElement = form;
  form.matches = { ':input': [child] };
  const handler = document.jqueryListeners['state:disabled'];
  handler(new Event('state:disabled', { target: input, trigger: true, value: true }));
  assert.equal(input.getAttribute('disabled'), 'true');
  assert.equal(child.getAttribute('disabled'), 'true');
  assert.equal($(form).hasClass('form-disabled'), true);
  handler(new Event('state:disabled', { target: input, trigger: false, value: false }));
  assert.equal($(form).hasClass('form-disabled'), true);
});

test('views exposed-form AJAX registers submit controls except reset', () => {
  const { $, Drupal, Element, load } = setup();
  load('js/modules/views/ajax_view.js');
  const form = new Element('form');
  const submit = new Element('button');
  const reset = new Element('input');
  submit.setAttribute('id', 'submit');
  reset.setAttribute('id', 'reset');
  reset.isSelector = { '[data-drupal-selector=edit-reset]': true };
  form.matches = { 'button[type=submit], input[type=submit], input[type=image]': [submit, reset] };
  const calls = [];
  Drupal.ajax = (opts) => { calls.push(opts); return { id: opts.base }; };
  const view = new Drupal.views.ajaxView();
  view.$exposed_form = form;
  view.element_settings = { progress: false };
  view.attachExposedFormAjax();
  assert.equal(calls.length, 1);
  assert.equal(calls[0].base, 'submit');
  assert.equal(calls[0].element, submit);
  assert.equal(view.exposedFormAjax[0].id, 'submit');
});

test('filter help link preserves base path when switching formats', () => {
  const { $, Drupal, Element, load } = setup();
  load('js/modules/filter/filter.js');
  const context = new Element();
  const select = new Element('select');
  const option = new Element('option');
  const wrapper = new Element();
  const link = new Element('a');
  link.setAttribute('href', '/filter/help');
  option.value = 'plain_text';
  select.parentElement = wrapper;
  select.matches = { ':selected': [option] };
  wrapper.matches = { '.filter-help > a': [link] };
  context.matches = { '.filter-wrapper select.filter-list': [select] };
  Drupal.behaviors.filterGuidelines.attach(context);
  assert.equal($(link).attr('href'), '/filter/help/plain_text');
  option.value = 'full_html';
  Drupal.behaviors.filterGuidelines.attach(context);
  assert.equal($(link).attr('href'), '/filter/help/full_html');
});


test('tooltips do not accept sanitizer overrides from untrusted data attributes', () => {
  const { Drupal, $, Element, load } = setup({ tooltip_enabled: true, tooltip_delay: '0' });
  function Tooltip() {}
  const trustedWhiteList = { b: [] };
  const trustedSanitizeFn = (html) => html;
  Tooltip.DEFAULTS = {
    sanitize: true,
    sanitizeFn: trustedSanitizeFn,
    whiteList: trustedWhiteList,
  };
  $.fn.tooltip.Constructor = Tooltip;
  load('js/tooltip.js');

  const el = new Element('a');
  el.store = {
    sanitize: false,
    sanitizeFn: 'attacker-controlled',
    whiteList: { img: ['onerror'] },
    html: true,
    title: 'legitimate content',
  };
  const context = new Element('div');
  context.matches = { '[data-toggle="tooltip"]': [el] };
  Drupal.behaviors.bootstrapTooltips.attach(context);

  const options = el.tooltipCalls[0];
  assert.equal(options.sanitize, true);
  assert.equal(options.sanitizeFn, trustedSanitizeFn);
  assert.deepEqual(options.whiteList, trustedWhiteList);
  assert.equal(options.html, true);
  assert.equal(options.title, 'legitimate content');
  // Filtering must not alter jQuery's cached per-element data object.
  assert.equal(el.store.sanitize, false);
  assert.deepEqual(el.store.whiteList, { img: ['onerror'] });
});

test('popovers do not accept sanitizer overrides from untrusted data attributes', () => {
  const { Drupal, $, Element, load } = setup({ popover_enabled: true, popover_trigger: 'click', popover_delay: '0' });
  function Popover() {}
  const trustedWhiteList = { em: [] };
  const trustedSanitizeFn = (html) => html;
  Popover.DEFAULTS = {
    sanitize: true,
    sanitizeFn: trustedSanitizeFn,
    whiteList: trustedWhiteList,
  };
  $.fn.popover.Constructor = Popover;
  load('js/popover.js');

  const el = new Element('a');
  el.store = {
    sanitize: false,
    sanitizeFn: 'attacker-controlled',
    whiteList: { img: ['onerror'] },
    html: true,
    content: 'legitimate content',
    trigger: 'click',
  };
  const context = new Element('div');
  context.matches = { '[data-toggle=popover]': [el] };
  Drupal.behaviors.bootstrapPopovers.attach(context);

  const options = el.popoverCalls[0];
  assert.equal(options.sanitize, true);
  assert.equal(options.sanitizeFn, trustedSanitizeFn);
  assert.deepEqual(options.whiteList, trustedWhiteList);
  assert.equal(options.html, true);
  assert.equal(options.content, 'legitimate content');
  assert.equal(options.originalTrigger, 'click');
  assert.equal(options.trigger, 'manual');
  assert.equal(el.store.sanitize, false);
});
