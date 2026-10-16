const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function editor(overrides = {}) {
    const context = { window: { SM: {} } };
    vm.runInNewContext(fs.readFileSync('resources/js/product-materials.js', 'utf8').replace(/^import .*;\n/gm, ''), context);
    vm.runInNewContext(fs.readFileSync('resources/js/product-variant-editor.js', 'utf8'), context);
    const state = {
        ...context.window.SM.productMaterials({
            unitCost: 0.291, operationalCost: 3, timeCost: 6.5, markup: 30,
            basePackUnits: 1, automaticPricing: true, defaults: { bulkDiscount: 20 },
        }),
        ...context.window.SM.productVariantEditor(),
        productType: 'physical', productTaxRate: .1, basePrice: '12.95',
        displayVariantName: variant => variant.name,
        variants: [
            { id: 2, name: '10 Pack', inventory_units: 10, bulk_discount_percent: 0, price: '129.95', automatic_pricing: true, is_active: true, is_base: true, sort_order: 0 },
            { id: 3, name: '25 Pack', inventory_units: 25, bulk_discount_percent: 0, price: '24.95', automatic_pricing: true, is_active: true, sort_order: 1 },
            { id: 1, name: '2 Pack', inventory_units: 2, bulk_discount_percent: 0, price: '14.95', automatic_pricing: true, is_active: false, sort_order: 2 },
        ],
        ...overrides,
    };
    state.initVariantEditor();
    return state;
}

test('larger packs follow the minimum active pack without scaling a fictional single-item reference', () => {
    const state = editor();
    state.syncAutomaticPrice();
    assert.equal(state.variants[0].price, '17.95');
    assert.equal(state.variants[1].price, '43.95');
    state.basePrice = '999.00';
    assert.equal(state.suggestedSellingPrice(state.variants[1]), 43.95);
    state.basePackUnits = 100;
    state.unitCost = 29.1;
    assert.equal(state.suggestedSellingPrice(state.variants[1]), 43.95);
    state.syncAutomaticPrice();
    state.syncAutomaticPrice();
    assert.equal(state.variants[1].price, '43.95');
});

test('bulk discounts are explicit and cannot reduce a pack below its own cost floor', () => {
    const state = editor();
    const large = state.variants[1];
    assert.equal(state.costBasedMinimumPrice(large), 24.95);
    large.bulk_discount_percent = 20;
    assert.equal(state.suggestedSellingPrice(large), 35.95);
    assert.equal(state.minimumPriceApplied(large), false);
    large.bulk_discount_percent = 100;
    assert.equal(state.suggestedSellingPrice(large), 24.95);
    assert.equal(state.minimumPriceApplied(large), true);
});

test('zero-discount bulk rounding cannot make the screenshot pack dearer per item', () => {
    const state = editor({ unitCost: 2.28, operationalCost: 3, timeCost: 3.5, markup: 0, productBulkDiscount: 0, automaticPricing: false });
    const minimum = state.variants[0], large = state.variants[1];
    large.bulk_discount_percent = '';
    state.syncAutomaticPrice();
    assert.equal(minimum.price, '32.95');
    assert.equal(state.costBasedMinimumPrice(large), 69.95);
    assert.equal(large.price, '81.95');
    assert.ok(state.pricePerItem(large) <= state.pricePerItem(minimum));
    assert.equal(state.isBelowCost(large), false);
    state.syncAutomaticPrice();
    assert.equal(large.price, '81.95');
});

test('small discounts and different pack sizes cannot round above the protected reference rate', () => {
    const state = editor({ unitCost: 2.28, operationalCost: 3, timeCost: 3.5, markup: 0 });
    const minimum = state.variants[0], large = state.variants[1];
    const referenceRate = state.suggestedSellingPrice(minimum) / state.packUnits(minimum);
    for (const units of [11, 12, 15, 20, 25, 50]) {
        large.inventory_units = units;
        for (const discount of [0, .01, .1, 5, 20]) {
            large.bulk_discount_percent = discount;
            const price = state.suggestedSellingPrice(large);
            assert.ok(price / units <= referenceRate, `${units} items, ${discount}% discount`);
            assert.ok(price >= state.costBasedMinimumPrice(large));
        }
    }
});

