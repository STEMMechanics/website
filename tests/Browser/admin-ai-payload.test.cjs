const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('AI receipt payload rebuilds an IndexedDB-restored File and includes the current CSRF token', async () => {
    const source = fs.readFileSync('resources/js/admin-ai.js', 'utf8');
    const start = source.indexOf('const makePayload = async');
    const end = source.indexOf('\n\nconst responseFailureMessage', start);
    assert.notEqual(start, -1);
    assert.notEqual(end, -1);

    class HTMLInputElement {
        constructor(file) {
            this.files = [file];
        }
    }

    const original = new File(['restored receipt bytes'], 'cid:<receipt.pdf>', {
        type: 'application/pdf',
        lastModified: 123456,
    });
    const input = new HTMLInputElement(original);
    const context = {
        Date,
        File,
        FormData,
        HTMLInputElement,
        csrfToken: () => 'current-session-token',
        readFormField: () => '',
        document: { querySelector: () => input },
    };
    vm.createContext(context);
    vm.runInContext(`${source.slice(start, end)}\nglobalThis.makePayload = makePayload;`, context);

    const payload = await context.makePayload({
        dataset: { aiFile: '#receipt', aiFileName: 'receipt_pdf', aiFields: '' },
    }, null);
    const materialized = payload.get('receipt_pdf');

    assert.notEqual(materialized, original);
    assert.equal(materialized.name, 'receipt.pdf');
    assert.equal(materialized.type, 'application/pdf');
    assert.equal(materialized.lastModified, original.lastModified);
    assert.equal(await materialized.text(), 'restored receipt bytes');
    assert.equal(payload.get('_token'), 'current-session-token');
    assert.match(source, /requestHeaders\['X-CSRF-TOKEN'\] = initialToken/);
});

test('AI payload includes a context field when its bound value is temporarily empty', async () => {
    const source = fs.readFileSync('resources/js/admin-ai.js', 'utf8');
    const start = source.indexOf('const makePayload = async');
    const end = source.indexOf('\n\nconst responseFailureMessage', start);
    assert.notEqual(start, -1);
    assert.notEqual(end, -1);

    const context = { FormData, csrfToken: () => '', readFormField: () => '', document: { querySelector: () => null } };
    vm.createContext(context);
    vm.runInContext(`${source.slice(start, end)}\nglobalThis.makePayload = makePayload;`, context);

    const payload = await context.makePayload({
        dataset: { aiContext: '' },
        hasAttribute: name => name === 'data-ai-context',
    }, null);

    assert.equal(payload.get('context'), '{}');
});

test('a refreshed AI token is copied to page forms before the next save', () => {
    const source = fs.readFileSync('resources/js/admin-ai.js', 'utf8');
    const start = source.indexOf('const applyCsrfToken =');
    const end = source.indexOf('\n\nconst valueFor', start);
    assert.notEqual(start, -1);
    assert.notEqual(end, -1);

    const meta = { content: 'stale-token' };
    const formToken = { value: 'stale-token' };
    const widget = { dataset: { aiToken: 'stale-token' } };
    const context = {
        document: {
            querySelector: selector => selector === 'meta[name="csrf-token"]' ? meta : null,
            querySelectorAll: selector => selector === 'input[name="_token"]' ? [formToken]
                : selector === '[data-ai-token]' ? [widget] : [],
        },
    };
    vm.createContext(context);
    vm.runInContext(`${source.slice(start, end)}\nglobalThis.applyCsrfToken = applyCsrfToken;`, context);
    context.applyCsrfToken('fresh-token');

    assert.equal(meta.content, 'fresh-token');
    assert.equal(formToken.value, 'fresh-token');
    assert.equal(widget.dataset.aiToken, 'fresh-token');
    assert.match(source, /applyCsrfToken\(body\.token\)/);
});

