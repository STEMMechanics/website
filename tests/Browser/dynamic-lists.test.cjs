const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup() {
    const listeners = {}, history = [], redirects = [], alerts = [];
    const content = { nodes: ['original'], replaceChildren(...nodes) { this.nodes = nodes; }, focus() {}, scrollIntoView() {} };
    const status = { textContent: '' };
    const root = { dataset: { dynamicList: 'products' }, contains: () => false, setAttribute(key, value) { this[key] = value; }, querySelector: key => key === '[data-list-status]' ? status : content, querySelectorAll: () => [] };
    let nextPage = { title: 'Products', querySelectorAll: () => [{ dataset: root.dataset, querySelector: () => ({ childNodes: ['updated'], querySelector: () => null }) }] };
    const context = {
        URL, URLSearchParams, AbortController, setTimeout, clearTimeout,
        Element: class {}, HTMLInputElement: class {}, HTMLFormElement: class {},
        FormData: class { constructor(form) { return form.entries; } },
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
        requestAnimationFrame: callback => callback(),
        DOMParser: class { parseFromString() { return nextPage; } },
        document: { readyState: 'complete', activeElement: null, title: 'Before', querySelector: () => root, querySelectorAll: () => [root], addEventListener: (name, callback) => { listeners[name] = callback; }, dispatchEvent() {} },
        window: { location: { href: 'https://example.test/products', assign: url => redirects.push(url) }, history: { state: {}, replaceState(state, _, url) { history.push(['replace', url]); this.state = state; }, pushState(state, _, url) { history.push(['push', url]); this.state = state; } }, addEventListener: (name, callback) => { listeners[name] = callback; }, SM: { alert: (...args) => alerts.push(args) } },
        fetch: async url => ({ ok: true, url, headers: { get: () => 'text/html' }, text: async () => '<html></html>' }),
    };
    const code = fs.readFileSync('resources/js/dynamic-lists.js', 'utf8').replaceAll('export ', '');
    vm.runInNewContext(`${code}\nwindow.api = { loadList, canLoadList, listUrl, pendingFilters, previewListControls };`, context);
    history.length = 0;
    return { ...context.window.api, context, root, content, status, history, redirects, alerts, listeners, page: value => { nextPage = value; } };
}

test('list navigation only accepts the same origin and path', () => {
    const app = setup();
    assert.equal(app.canLoadList(new URL('https://example.test/products?page=2')), true);
    for (const target of ['https://elsewhere.test/products', 'https://example.test/products/create', 'https://example.test/products#edit']) assert.equal(app.canLoadList(new URL(target)), false);
});

test('search preserves filters and repeated values while resetting pagination', () => {
    const app = setup();
    const url = app.listUrl({ action: '/products', entries: [['search', 'kit'], ['filter', 'archived'], ['category[]', '1'], ['category[]', '2'], ['page', '4']] });
    assert.equal(url.searchParams.get('filter'), 'archived');
    assert.deepEqual(url.searchParams.getAll('category[]'), ['1', '2']);
    assert.equal(url.searchParams.has('page'), false);
});

test('successful loading replaces the list and adds a history entry', async () => {
    const app = setup();
    await app.loadList(app.root, new URL('https://example.test/products?page=2'));
    assert.deepEqual(app.content.nodes, ['updated']);
    assert.deepEqual(app.history, [['push', 'https://example.test/products?page=2']]);
    assert.equal(app.root['aria-busy'], 'false');
    assert.equal(app.status.textContent, 'Results updated.');
    assert.equal(app.context.document.title, 'Products');
});

test('a superseded request cannot replace newer results', async () => {
    const app = setup();
    let finishFirst;
    app.context.fetch = url => new Promise(resolve => { finishFirst = () => resolve({ ok: true, url, headers: { get: () => 'text/html' }, text: async () => '' }); });
    const first = app.loadList(app.root, new URL('https://example.test/products?search=old'));
    app.context.fetch = async url => ({ ok: true, url, headers: { get: () => 'text/html' }, text: async () => '' });
    await app.loadList(app.root, new URL('https://example.test/products?search=new'));
    finishFirst();
    await first;
    assert.deepEqual(app.history, [['push', 'https://example.test/products?search=new']]);
});

test('network errors preserve results and the URL, and allow retry', async () => {
    const app = setup();
    app.context.fetch = async () => { throw new Error('Offline'); };
    await app.loadList(app.root, new URL('https://example.test/products?page=2'));
    assert.deepEqual(app.content.nodes, ['original']);
    assert.equal(app.history.length, 0);
    assert.equal(app.alerts.length, 1);
    assert.equal(app.root['aria-busy'], 'false');
});

