'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const { environment } = require('./support/harness.cjs');

function setup() {
  const env = environment({ dev: false });
  env.load('js/attributes.js');
  env.load('js/drupal.bootstrap.js');
  return env;
}

test('Attributes sanitizes, deduplicates and escapes HTML attributes', () => {
  const { window } = setup();
  const attrs = window.Attributes.create({ class: ['Alert alert', 'alert'], title: 'A&B <"x">' });
  assert.deepEqual(Array.from(attrs.getClasses()), ['alert']);
  assert.equal(attrs.toString(), ' class="alert" title="A&amp;B &lt;&quot;x&quot;&gt;"');
  attrs.addClass('foo bar', ['bar', 'baz']);
  assert.deepEqual(Array.from(attrs.getClasses()), ['alert', 'foo', 'bar', 'baz']);
  assert.equal(attrs.hasClass('foo', 'bar'), true);
  assert.equal(attrs.hasClass('missing'), false);
});

test('Attributes clones data, merges attributes, supports setters and removal', () => {
  const { window } = setup();
  const original = { id: 'first', class: 'initial', data: { x: 1 } };
  const attrs = new window.Attributes(original);
  attrs.merge({ data: { y: 2 }, title: 'hello' }).set('id', 'second').remove('title');
  assert.equal(attrs.get('id'), 'second');
  assert.equal(attrs.exists('title'), false);
  assert.equal(attrs.toPlainObject().id, 'second');
  assert.equal(attrs.get('data').x, 1);
  assert.equal(attrs.get('data').y, 2);
  assert.equal(original.id, 'first');
  const snapshot = attrs.getData();
  snapshot.id = 'mutated';
  assert.equal(attrs.get('id'), 'second');
});

test('Attributes extracts native DOM attributes', () => {
  const { window, Element } = setup();
  const node = new Element('input');
  node.setAttribute('id', 'test');
  node.setAttribute('class', 'one two');
  const attrs = window.Attributes.create(node);
  assert.equal(attrs.get('id'), 'test');
  assert.deepEqual(Array.from(attrs.getClasses()), ['one', 'two']);
});

test('Bootstrap once invokes a callback exactly once with settings', () => {
  const { Drupal } = setup();
  let calls = 0;
  Drupal.bootstrap.once('key', (settings) => { assert.equal(settings.dev, false); calls++; });
  Drupal.bootstrap.once('key', () => calls++);
  assert.equal(calls, 1);
});

test('Bootstrap creates plugins and restores previous plugin with noConflict', () => {
  const { Drupal, $ } = setup();
  const plugin = function plugin() {};
  Drupal.bootstrap.createPlugin('example', plugin);
  assert.equal($.fn.example, plugin);
  assert.equal(plugin.noConflict(), plugin);
  assert.equal($.fn.example, undefined);
  assert.equal(Drupal.bootstrap.createPlugin('example', 3), false);
});

test('Bootstrap plugin extension preserves access to its parent method', () => {
  const { Drupal, $ } = setup();
  function Plugin() {}
  Plugin.DEFAULTS = { enabled: false };
  Plugin.prototype.describe = function () { return 'old'; };
  function plugin() {}
  plugin.Constructor = Plugin;
  $.fn.example = plugin;
  Drupal.bootstrap.extendPlugin('example', () => ({
    DEFAULTS: { enabled: true },
    prototype: { describe() { return `${this.super()}-new`; } },
  }));
  assert.equal(new Plugin().describe(), 'old-new');
  assert.equal(Plugin.DEFAULTS.enabled, true);
  assert.equal(new Plugin().super, undefined);
});

test('Bootstrap normalizeObject converts boolean strings recursively', () => {
  const { Drupal } = setup();
  const result = Drupal.bootstrap.normalizeObject({ yes: 'true', no: 'false', nested: { value: 'true' }, name: 'hello' });
  assert.equal(result.yes, true);
  assert.equal(result.no, false);
  assert.equal(result.nested.value, true);
  assert.equal(result.name, 'hello');
});

test('Bootstrap simulates click, keydown and cancellation on native targets', () => {
  const { Drupal, Element } = setup();
  const element = new Element('button');
  const received = [];
  element.addEventListener('click', (e) => { received.push(e.type); e.preventDefault(); });
  element.addEventListener('keydown', (e) => received.push(e.type));
  assert.equal(Drupal.bootstrap.simulate(element, ['click', 'keydown']), false);
  assert.deepEqual(received, ['click', 'keydown']);
  assert.throws(() => Drupal.bootstrap.simulate(element, 'custom-event'), /Only rudimentary/);
});

test('Bootstrap relays event names to jQuery targets', () => {
  const { Drupal, $, Element, Event } = setup();
  const element = new Element('button');
  let observed;
  $(element).on('updated', (e) => { observed = { type: e.type, namespace: e.namespace }; });
  const handler = Drupal.bootstrap.relayEvent(element, 'updated.drupal');
  const event = new Event('change');
  handler(event);
  assert.equal(event.propagationStopped, true);
  assert.deepEqual(observed, { type: 'updated', namespace: 'drupal' });
});