test('workshop-description drafts become safe headings, paragraphs and formatted outcome lists', () => {
    const source = fs.readFileSync('resources/js/admin-ai.js', 'utf8');
    const start = source.indexOf('const toWorkshopDescriptionHtml =');
    const end = source.indexOf('\n\nconst positionAiToast', start);
    assert.notEqual(start, -1);
    assert.notEqual(end, -1);

    const context = {};
    vm.createContext(context);
    vm.runInContext(`${source.slice(start, end)}\nglobalThis.toWorkshopDescriptionHtml = toWorkshopDescriptionHtml;`, context);
    const html = context.toWorkshopDescriptionHtml([
        'Build an interactive cardboard game.',
        '',
        '###Learning outcomes',
        '',
        '- **Electrical circuits:** Understand how a circuit is completed.',
        '- **Testing:** Find and fix a loose connection.',
        '',
        '<script>alert(1)</script>',
    ].join('\n'));

    assert.match(html, /<p>Build an interactive cardboard game\.<\/p>/);
    assert.match(html, /<h3>Learning outcomes<\/h3>/);
    assert.match(html, /<ul><li><p><strong>Electrical circuits:<\/strong> Understand how a circuit is completed\.<\/p><\/li>/);
    assert.match(html, /&lt;script&gt;alert\(1\)&lt;\/script&gt;/);
    assert.doesNotMatch(html, /<script>/);
});

test('amended outcomes are inserted into the existing Learning outcomes section without a duplicate heading', () => {
    const source = fs.readFileSync('resources/js/editor/TipTap.js', 'utf8');
    const start = source.indexOf('appendExternalContent(html = \'\') {');
    const end = source.indexOf('\n            init() {', start);
    assert.notEqual(start, -1);
    assert.notEqual(end, -1);

    let inserted;
    const heading = { type: { name: 'heading' }, textContent: 'Learning outcomes', nodeSize: 2 };
    const list = { type: { name: 'bulletList' }, textContent: 'Existing outcome', nodeSize: 8 };
    const paragraph = { type: { name: 'paragraph' }, textContent: 'Closing paragraph', nodeSize: 5 };
    const editor = {
        getHTML: () => '<h3>Learning outcomes</h3><ul><li><p>Existing outcome</p></li></ul><p>Closing paragraph</p>',
        state: { doc: { forEach: callback => { callback(heading, 0); callback(list, 2); callback(paragraph, 10); } } },
        chain() {
            const chain = {
                focus: () => chain,
                insertContentAt: (position, html) => { inserted = { position, html }; return chain; },
                run: () => true,
            };
            return chain;
        },
    };
    const context = { SM: { decodeHtml: value => value }, editor, Date };
    vm.createContext(context);
    vm.runInContext(`globalThis.component = {${source.slice(start, end).trim()}};`, context);

    context.component.appendExternalContent('<h3>Learning outcomes</h3><ul><li><p><strong>Testing:</strong> Explain what learners will understand.</p></li></ul>');

    assert.equal(inserted.position, 10);
    assert.match(inserted.html, /<ul><li>/);
    assert.doesNotMatch(inserted.html, /<h3>/);
});