test('incompatible fragments and expired sessions fall back to normal navigation', async () => {
    for (const expired of [false, true]) {
        const app = setup();
        if (expired) app.context.fetch = async () => ({ ok: true, url: 'https://example.test/login', headers: { get: () => 'text/html' } });
        else app.page({ querySelectorAll: () => [] });
        await app.loadList(app.root, new URL('https://example.test/products?page=2'));
        assert.deepEqual(app.redirects, ['https://example.test/products?page=2']);
        assert.deepEqual(app.content.nodes, ['original']);
    }
});

test('successive live searches share an entry and Back does not add another', async () => {
    const app = setup();
    await app.loadList(app.root, new URL('https://example.test/products?search=k'), { search: true });
    await app.loadList(app.root, new URL('https://example.test/products?search=kit'), { search: true });
    await app.loadList(app.root, new URL('https://example.test/products'), { historyMode: 'none' });
    assert.deepEqual(app.history.map(entry => entry[0]), ['push', 'replace']);
});

test('live search restores focus and the caret after replacing controls', async () => {
    const app = setup();
    const input = new app.context.HTMLInputElement();
    Object.assign(input, { name: 'search', selectionStart: 2, selectionEnd: 3 });
    app.context.document.activeElement = input;
    app.root.contains = node => node === input;
    const calls = [];
    app.root.querySelectorAll = selector => selector.startsWith('input') ? [{ name: 'search', focus: () => calls.push('focus'), setSelectionRange: (start, end) => calls.push([start, end]) }] : [];
    await app.loadList(app.root, new URL('https://example.test/products?search=kit'), { search: true });
    assert.deepEqual(calls, ['focus', [2, 3]]);
});


test('loading indicator is positioned over the visible results instead of above the toolbar', async () => {
    const app = setup();
    const positions = {};
    app.context.window.innerHeight = 800;
    app.root.getBoundingClientRect = () => ({ top: 120 });
    app.root.style = { setProperty: (name, value) => { positions[name] = value; } };
    app.root.querySelectorAll = selector => selector.includes('[data-list-results]') ? [{ getBoundingClientRect: () => ({ top: 350, bottom: 1100, height: 750 }) }] : [];
    await app.loadList(app.root, new URL('https://example.test/products?page=2'));
    assert.equal(positions['--list-loader-top'], '455px');
});

test('refresh preserves explicitly persistent draft controls', async () => {
    const app = setup();
    const draft = { dataset: { listPreserve: 'newsletter' }, value: 'Unsaved draft' };
    let preserved;
    app.content.querySelectorAll = () => [draft];
    app.page({ title: 'Subscribers', querySelectorAll: () => [{ dataset: app.root.dataset, querySelector: () => ({
        childNodes: ['new results'], querySelector: () => null,
        querySelectorAll: () => [{ dataset: draft.dataset, replaceWith: node => { preserved = node; } }],
    }) }] });
    await app.loadList(app.root, new URL('https://example.test/products?search=alex'));
    assert.equal(preserved, draft);
    assert.equal(preserved.value, 'Unsaved draft');
    assert.deepEqual(app.content.nodes, ['new results']);
});

test('report search resets named paginator parameters too', () => {
    const app = setup();
    const url = app.listUrl({ action: '/products', entries: [['search', 'kit'], ['orders_page', '5'], ['payments_page', '3'], ['days', '30']] });
    assert.equal(url.searchParams.has('orders_page'), false);
    assert.equal(url.searchParams.has('payments_page'), false);
    assert.equal(url.searchParams.get('days'), '30');
});

function addPreviewControls(app) {
    const element = () => ({
        dataset: {}, childNodes: [], hidden: false, textContent: '', attrs: {},
        append(node) { this.childNodes.push(node); },
        replaceChildren(...nodes) { this.childNodes = nodes; },
        setAttribute(key, value) { this.attrs[key] = value; },
        getAttribute(key) { return this.attrs[key] ?? null; },
        removeAttribute(key) { delete this.attrs[key]; },
    });
    const chips = element(), badge = element(), all = element(), current = element();
    chips.hidden = true;
    badge.hidden = true;
    badge.textContent = '0';
    all.href = 'https://example.test/products?show_cancelled=1';
    all.setAttribute('aria-current', 'page');
    current.href = 'https://example.test/products?show_cancelled=0&list_starts_at_min=2026-09-06';
    const fields = { show_cancelled: { label: 'Include cancelled', type: 'boolean', default: '1', clear: '1' }, list_starts_at_min: { label: 'Date — From', type: 'date' } };
    const controls = { dataset: { filterSchema: JSON.stringify(fields), filterSearch: 'search' }, querySelector: selector => selector === '[data-filter-chips]' ? chips : badge };
    app.root.querySelectorAll = selector => selector === '[data-filter-controls]' ? [controls] : selector === '.sm-preset-views' ? [{ querySelectorAll: () => [all, current] }] : [];
    app.context.document.createElement = element;
    app.context.document.createTextNode = text => ({ textContent: text });
    return { chips, badge, all, current };
}

