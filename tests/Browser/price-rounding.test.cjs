const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const dialog = { open: false, showModal() { this.open = true; }, close() { this.open = false; } };
const context = { window: {}, document: { getElementById: () => dialog } };
vm.runInNewContext(fs.readFileSync('resources/js/workshop-line.js', 'utf8').replace('export function', 'function'), context);
context.SM = context.window.SM;
vm.runInNewContext(fs.readFileSync('resources/js/document-product-editor.js', 'utf8'), context);
const sm = context.SM;

test('rounding up produces exact unit prices and totals as attendance changes', () => {
    for (const quantity of [60, 35]) {
        const quote = sm.cleanPriceQuote(14.5, quantity);
        assert.equal(quote.clean, false);
        assert.equal(quote.suggested.unitInc, 14.52);
        assert.equal(quote.suggested.unitEx, 13.2);
        assert.equal(quote.suggested.gross, quantity === 60 ? 871.2 : 508.2);
        assert.equal(quote.previous.unitInc, 14.41);
    }
    assert.equal(sm.cleanPriceQuote(14.52, 35).clean, true);
    assert.equal(sm.cleanPriceQuote(14.52, 35).next.unitInc, 14.63);
});

test('fractional quantities and other tax rates also have exact cent amounts', () => {
    for (const [inclusive, quantity, rate] of [[14.63, 0.25, 0.1], [14.63, 35.5, 0.1], [14.5, 35, 0.15], [0.01, 100, 0.1]]) {
        const selected = sm.cleanPriceQuote(inclusive, quantity, rate).suggested;
        for (const value of [selected.unitEx, selected.unitInc, quantity * selected.unitEx, quantity * selected.unitEx * rate]) {
            assert.ok(Math.abs(value * 100 - Math.round(value * 100)) < 0.0000001);
        }
        assert.equal(selected.gross, Math.round(quantity * selected.unitInc * 100) / 100);
    }
    assert.equal(sm.cleanPriceQuote(14.5, 1.25, 0).clean, true);
    assert.equal(sm.cleanPriceQuote(Infinity, 35), null);
    assert.equal(sm.cleanPriceQuote(-1, 35), null);
});

test('applying a clean price persists the actual rate and reports its effect in the summary', () => {
    const item = { kind: 'workshop', quantity: 60, unit_price_inc_tax: '14.50', gst_applicable: true, auto_pricing: true, details_json: {} };
    sm.roundWorkshopPrice(item);
    assert.equal(item.unit_price_inc_tax, '14.52');
    assert.equal(item.unit_price, '13.20');
    assert.equal(item.details_json.price_rounding.requested, 14.5);
    assert.equal(item.auto_pricing, false);
    assert.equal(sm.lineAmounts(item).gross, 871.2);
    assert.equal(sm.priceRoundingSummary([item]).increase, 1.2);
    item.quantity = 35;
    assert.equal(sm.lineAmounts(item).gross, 508.2);
    assert.equal(sm.priceRoundingSummary([item]).increase, 0.7);
    assert.equal(sm.priceRoundingSummary([item]).pending, 0);
    assert.equal(sm.priceRoundingSummary([item]).adjusted, 1);
});

test('the popup previews neighbouring rates before applying the selected price', () => {
    const item = { kind: 'workshop', quantity: 35, unit_price_inc_tax: '14.50', gst_applicable: true, details_json: {} };
    let saves = 0;
    const editor = sm.lineItemsEditor();
    editor.serializeLineItems = () => { saves++; };
    editor.$nextTick = callback => callback();
    editor.openPriceRounding(item);
    assert.equal(dialog.open, true);
    assert.equal(editor.roundingProposed, 14.52);
    assert.equal(editor.roundingPreview.gross, 508.2);
    editor.moveRoundingPrice(-1);
    assert.equal(editor.roundingProposed, 14.41);
    editor.moveRoundingPrice(1);
    editor.moveRoundingPrice(1);
    assert.equal(editor.roundingPreview.unitInc, 14.63);
    assert.equal(item.unit_price_inc_tax, '14.50');
    editor.applyRoundingPrice();
    assert.equal(item.unit_price_inc_tax, '14.63');
    assert.equal(item.details_json.price_rounding.requested, 14.5);
    assert.equal(sm.lineAmounts(item).gross, 512.05);
    assert.equal(saves, 1);
    assert.equal(dialog.open, false);
});

test('unchanged saved prices remain authoritative when the field loses focus', () => {
    const item = { kind: 'workshop', quantity: 35, unit_price_inc_tax: '14.50', gst_applicable: true,
        saved_pricing: { quantity: 35, price: 14.5, rate: 0.1, net: 461.36, tax: 46.14, gross: 507.5 }, details_json: {} };
    const editor = sm.lineItemsEditor();
    editor.serializeLineItems = () => {};
    editor.normalizeLineItem = () => {};
    editor.finishUnitPrice(item, 0);
    assert.equal(item.unit_price_inc_tax, '14.50');
    assert.equal(item.details_json.price_rounding, undefined);
    assert.equal(sm.lineAmounts(item).gross, 507.5);
    assert.equal(sm.priceRoundingSummary([item]).pending, 1);
});