test('a bulk pack with independently higher costs still keeps its own minimum price', () => {
    const state = editor({ unitCost: 2.28, operationalCost: 3, timeCost: 3.5, markup: 0 });
    const large = state.variants[1];
    large.unit_cost_ex_tax = 200;
    assert.equal(state.costBasedMinimumPrice(large), 227.95);
    assert.equal(state.suggestedSellingPrice(large), 227.95);
    assert.equal(state.minimumPriceApplied(large), true);
    assert.equal(state.isBelowCost(large, true), false);
});

test('the chart uses actual variants and keeps the smallest active pack as its minimum', () => {
    const state = editor();
    state.syncAutomaticPrice();
    assert.deepEqual(Array.from(state.pricingRows(), row => row.name), ['10 Pack', '25 Pack', '2 Pack']);
    assert.equal(state.savingLabel(state.variants[0]), '');
    assert.equal(state.savingLabel(state.variants[1]), '2.1% less per item vs 10 Pack');
    assert.equal(state.minimumPackNotice(), '');
    state.selectBaseVariant(state.variants[1]);
    assert.deepEqual(Array.from(state.pricingRows(), row => row.name), ['25 Pack', '10 Pack', '2 Pack']);
    assert.equal(state.pricingComparisonVariant().id, 2);
    assert.equal(state.minimumPackNotice(), 'Bulk pricing uses 10 Pack, the smallest active pack.');
    assert.equal(state.suggestedSellingPrice(state.variants[1]), 43.95);
});

test('handling costs recommend a larger minimum while smaller packs are active', () => {
    const state = editor();
    state.variants[2].is_active = true;
    const prices = state.variants.map(variant => variant.price);
    assert.equal(state.minimumPackRecommendation().pack.name, '10 Pack');
    assert.equal(state.minimumPackNotice(), 'Recommended minimum pack: 10 Pack. Handling falls from $4.75 to $0.95 per item.');
    state.selectBaseVariant(state.variants[1]);
    assert.equal(state.minimumPackRecommendation().pack.name, '10 Pack');
    assert.equal(state.minimumPackNotice(), 'Recommended minimum pack: 10 Pack. Handling falls from $4.75 to $0.95 per item.');
    assert.deepEqual(state.variants.map(variant => variant.price), prices);
    assert.equal(state.variants[2].is_active, true);
});

test('minimum-pack advice clears when followed and only considers active pack sizes', () => {
    const state = editor();
    assert.equal(state.minimumPackRecommendation(), null);
    assert.equal(state.minimumPackNotice(), '');
    state.variants[2].is_active = true;
    state.variants[0].is_active = false;
    assert.equal(state.minimumPackRecommendation().pack.name, '25 Pack');
    state.variants[1].is_active = false;
    assert.equal(state.minimumPackRecommendation(), null);
    state.variants[1].is_active = true;
    state.variants[2].is_active = false;
    assert.equal(state.minimumPackRecommendation(), null);
});

test('minimum-pack recommendations react to handling allowances and the target', () => {
    const state = editor();
    state.minimumPackHandlingTarget = .5;
    assert.equal(state.minimumPackRecommendation().pack.name, '25 Pack');
    state.minimumPackHandlingTarget = 1;
    state.variants[2].is_active = true;
    state.operationalCost = .5;
    state.timeCost = 1.5;
    assert.equal(state.minimumPackRecommendation(), null);
    state.timeCost = 1.51;
    assert.equal(state.minimumPackRecommendation().pack.name, '10 Pack');
    state.variants[0].inventory_units = 0;
    assert.equal(state.minimumPackRecommendation().pack.name, '25 Pack');
});

test('missing allowances, invalid targets and nonphysical products do not produce minimum-pack advice', () => {
    const state = editor();
    state.variants[2].is_active = true;
    for (const target of [0, -1, NaN, Infinity]) {
        state.minimumPackHandlingTarget = target;
        assert.equal(state.minimumPackRecommendation(), null);
    }
    state.minimumPackHandlingTarget = 1;
    state.operationalCost = '';
    assert.equal(state.minimumPackRecommendation(), null);
    state.operationalCost = 3;
    state.timeCost = -1;
    assert.equal(state.minimumPackRecommendation(), null);
    state.timeCost = 6.5;
    state.productType = 'digital';
    assert.equal(state.minimumPackRecommendation(), null);
    state.productType = 'kit';
    assert.equal(state.minimumPackRecommendation().pack.name, '10 Pack');
});