test('newsletter AI actions replace all header fields and append messages without a draft panel', () => {
    const source = fs.readFileSync('resources/js/admin-ai.js', 'utf8');
    const start = source.indexOf('const applyNewsletterAiAction =');
    const end = source.indexOf('\n\nconst makePayload', start);
    assert.notEqual(start, -1);
    assert.notEqual(end, -1);

    const fields = new Map(['newsletter-subject', 'newsletter-hero_header', 'newsletter-hero_cta'].map(id => [id, {
        value: `old ${id}`,
        events: [],
        dispatchEvent: event => { fields.get(id).events.push(event.type); },
    }]));
    const statuses = [];
    const events = [];
    const context = {
        document: { getElementById: id => fields.get(id) },
        Event: class { constructor(type, options) { this.type = type; this.options = options; } },
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
        window: { dispatchEvent: event => events.push(event) },
        setStatus: (root, message) => statuses.push(message),
        toParagraphHtml: value => `<p>${value}</p>`,
    };
    vm.createContext(context);
    vm.runInContext(`${source.slice(start, end)}\nglobalThis.applyNewsletterAiAction = applyNewsletterAiAction;`, context);

    const root = {};
    const headerTrigger = { dataset: { aiAction: 'newsletter-header' } };
    const header = { subject: 'New subject', hero_header: 'New heading', hero_cta: 'New introduction' };
    assert.equal(context.applyNewsletterAiAction(root, headerTrigger, header), true);
    assert.equal(fields.get('newsletter-subject').value, 'New subject');
    assert.equal(fields.get('newsletter-hero_header').value, 'New heading');
    assert.equal(fields.get('newsletter-hero_cta').value, 'New introduction');
    assert.deepEqual(fields.get('newsletter-subject').events, ['input', 'change']);
    assert.equal(statuses.at(-1), 'Header replaced. Review it before saving.');

    const appendTrigger = { dataset: { aiAction: 'newsletter-message', aiMode: 'append' } };
    assert.equal(context.applyNewsletterAiAction(root, appendTrigger, { message: 'A new paragraph.' }), true);
    assert.equal(events[0].type, 'sm-newsletter-ai-draft');
    assert.equal(events[0].detail.html, '<p>A new paragraph.</p>');
    assert.equal(events[0].detail.mode, 'append');
    assert.equal(statuses.at(-1), 'New paragraphs added. Review before saving.');

    assert.throws(() => context.applyNewsletterAiAction(root, headerTrigger, { subject: 'Partial' }), /incomplete newsletter header/);
});

test('newsletter append mode is sent with the current edited message', async () => {
    const source = fs.readFileSync('resources/js/admin-ai.js', 'utf8');
    const start = source.indexOf('const makePayload = async');
    const end = source.indexOf('\n\nconst responseFailureMessage', start);
    assert.notEqual(start, -1);
    assert.notEqual(end, -1);

    const form = { elements: {} };
    const context = {
        FormData,
        csrfToken: () => 'token',
        readFormField: (_form, name) => name === 'personal_note[body]' ? '<p>Existing words.</p>' : '',
        document: { querySelector: selector => selector === '#newsletter-content-form' ? form : null },
    };
    vm.createContext(context);
    vm.runInContext(`${source.slice(start, end)}\nglobalThis.makePayload = makePayload;`, context);

    const payload = await context.makePayload({
        dataset: { aiScope: '#newsletter-content-form', aiFields: 'personal_note[body]', aiMode: 'append' },
    }, null);

    assert.equal(payload.get('personal_note[body]'), '<p>Existing words.</p>');
    assert.equal(payload.get('mode'), 'append');
});