test('automatic prices round up from the full calculation and discard stale rounding history', () => {
    const item = { kind: 'workshop', workshop_hours: 1, workshop_seats: 10, auto_pricing: true, gst_applicable: true };
    const plan = { clean_prices: true, rules: [{ basis: 'workshop', rate_cents: 1001 }] };
    sm.updateWorkshopLine(item, plan, true);
    assert.equal(item.unit_price_inc_tax, '1.21');
    assert.equal(sm.lineAmounts(item).net, 11);
    assert.ok(Math.abs(item.details_json.price_rounding.requested - 1.1011) < 0.0000001);
    assert.equal(item.auto_pricing, true);
    plan.rules[0].rate_cents = 1100;
    sm.updateWorkshopLine(item, plan, true);
    assert.equal(item.unit_price_inc_tax, '1.21');
    assert.equal(sm.priceRoundingSummary([item]).adjusted, 0);
});

test('manual sub-cent inputs are rounded up before normalising the price field', () => {
    const item = { kind: 'workshop', quantity: 10, unit_price_inc_tax: '1.1011', gst_applicable: true, details_json: {} };
    const editor = sm.lineItemsEditor();
    editor.normalizeLineItem = () => { item.unit_price_inc_tax = Number(item.unit_price_inc_tax).toFixed(2); };
    editor.serializeLineItems = () => {};
    editor.finishUnitPrice(item, 0);
    assert.equal(item.unit_price_inc_tax, '1.21');
});

test('applying the top rounding notice updates saved rates and totals to the previewed amount', () => {
    const lineItems = [
        { kind: 'workshop', quantity: 35, unit_price_inc_tax: '14.50', gst_applicable: true, details_json: {},
            saved_pricing: { quantity: 35, price: 14.5, rate: 0.1, net: 461.36, tax: 46.14, gross: 507.5 } },
        { kind: 'travel', quantity: 0.25, unit_price_inc_tax: '14.63', gst_applicable: true, details_json: {},
            saved_pricing: { quantity: 0.25, price: 14.63, rate: 0.1, net: 3.33, tax: 0.33, gross: 3.66 } },
        { kind: 'custom', quantity: 2, unit_price_inc_tax: '11.00', gst_applicable: true, details_json: {} },
    ];
    const before = JSON.stringify(lineItems);
    const preview = sm.priceRoundingSummary(lineItems);
    assert.equal(preview.pending, 2);
    assert.equal(preview.roundedTotal, 533.94);
    assert.equal(JSON.stringify(lineItems), before);
    const editor = sm.lineItemsEditor();
    let saves = 0;
    editor.lineItems = lineItems;
    editor.serializeLineItems = () => { saves++; };
    editor.applyPendingPriceRounding();
    assert.equal(saves, 1);
    assert.equal(lineItems[0].unit_price_inc_tax, '14.52');
    assert.equal(lineItems[1].unit_price_inc_tax, '14.96');
    assert.equal(lineItems[2].unit_price_inc_tax, '11.00');
    assert.equal(sm.priceRoundingSummary(lineItems).pending, 0);
    assert.equal(sm.priceRoundingSummary(lineItems).adjusted, 2);
    const total = sm.documentAmounts(lineItems, { count: 3, net: 484.69, tax: 48.47, gross: 533.16 });
    assert.equal(Math.round(total.gross * 100) / 100, preview.roundedTotal);
    assert.equal(Math.round(total.net * 100) / 100, 485.4);
    assert.equal(Math.round(total.tax * 100) / 100, 48.54);
});

test('the rounding action respects locked invoices', () => {
    const item = { kind: 'workshop', quantity: 35, unit_price_inc_tax: '14.50', gst_applicable: true, details_json: {} };
    const editor = sm.lineItemsEditor(true);
    editor.lineItems = [item];
    editor.serializeLineItems = () => assert.fail('Locked invoices cannot be repriced');
    editor.applyPendingPriceRounding();
    assert.equal(item.unit_price_inc_tax, '14.50');
});