test('manual minimum-pack edits affect automatic bulk prices and retirement selects the next active size', () => {
    const state = editor();
    const minimum = state.variants[0], large = state.variants[1];
    minimum.automatic_pricing = false;
    minimum.price = '19.95';
    large.bulk_discount_percent = 20;
    state.syncAutomaticPrice();
    assert.equal(large.price, '39.95');
    const inputs = state.variantPricingInputs();
    minimum.price = '22.95';
    assert.notEqual(state.variantPricingInputs(), inputs);
    state.syncAutomaticPrice();
    assert.equal(large.price, '45.95');
    minimum.is_active = false;
    state.variantActiveChanged(minimum);
    state.syncAutomaticPrice();
    assert.equal(large.price, '24.95');
    assert.equal(state.minimumPackNotice(), '');
});

test('an underpriced manual minimum pack cannot lower bulk suggestions or make applying suggestions change other suggestions', () => {
    const state = editor({ unitCost: .02, markup: 15, automaticPricing: false, basePrice: '2.95' });
    const minimum = state.variants[0], large = state.variants[1];
    minimum.automatic_pricing = false;
    minimum.price = '4.95';
    large.automatic_pricing = false;
    large.price = '9.95';
    assert.equal(state.suggestedSellingPrice(minimum), 12.95);
    assert.equal(state.costBasedMinimumPrice(large), 12.95);
    assert.equal(state.suggestedSellingPrice(large), 31.95);
    state.useSuggestedPrice(minimum);
    assert.equal(state.suggestedSellingPrice(large), 31.95);
    minimum.price = '4.95';
    large.bulk_discount_percent = 20;
    large.automatic_pricing = true;
    state.syncAutomaticPrice();
    assert.equal(large.price, '25.95');
    assert.equal(minimum.price, '4.95');
    assert.equal(state.basePrice, '2.95');
});

test('unknown automatic minimum-pack costs keep the last prices rather than using a stale pricing anchor', () => {
    const state = editor();
    state.unitCost = '';
    state.variants[1].unit_cost_ex_tax = 7.275;
    state.syncAutomaticPrice();
    assert.equal(state.variants[0].price, '129.95');
    assert.equal(state.variants[1].price, '24.95');
    state.variants.forEach(variant => { variant.is_active = false; });
    assert.equal(state.minimumPackNotice(), '');
});

test('bulk discounts inherit from the site and product, while variant overrides including zero take precedence', () => {
    const state = editor();
    const large = state.variants[1];
    large.bulk_discount_percent = '';
    assert.equal(state.bulkDiscount(large), 20);
    assert.equal(state.suggestedSellingPrice(large), 35.95);
    state.productBulkDiscount = '10';
    assert.equal(state.effectiveProductBulkDiscount(), 10);
    assert.equal(state.suggestedSellingPrice(large), 40.95);
    large.bulk_discount_percent = '5';
    assert.equal(state.suggestedSellingPrice(large), 42.95);
    large.bulk_discount_percent = '0';
    assert.equal(state.suggestedSellingPrice(large), 43.95);
    large.bulk_discount_percent = null;
    state.productBulkDiscount = 0;
    assert.equal(state.suggestedSellingPrice(large), 43.95);
    state.productBulkDiscount = '';
    assert.equal(state.suggestedSellingPrice(large), 35.95);
    state.costingDefaults.bulkDiscount = 0;
    assert.equal(state.suggestedSellingPrice(large), 43.95);
    assert.equal(state.bulkDiscount(state.variants[0]), 0);
});

test('invalid inherited discounts preserve the last price, and product discount changes are watched', () => {
    const state = editor();
    const watched = [];
    state.$watch = property => watched.push(property);
    state.initMaterials();
    assert.ok(watched.includes('productBulkDiscount'));
    const large = state.variants[1];
    large.bulk_discount_percent = '';
    state.productBulkDiscount = 101;
    state.syncAutomaticPrice();
    assert.equal(state.suggestedSellingPrice(large), null);
    assert.equal(large.price, '43.95');
    large.bulk_discount_percent = 5;
    assert.equal(state.suggestedSellingPrice(large), 42.95);
    large.bulk_discount_percent = -1;
    assert.equal(state.suggestedSellingPrice(large), null);
});