test('newsletter header fields lock with an animation and restore their prior state', () => {
    const source = fs.readFileSync('resources/js/admin-ai.js', 'utf8');
    const start = source.indexOf('const lockAiFields =');
    const end = source.indexOf('\n\nconst requestDraft', start);
    assert.notEqual(start, -1);
    assert.notEqual(end, -1);

    const makeField = (readOnly = false, ariaBusy = null, animated = false) => {
        const attributes = new Map(ariaBusy === null ? [] : [['aria-busy', ariaBusy]]);
        const classes = new Set(animated ? ['sm-ai-field-processing'] : []);
        return {
            readOnly,
            getAttribute: name => attributes.get(name) ?? null,
            setAttribute: (name, value) => attributes.set(name, value),
            removeAttribute: name => attributes.delete(name),
            classList: {
                contains: name => classes.has(name),
                add: name => classes.add(name),
                toggle: (name, force) => force ? classes.add(name) : classes.delete(name),
            },
            classes,
            attributes,
        };
    };
    const fields = new Map([
        ['subject', makeField()],
        ['hero_header', makeField(true, 'false', true)],
        ['hero_cta', makeField()],
    ]);
    const richTextField = makeField();
    richTextField.setAttribute('contenteditable', 'true');
    const controls = [{
        disabled: false,
        getAttribute: name => name === 'aria-busy' ? null : null,
        setAttribute: () => {},
        removeAttribute: () => {},
    }];
    const form = { elements: { namedItem: name => fields.get(name) } };
    const context = { document: { querySelectorAll: selector => selector.includes('.tiptap') ? [richTextField] : selector.includes('[data-newsletter-refresh]') ? controls : [] } };
    vm.createContext(context);
    vm.runInContext(`${source.slice(start, end)}\nglobalThis.lockAiFields = lockAiFields;`, context);

    const unlock = context.lockAiFields({ dataset: { aiLockFields: 'subject,hero_header,hero_cta', aiLockContent: '#newsletter-note-editor .tiptap', aiLockControls: '#newsletter-header-editor [data-newsletter-refresh]' } }, form);
    for (const field of fields.values()) {
        assert.equal(field.readOnly, true);
        assert.equal(field.classes.has('sm-ai-field-processing'), true);
        assert.equal(field.getAttribute('aria-busy'), 'true');
    }
    assert.equal(controls[0].disabled, true);
    assert.equal(richTextField.getAttribute('contenteditable'), 'false');
    assert.equal(richTextField.getAttribute('aria-busy'), 'true');
    assert.equal(richTextField.classes.has('sm-ai-field-processing'), true);

    unlock();
    assert.equal(fields.get('subject').readOnly, false);
    assert.equal(fields.get('subject').getAttribute('aria-busy'), null);
    assert.equal(fields.get('subject').classes.has('sm-ai-field-processing'), false);
    assert.equal(fields.get('hero_header').readOnly, true);
    assert.equal(fields.get('hero_header').getAttribute('aria-busy'), 'false');
    assert.equal(fields.get('hero_header').classes.has('sm-ai-field-processing'), true);
    assert.equal(controls[0].disabled, false);
    assert.equal(richTextField.getAttribute('contenteditable'), 'true');
    assert.equal(richTextField.getAttribute('aria-busy'), null);
    assert.equal(richTextField.classes.has('sm-ai-field-processing'), false);
    const styles = fs.readFileSync('resources/css/app.css', 'utf8');
    assert.match(styles, /\.sm-ai-field-processing[^{}]*\{[^}]*animation:\s*sm-ai-field-rainbow/s);
    assert.match(styles, /-webkit-background-clip:\s*text/);
    assert.doesNotMatch(styles.match(/\.sm-ai-field-processing\s*\{([^}]+)\}/s)?.[1] || '', /background-color:/);
    assert.match(styles, /\.sm-ai-status-toast\[popover\]\s*\{[^}]*inset-block:\s*auto/s);
    assert.match(styles, /\.sm-ai-status-toast\.is-processing \.sm-ai-progress-bar\s*\{[^}]*animation:\s*sm-ai-progress-sweep\s+1\.25s\s+linear/s);
});