test('the top notice stays visible after applying rates and switches to the included adjustment', async () => {
    const { JSDOM } = require('jsdom');
    const source = fs.readFileSync('resources/views/components/finance/price-rounding-summary.blade.php', 'utf8')
        .replace(/^@props.*\n/, '').replaceAll('x-ui.button', 'button').replaceAll('{{ $documentLabel }}', 'quote');
    const dom = new JSDOM(`<!doctype html><form x-data="summaryFixture" x-on:apply-price-rounding="applyPendingPriceRounding()">${source}<input name="quote_number"></form>`, { runScripts: 'outside-only', pretendToBeVisual: true });
    const { window } = dom;
    try {
        const settle = async () => {
            await window.Alpine.nextTick();
            await new Promise(resolve => window.requestAnimationFrame(() => window.requestAnimationFrame(resolve)));
        };
        window.livewireScriptConfig = {};
        window.eval(fs.readFileSync('vendor/livewire/livewire/dist/livewire.js', 'utf8'));
        window.eval(fs.readFileSync('resources/js/workshop-line.js', 'utf8').replace('export function', 'function'));
        window.eval(fs.readFileSync('resources/js/document-product-editor.js', 'utf8'));
        window.Alpine.data('summaryFixture', () => Object.assign(window.SM.lineItemsEditor(), {
            isLocked: false,
            lineItems: [{ kind: 'workshop', quantity: 35, unit_price_inc_tax: '14.50', gst_applicable: true, details_json: {} }],
            savedTotals: null, normalizeMoney: value => Number(value).toFixed(2), serializeLineItems() {},
        }));
        window.Alpine.start();
        await settle();
        const banner = window.document.querySelector('[data-price-rounding-review]');
        const pending = banner.querySelector('div[x-show="roundingSummary.pending"]');
        const adjusted = banner.querySelector('p[x-show="roundingSummary.adjusted"]');
        const apply = [...banner.querySelectorAll('button')].find(button => button.textContent.includes('Apply rounded rates'));
        assert.notEqual(banner.style.display, 'none');
        assert.notEqual(pending.style.display, 'none');
        assert.equal(adjusted.style.display, 'none');
        apply.click();
        await settle();
        assert.notEqual(banner.style.display, 'none');
        assert.equal(pending.style.display, 'none');
        assert.equal(apply.style.display, 'none');
        assert.notEqual(adjusted.style.display, 'none');
        assert.match(adjusted.textContent, /Price rounding included in total: \+\$0\.70/);
    } finally { window.close(); }
});

test('the caution clears after using a clean rate and stays cleared when rounding history is reloaded', async () => {
    const { JSDOM } = require('jsdom');
    const source = fs.readFileSync('resources/views/components/finance/unit-price-field.blade.php', 'utf8');
    const warning = source.match(/<x-ui\.button\b[\s\S]*?<\/x-ui\.button>/)[0].replaceAll('x-ui.button', 'button');
    const dom = new JSDOM(`<!doctype html><div x-data="roundingFixture">${warning}<dialog id="finance-price-rounding"></dialog></div>`, { runScripts: 'outside-only', pretendToBeVisual: true });
    const { window } = dom;
    try {
        const settle = async () => {
            await window.Alpine.nextTick();
            await new Promise(resolve => window.requestAnimationFrame(() => window.requestAnimationFrame(resolve)));
        };
        window.livewireScriptConfig = {};
        window.eval(fs.readFileSync('vendor/livewire/livewire/dist/livewire.js', 'utf8'));
        window.eval(fs.readFileSync('resources/js/workshop-line.js', 'utf8').replace('export function', 'function'));
        window.eval(fs.readFileSync('resources/js/document-product-editor.js', 'utf8'));
        const popup = window.document.querySelector('dialog');
        popup.showModal = () => { popup.open = true; };
        popup.close = () => { popup.open = false; };
        window.Alpine.data('roundingFixture', () => Object.assign(window.SM.lineItemsEditor(), {
            item: { kind: 'workshop', quantity: 35, unit_price_inc_tax: '14.50', gst_applicable: true, details_json: {} },
            serializeLineItems() {},
        }));
        window.Alpine.start();
        await settle();
        const root = window.document.querySelector('[x-data]');
        const state = window.Alpine.$data(root);
        const button = root.querySelector('button');
        assert.notEqual(button.style.display, 'none');
        button.click();
        await settle();
        assert.equal(popup.open, true);
        state.moveRoundingPrice(1);
        state.applyRoundingPrice();
        await settle();
        assert.equal(popup.open, false);
        assert.equal(state.item.unit_price_inc_tax, '14.63');
        assert.equal(button.style.display, 'none');
        assert.equal(window.SM.priceRoundingSummary([state.item]).adjusted, 1);
        assert.equal(window.SM.priceRoundingSummary([state.item]).pending, 0);
        state.item = JSON.parse(JSON.stringify(state.item));
        await settle();
        assert.equal(button.style.display, 'none');
        state.item.quantity = 35.25;
        await settle();
        assert.notEqual(button.style.display, 'none');
        assert.equal(window.SM.priceRoundingSummary([state.item]).pending, 1);
    } finally { window.close(); }
});
