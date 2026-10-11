'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const classes = new Set();
const navClasses = new Set();
const listeners = {};
const buttonListeners = {};
const attributes = { 'aria-expanded': 'false', 'aria-controls': 'learner-nav' };
const label = { textContent: 'Open primary navigation' };
let focused = false;

const button = {
    getAttribute(name) { return attributes[name] || null; },
    setAttribute(name, value) { attributes[name] = value; },
    querySelector(selector) { return selector === '.sr-only' ? label : null; },
    addEventListener(name, callback) { buttonListeners[name] = callback; },
    focus() { focused = true; },
};

const document = {
    documentElement: { classList: { add(name) { classes.add(name); } } },
    querySelectorAll(selector) { return selector === '.mobile-menu-button' ? [button] : []; },
    getElementById(id) {
        if (id !== 'learner-nav') return null;
        return {
            classList: {
                toggle(name, force) { force ? navClasses.add(name) : navClasses.delete(name); },
                remove(name) { navClasses.delete(name); },
            },
        };
    },
    addEventListener(name, callback) { listeners[name] = callback; },
};

const source = fs.readFileSync('public/assets/js/app-shell.js', 'utf8');
vm.runInNewContext(source, { document });
assert.equal(classes.has('has-js'), true, 'script enables JS-only collapsed navigation');

buttonListeners.click();
assert.equal(attributes['aria-expanded'], 'true');
assert.equal(navClasses.has('is-open'), true);
assert.equal(label.textContent, 'Close primary navigation');

listeners.keydown({ key: 'Escape' });
assert.equal(attributes['aria-expanded'], 'false');
assert.equal(navClasses.has('is-open'), false);
assert.equal(label.textContent, 'Open primary navigation');
assert.equal(focused, true, 'Escape returns keyboard focus to the menu toggle');

console.log('PASS mobile menu opens, updates its accessible name, and closes with Escape');
