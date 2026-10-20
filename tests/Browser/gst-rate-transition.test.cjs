const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function pricing() {
    const schedule = [{ effective_from: '2000-07-01', rate: 0.10 }, { effective_from: '2027-07-01', rate: 0.15 }];
    const context = { window: {}, document: { querySelector: selector => ({ content: selector.includes('gst-rates') ? JSON.stringify(schedule) : '0.15' }), getElementById() {} } };
    vm.runInNewContext(fs.readFileSync('resources/js/workshop-line.js', 'utf8').replace('export function', 'function'), context);
    context.SM = context.window.SM;
    vm.runInNewContext(fs.readFileSync('resources/js/document-product-editor.js', 'utf8'), context);
    vm.runInNewContext(fs.readFileSync('resources/js/allocation-plan-preview.js', 'utf8').replace("import { updateWorkshopLine } from './workshop-line';", '').replace('export function', 'function'), context);
    return context;
}

test('current-rate workshop rounding and allocation previews agree at fifteen percent', () => {
    const context = pricing();
    const sm = context.SM;
    const plan = { pricing_participants: 10, clean_prices: true, rounding_step: 0, travel_rounding_step: 0, travel_cents: 2300,
        rules: [{ category_id: 1, basis: 'participant', rate_cents: 1250 }, { category_id: 2, basis: 'travel', rate_cents: 2000 }] };
    const item = { kind: 'workshop', auto_pricing: true, gst_applicable: true, workshop_hours: 1, workshop_seats: 15 };
    sm.updateWorkshopLine(item, plan, true);
    assert.equal(item.tax_rate, 0.15);
    assert.equal(item.unit_price_inc_tax, '14.49');
    assert.equal(sm.lineAmounts(item).net, 189);
    assert.equal(sm.lineAmounts(item).tax, 28.35);
    assert.equal(sm.lineAmounts(item).gross, 217.35);
    assert.equal(sm.priceRoundingForLine(item).clean, true);
    const preview = context.calculatePlanPreview(plan, 1, 15, 0);
    assert.equal(preview.workshopRounding.suggested.unitInc, 14.49);
    assert.equal(preview.cleanInvoiceGross, 217.35);
    assert.equal(sm.gstRateForDate('2027-06-30'), 0.10);
    assert.equal(sm.gstRateForDate('2027-07-01'), 0.15);
});

test('historical rates retain totals and do not produce rounding warnings against the current rate', () => {
    const { SM: sm } = pricing();
    const item = { kind: 'workshop', quantity: 10, unit_price: '12.50', unit_price_inc_tax: '13.75', tax_rate: 0.10, gst_applicable: true,
        saved_pricing: { net: 125, tax: 12.5, gross: 137.5, quantity: 10, price: 13.75, rate: 0.10 }, details_json: {} };
    sm.roundWorkshopPrice(item);
    assert.equal(sm.priceRoundingForLine(item), null);
    assert.equal(sm.priceRoundingSummary([item]).pending, 0);
    assert.equal(sm.lineAmounts(item).gross, 137.5);
    assert.equal(item.unit_price_inc_tax, '13.75');
    const editor = sm.lineItemsEditor();
    editor.lineItems = [item];
    let saves = 0;
    editor.serializeLineItems = () => saves++;
    editor.applyCurrentGst();
    assert.equal(saves, 1);
    assert.equal(item.tax_rate, 0.15);
    assert.equal(item.unit_price_inc_tax, '14.49');
    assert.equal(sm.lineAmounts(item).net, 126);
    assert.equal(sm.lineAmounts(item).tax, 18.9);
    assert.equal(sm.lineAmounts(item).gross, 144.9);
});

test('issued invoices and GST-free lines retain their recorded tax treatment', () => {
    const { SM: sm } = pricing();
    const old = { kind: 'custom', quantity: 1, unit_price_inc_tax: '110.00', tax_rate: 0.10, gst_applicable: true };
    const locked = sm.lineItemsEditor(true);
    locked.lineItems = [old];
    locked.serializeLineItems = () => assert.fail('An issued invoice cannot be repriced');
    locked.applyCurrentGst();
    assert.equal(old.tax_rate, 0.10);
    assert.equal(old.unit_price_inc_tax, '110.00');
    const free = { kind: 'workshop', quantity: 10, unit_price_inc_tax: '12.50', tax_rate: 0, gst_applicable: false };
    sm.applyCurrentGst(free);
    assert.equal(free.tax_rate, 0);
    assert.equal(sm.lineAmounts(free).tax, 0);
    assert.equal(sm.lineAmounts(free).gross, 125);
});

test('the saved-GST notice applies current GST only after its explicit action', async () => {
    const { JSDOM } = require('jsdom');
    const notice = fs.readFileSync('resources/views/components/finance/gst-rate-notice.blade.php', 'utf8').replaceAll('x-ui.button', 'button');
    const dom = new JSDOM(`<!doctype html><meta name="gst-rate" content="0.15"><form x-data="gstFixture">${notice}<div x-data="SM.lineItemsEditor()" x-on:apply-current-gst.window="applyCurrentGst()"></div></form>`, { runScripts: 'outside-only', pretendToBeVisual: true });
    const { window } = dom;
    try {
        window.livewireScriptConfig = {};
        window.eval(fs.readFileSync('vendor/livewire/livewire/dist/livewire.js', 'utf8'));
        window.eval(fs.readFileSync('resources/js/workshop-line.js', 'utf8').replace('export function', 'function'));
        window.eval(fs.readFileSync('resources/js/document-product-editor.js', 'utf8'));
        window.Alpine.data('gstFixture', () => ({ isLocked: false,
            lineItems: [{ kind: 'workshop', quantity: 10, unit_price_inc_tax: '13.75', tax_rate: 0.1, gst_applicable: true }],
            serializeLineItems() { window.submittedLines = JSON.stringify(this.lineItems); },
        }));
        window.Alpine.start();
        const settle = async () => { await window.Alpine.nextTick(); await new Promise(resolve => window.requestAnimationFrame(resolve)); };
        await settle();
        const banner = window.document.querySelector('[role="status"]');
        assert.notEqual(banner.style.display, 'none');
        assert.match(banner.textContent, /Saved GST rate:/);
        assert.match(banner.textContent, /10.00%/);
        assert.match(banner.textContent, /15.00%/);
        assert.equal(window.submittedLines, undefined);
        banner.querySelector('button').click();
        await settle();
        const submitted = JSON.parse(window.submittedLines)[0];
        assert.equal(submitted.tax_rate, 0.15);
        assert.equal(submitted.unit_price_inc_tax, '14.49');
        assert.equal(banner.style.display, 'none');
    } finally { window.close(); }
});