test('filter previews use labels, defaults and repeated values without treating zero as empty', () => {
    const app = setup();
    const filters = app.pendingFilters(new URL('https://example.test/products?show_cancelled=0&tags[]=a&tags[]=b&state=scheduled'), {
        show_cancelled: { label: 'Include cancelled', type: 'boolean', default: '1' },
        tags: { label: 'Tags', type: 'array' },
        state: { label: 'Status', options: { scheduled: 'Opens Soon' } },
    });
    assert.deepEqual(Array.from(filters, filter => filter.label), ['Include cancelled: No', 'Tags: 2 selected', 'Status: Opens Soon']);
    assert.equal(app.pendingFilters(new URL('https://example.test/products?show_cancelled=1'), { show_cancelled: { default: '1' } }).length, 0);
});

test('tabs, chips and count update before fetch resolves, then roll back on failure', async () => {
    const app = setup(), ui = addPreviewControls(app);
    let fail;
    app.context.fetch = () => new Promise((resolve, reject) => { fail = reject; });
    const request = app.loadList(app.root, new URL(ui.current.href));
    assert.equal(ui.current.getAttribute('aria-current'), 'page');
    assert.equal(ui.all.getAttribute('aria-current'), null);
    assert.equal(ui.badge.textContent, '2');
    assert.equal(ui.chips.hidden, false);
    assert.equal(ui.chips.childNodes[0].getAttribute('aria-label'), 'Remove filter: Include cancelled: No');
    const removal = new URL(ui.chips.childNodes[0].href);
    assert.equal(removal.searchParams.get('show_cancelled'), '1');
    assert.equal(removal.searchParams.get('list_starts_at_min'), '2026-09-06');
    assert.deepEqual(app.content.nodes, ['original']);
    fail(new Error('Offline'));
    await request;
    assert.equal(ui.all.getAttribute('aria-current'), 'page');
    assert.equal(ui.current.getAttribute('aria-current'), null);
    assert.equal(ui.chips.hidden, true);
    assert.equal(ui.badge.hidden, true);
});

test('a superseded failure cannot roll back the newest filter preview', async () => {
    const app = setup(), ui = addPreviewControls(app);
    let failFirst, finishSecond;
    app.context.fetch = () => new Promise((resolve, reject) => { failFirst = reject; });
    const first = app.loadList(app.root, new URL(ui.current.href));
    app.context.fetch = url => new Promise(resolve => { finishSecond = () => resolve({ ok: true, url, headers: { get: () => 'text/html' }, text: async () => '' }); });
    const second = app.loadList(app.root, new URL(ui.all.href));
    failFirst(new Error('Old request'));
    await first;
    assert.equal(ui.all.getAttribute('aria-current'), 'page');
    assert.equal(ui.current.getAttribute('aria-current'), null);
    assert.equal(ui.chips.hidden, true);
    finishSecond();
    await second;
    assert.equal(app.alerts.length, 0);
});

test('pagination clicks are intercepted for AJAX including synthetic keyboard clicks', async () => {
    const app = setup();
    let prevented = false;
    const link = new app.context.Element();
    Object.assign(link, {
        href: 'https://example.test/products?page=2', target: '', hasAttribute: () => false,
        closest: selector => selector === 'a[href]' ? link : selector === '[data-dynamic-list]' ? app.root : link,
    });
    app.listeners.click({ target: link, defaultPrevented: false, preventDefault() { prevented = true; } });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(prevented, true);
    assert.deepEqual(app.content.nodes, ['updated']);
    assert.equal(app.redirects.length, 0);
});

test('changing rows per page keeps page sizes and resets only page numbers', () => {
    const app = setup();
    const url = app.listUrl({ action: '/products', entries: [['per_page', '50'], ['expenses_page_per_page', '100'], ['page', '4'], ['expenses_page', '3']] });
    assert.equal(url.searchParams.get('per_page'), '50');
    assert.equal(url.searchParams.get('expenses_page_per_page'), '100');
    assert.equal(url.searchParams.has('page'), false);
    assert.equal(url.searchParams.has('expenses_page'), false);
});
