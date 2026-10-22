'use strict';

// A deliberately small browser/Drupal adapter. The tests execute the shipped
// scripts directly, rather than reimplementing their logic in test helpers.
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../../..');

function environment(settings = {}) {
  class Element {
    constructor(tagName = 'div') {
      this.tagName = tagName.toUpperCase();
      this.attributes = [];
      this.attrs = {};
      this.dataset = {};
      this.children = [];
      this.listeners = {};
      this.classes = new Set();
      this.style = {};
      this.innerHTML = '';
      this.value = '';
      this.defaultValue = '';
      this.classList = {
        add: (name) => this.classes.add(name),
        contains: (name) => this.classes.has(name),
      };
    }
    setAttribute(key, value) {
      this.attrs[key] = String(value);
      this.attributes = Object.entries(this.attrs).map(([name, val]) => ({ name, value: val }));
    }
    getAttribute(key) { return this.attrs[key] ?? null; }
    appendChild(child) { this.children.push(child); return child; }
    addEventListener(type, fn) { (this.listeners[type] ??= new Set()).add(fn); }
    removeEventListener(type, fn) { this.listeners[type]?.delete(fn); }
    dispatchEvent(event) {
      event.target ??= this;
      event.currentTarget = this;
      for (const fn of this.listeners[event.type] ?? []) fn.call(this, event);
      return !event.defaultPrevented;
    }
    querySelectorAll(selector) { return this.matches?.[selector] || []; }
    get textContent() { return this.innerHTML.replace(/<[^>]*>/g, ''); }
  }
  class Event {
    constructor(type, options = {}) { Object.assign(this, options); this.type = type; this.defaultPrevented = false; }
    preventDefault() { this.defaultPrevented = true; }
    stopPropagation() { this.propagationStopped = true; }
  }
  class Collection {
    constructor(items = []) { this.items = items; this.length = items.length; items.forEach((v, i) => { this[i] = v; }); }
    each(fn) { this.items.forEach((v, i) => fn.call(v, i, v)); return this; }
    toArray() { return [...this.items]; }
    clone() { return $(this.items.map((v) => Object.assign(new Element(v.tagName), { attrs: { ...v.attrs }, store: { ...v.store } }))); }
    children() { return $(this.items.flatMap((v) => v.children)); }
    appendTo(target) { $(target).append(this); return this; }
    replaceWith() { return this; }
    eq(i) { return $(this.items[i]); }
    find(selector) { return $(this.items.flatMap((el) => el.querySelectorAll?.(selector) || [])); }
    on(events, selector, callback) {
      const fn = typeof selector === 'function' ? selector : callback;
      return this.each(function () {
        for (const event of events.split(' ')) (this.jqueryListeners ??= {})[event] = fn;
      });
    }
    off(events) { return this.each(function () { for (const e of events.split(' ')) delete this.jqueryListeners?.[e]; }); }
    bind(events, fn) { return this.on(events, fn); }
    unbind(events) { return this.off(events); }
    trigger(event) {
      const e = typeof event === 'string' ? new Event(event) : event;
      return this.each(function () { e.target ??= this; this.jqueryListeners?.[e.type]?.call(this, e); });
    }
    attr(name, value) {
      if (value === undefined) return this[0]?.getAttribute?.(name);
      return this.each(function () { this.setAttribute(name, value); });
    }
    removeAttr(name) { return this.each(function () { delete this.attrs[name]; }); }
    data(name, value) {
      if (value !== undefined) return this.each(function () { (this.store ??= {})[name] = value; });
      return name === undefined ? (this[0]?.store || {}) : this[0]?.store?.[name];
    }
    addClass(names) { return this.each(function () { names.split(' ').forEach((name) => this.classes.add(name)); }); }
    removeClass(names) { return this.each(function () { names.split(' ').forEach((name) => this.classes.delete(name)); }); }
    toggleClass(name, enabled) { return enabled ? this.addClass(name) : this.removeClass(name); }
    hasClass(name) { return this[0]?.classes.has(name) || false; }
    html(value) { if (value === undefined) return this[0]?.innerHTML; return this.each(function () { this.innerHTML = value; }); }
    text(value) { if (value === undefined) return this[0]?.textContent; return this.html(value); }
    val(value) { if (value === undefined) return this[0]?.value; return this.each(function () { this.value = value; }); }
    css(name, value) { return this.each(function () { this.style[typeof name === 'string' ? name : 'styles'] = value ?? name; }); }
    prop(name, value) { if (value === undefined) return this[0]?.[name]; return this.each(function () { this[name] = value; }); }
    parent() { return $(this[0]?.parentElement); }
    parents() { return $(this[0]?.parentElement); }
    closest() { return $(this[0]?.closestElement); }
    is(selector) { return Boolean(this[0]?.isSelector?.[selector]); }
    not(selector) { return $(this.items.filter((el) => !el.isSelector?.[selector])); }
    append(child) { return this.each(function () { this.appendChild(child instanceof Collection ? child[0] : child); }); }
    prepend(child) { return this.append(child); }
    before() { return this; }
    after() { return this; }
    hide() { return this.css('display', 'none'); }
    show() { return this.css('display', 'block'); }
    remove() { return this; }
    tooltip(option) { return this.each(function () { (this.tooltipCalls ??= []).push(option); }); }
    popover(option) { return this.each(function () { (this.popoverCalls ??= []).push(option); }); }
    ajaxSend(fn) { return this.on('ajaxSend', fn); }
    ajaxComplete(fn) { return this.on('ajaxComplete', fn); }
  }
  function $(value, context) {
    if (value instanceof Collection) return value;
    if (typeof value === 'function') { value(); return new Collection(); }
    if (typeof value === 'string') {
      if (value.startsWith('<')) return new Collection([new Element(value.match(/^<([\w-]+)/)?.[1] || 'div')]);
      return new Collection(context instanceof Element ? context.querySelectorAll(value) : []);
    }
    return new Collection(value == null ? [] : Array.isArray(value) ? value : [value]);
  }
  $.prototype = Collection.prototype;
  $.fn = Collection.prototype;
  // Legacy jQuery API used by Bootstrap before its ESLint modernization.
  $.proxy = (fn, context, ...args) => fn.bind(context, ...args);
  $.ui = { autocomplete: function autocomplete() {} };
  $.widget = function (name, base, implementation) { $.registeredWidget = { name, base, implementation }; };
  $.isPlainObject = (obj) => obj !== null && typeof obj === 'object' && !Array.isArray(obj) && !(obj instanceof Element) && !(obj instanceof Collection);
  $.extend = function (...args) {
    const deep = args[0] === true;
    if (deep) args.shift();
    const target = args.shift();
    for (const obj of args) for (const [key, val] of Object.entries(obj || {})) {
      target[key] = deep && $.isPlainObject(val) ? $.extend(true, $.isPlainObject(target[key]) ? target[key] : {}, val) :
        deep && Array.isArray(val) ? [...val] : val;
    }
    return target;
  };

  const _ = {
    isFunction: (v) => typeof v === 'function',
    isArray: Array.isArray,
    isObject: (v) => v !== null && typeof v === 'object',
    values: Object.values,
    extend: Object.assign,
    indexOf: (arr, item) => arr.indexOf(item),
    without: (arr, ...items) => arr.filter((v) => !items.includes(v)),
    pick: (obj, keys) => Object.fromEntries(Object.entries(obj).filter(([key]) => keys.includes(key))),
    map: (arr, fn) => arr.map(fn),
    difference: (arr, ...others) => arr.filter((v) => !others.flat().includes(v)),
    intersection: (arr, ...others) => arr.filter((v) => others.every((a) => a.includes(v))),
    chain(items) {
      let value = items;
      const chain = {
        flatten() { value = value.flat(Infinity); return chain; },
        map(fn) { value = value.map(fn); return chain; },
        filter() { value = value.filter(Boolean); return chain; },
        uniq() { value = [...new Set(value)]; return chain; },
        value() { return value; },
      };
      return chain;
    },
  };
  const document = new Element('document');
  document.documentElement = new Element('html');
  document.documentElement.setAttribute('dir', 'ltr');
  document.createElement = (tag) => new Element(tag);
  const theme = function (name, ...args) { return theme[name](...args); };
  const Drupal = {
    behaviors: { dialog: {} },
    theme,
    checkPlain: (value) => String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;'),
    t: (str) => str,
    formatString: (str, map = {}) => Object.entries(map).reduce((s, [k, v]) => s.replaceAll(k, String(v)), str),
    throwError: (error) => { throw error; },
    Ajax: function Ajax() { this.progress = {}; },
    ProgressBar: function ProgressBar() {},
    Message: function Message() {},
    views: { ajaxView: function ajaxView() {} },
  };
  Drupal.Ajax.prototype.success = function (response) { return response; };
  Drupal.Ajax.prototype.beforeSerialize = function () { return true; };
  const drupalSettings = { bootstrap: settings, dialog: {} };
  const window = { jQuery: $, _: _, Drupal, drupalSettings, document, Event,
    MouseEvent: Event, KeyboardEvent: Event, TouchEvent: Event, console };
  const globals = { window, document, jQuery: $, $: $, _: _, Drupal, drupalSettings,
    Node: Element, Element, HTMLElement: Element, Event, console, setTimeout,
    once: (id, selector, context) => context.querySelectorAll(selector), };
  Object.defineProperty(globals, 'Attributes', { get: () => window.Attributes });
  vm.createContext(globals);
  return {
    $, _, Drupal, drupalSettings, window, document, Element, Event,
    load(filename) { vm.runInContext(fs.readFileSync(path.join(root, filename), 'utf8'), globals, { filename }); },
  };
}

module.exports = { environment };
