const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { JSDOM } = require('jsdom');

function bladeViews(directory) {
    return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const file = path.join(directory, entry.name);
        if (entry.isDirectory()) return bladeViews(file);
        return file.endsWith('.blade.php') ? [file] : [];
    });
}

test('Blade views do not use Alpine x-html directives', () => {
    const views = bladeViews(path.resolve('resources/views'));
    const violations = views.filter((file) => /\bx-html\s*=/.test(fs.readFileSync(file, 'utf8')));

    assert.deepEqual(violations, []);
});

test('Blade Alpine expressions avoid unsupported globals and optional chaining', () => {
    const views = bladeViews(path.resolve('resources/views'));
    const expressions = views.flatMap((file) => [...fs.readFileSync(file, 'utf8').matchAll(/\b(?:x-[\w:.-]+|:[\w.-]+)\s*=\s*"([^\"]*)"/gs)]
        .map(([, expression]) => ({ file, expression })));
    const forbidden = [
        /\?\./,
        /(?<![$\w.])(?:Number|String|Array|Object|JSON|Date)\s*\(/,
        /(?<![$\w.])Math\./,
        /(?<![$\w.])SMMediaPicker\./,
    ];
    const violations = expressions.filter(({ expression }) => forbidden.some((pattern) => pattern.test(expression)));

    assert.deepEqual(violations, []);
});

test('safe rich content rendering removes executable markup and keeps formatted text', () => {
    const script = fs.readFileSync('public/script.js', 'utf8');
    const start = script.indexOf('window.SM.setSafeHtmlContent = ');
    const end = script.indexOf('\nObject.assign(window.SM, {', start);
    assert.ok(start >= 0 && end > start);

    const dom = new JSDOM('<!doctype html><div id="target"></div>', { url: 'https://test.stemmechanics.com.au/' });
    dom.window.SM = {};
    const context = vm.createContext({
        window: dom.window,
        document: dom.window.document,
        HTMLElement: dom.window.HTMLElement,
        Node: dom.window.Node,
        DOMParser: dom.window.DOMParser,
        URL: dom.window.URL,
    });
    vm.runInContext(script.slice(start, end), context);

    const target = dom.window.document.querySelector('#target');
    dom.window.SM.setSafeHtmlContent(target, '<p onclick="run()" style="color:red;text-align:center">Safe <strong>text</strong><script>run()</script><a href="javascript:run()">bad link</a><a href="/notes" target="_blank">good link</a></p>');

    assert.equal(target.querySelector('strong')?.textContent, 'text');
    assert.equal(target.querySelector('p')?.style.textAlign, 'center');
    assert.equal(target.querySelectorAll('script').length, 0);
    assert.equal(target.querySelector('p')?.hasAttribute('onclick'), false);
    assert.equal(target.querySelector('a[href^="javascript:"]'), null);
    assert.equal(target.querySelector('a[href="/notes"]')?.getAttribute('rel'), 'noopener noreferrer');
});

test('safe review rendering preserves same-origin forms and strips Alpine attributes', () => {
    const script = fs.readFileSync('public/script.js', 'utf8');
    const start = script.indexOf('window.SM.setSafeHtmlContent = ');
    const end = script.indexOf('\nObject.assign(window.SM, {', start);
    const dom = new JSDOM('<!doctype html><div id="target"></div>', { url: 'https://test.stemmechanics.com.au/' });
    dom.window.SM = {};
    const context = vm.createContext({
        window: dom.window,
        document: dom.window.document,
        HTMLElement: dom.window.HTMLElement,
        Node: dom.window.Node,
        DOMParser: dom.window.DOMParser,
        URL: dom.window.URL,
    });
    vm.runInContext(script.slice(start, end), context);

    const target = dom.window.document.querySelector('#target');
    dom.window.SM.setSafeHtmlContent(target, '<form action="/admin/invoices" method="post" x-data="bad"><input type="hidden" name="_token" value="token"><button type="submit" x-on:click="bad()">Submit</button></form>', { allowForms: true });

    const form = target.querySelector('form');
    assert.equal(form?.getAttribute('action'), '/admin/invoices');
    assert.equal(form?.querySelector('input[name="_token"]')?.value, 'token');
    assert.equal(form?.querySelector('button')?.hasAttribute('x-on:click'), false);
});