test('receipt processing stars choose new panel positions on each twinkle cycle', () => {
    const source = fs.readFileSync('resources/js/admin-ai.js', 'utf8');
    const start = source.indexOf('const randomiseAiStarPosition =');
    const end = source.indexOf('\n\nconst markAiDefaultAsUserModified', start);
    assert.notEqual(start, -1);
    assert.notEqual(end, -1);

    let onIteration;
    const star = {
        style: {},
        dataset: {},
        addEventListener: (name, handler) => { if (name === 'animationiteration') onIteration = handler; },
        closest: () => ({ classList: { contains: () => true } }),
    };
    const randomValues = [0, 0, 1, 1];
    const context = {
        Math: { round: Math.round, random: () => randomValues.shift() },
        document: {
            readyState: 'complete',
            querySelectorAll: () => [star],
        },
    };
    vm.createContext(context);
    vm.runInContext(`${source.slice(start, end)}\nglobalThis.initialiseAiStars = initialiseAiStars;`, context);
    context.initialiseAiStars();
    const firstPosition = { left: star.style.left, top: star.style.top };
    onIteration();

    assert.deepEqual(firstPosition, { left: '4%', top: '10%' });
    assert.equal(star.style.left, '96%');
    assert.equal(star.style.top, '86%');
    const styles = fs.readFileSync('resources/css/app.css', 'utf8');
    assert.match(styles, /\.sm-ai-star\s*\{[^}]*color: #facc15/s);
});

test('the receipt notice follows the navbar while visible and moves up as it scrolls away', () => {
    const source = fs.readFileSync('resources/js/admin-ai.js', 'utf8');
    const start = source.indexOf('const positionAiToast =');
    const end = source.indexOf('\n\nconst randomiseAiStarPosition', start);
    assert.notEqual(start, -1);
    assert.notEqual(end, -1);

    let navbarBottom = 80;
    let animationFrame;
    const listeners = {};
    const root = { style: {} };
    const navbar = { getBoundingClientRect: () => ({ bottom: navbarBottom }) };
    const context = {
        document: {
            querySelector: selector => selector === '[data-site-navbar]' ? navbar : null,
            querySelectorAll: selector => selector === '[data-ai-auto-file][aria-hidden="false"], [data-ai-toast][aria-hidden="false"]' ? [root] : [],
        },
        window: {
            addEventListener: (event, callback) => { listeners[event] = callback; },
            requestAnimationFrame: callback => { animationFrame = callback; return 1; },
        },
    };
    vm.createContext(context);
    vm.runInContext(source.slice(start, end), context);

    listeners.scroll();
    animationFrame();
    assert.equal(root.style.top, '92px');

    navbarBottom = -40;
    listeners.scroll();
    animationFrame();
    assert.equal(root.style.top, '16px');
    assert.equal(typeof listeners.resize, 'function');

    const styles = fs.readFileSync('resources/css/app.css', 'utf8');
    assert.match(styles, /\.sm-ai-progress-bar\s*\{[^}]*mask-image:\s*linear-gradient\(90deg, transparent,[^}]*transparent\)/s);
});

test('newsletter AI toast enters the top layer and hides after its exit animation', () => {
    const source = fs.readFileSync('resources/js/admin-ai.js', 'utf8');
    const start = source.indexOf('const setAiToastPopoverVisibility =');
    const end = source.indexOf('\n\nconst randomiseAiStarPosition', start);
    assert.notEqual(start, -1);
    assert.notEqual(end, -1);

    let nextTimer = 1;
    const timers = new Map();
    const calls = [];
    const root = {
        _open: false,
        hasAttribute: name => name === 'popover',
        matches: selector => selector === ':popover-open' && root._open,
        showPopover: () => { root._open = true; calls.push('show'); },
        hidePopover: () => { root._open = false; calls.push('hide'); },
        getBoundingClientRect: () => ({ top: 140 }),
    };
    const context = {
        window: {
            clearTimeout: id => timers.delete(id),
            setTimeout: callback => { const id = nextTimer++; timers.set(id, callback); return id; },
        },
        positionAiToast: element => calls.push(element === root ? 'position' : 'wrong target'),
    };
    vm.createContext(context);
    vm.runInContext(`${source.slice(start, end)}\nglobalThis.setAiToastPopoverVisibility = setAiToastPopoverVisibility;`, context);

    context.setAiToastPopoverVisibility(root, true);
    assert.deepEqual(calls, ['position', 'show']);
    context.setAiToastPopoverVisibility(root, false);
    assert.equal(root._open, true);
    [...timers.values()][0]();
    assert.deepEqual(calls, ['position', 'show', 'hide']);
    assert.equal(root._open, false);
});
