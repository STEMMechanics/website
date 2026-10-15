const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function methods(path, start, end) {
    const source = fs.readFileSync(path, 'utf8');
    const begin = source.indexOf(start);
    const finish = source.indexOf(end, begin);
    assert.ok(begin >= 0 && finish > begin);
    return vm.runInNewContext(`({${source.slice(begin, finish)}})`);
}

const pricing = methods('resources/views/admin/shop/settings.blade.php', 'changePricingType(method) {', 'addShippingMethod() {');

test('switching pricing types retains calculated rates, package sizes, and draft cart value tiers', () => {
    const method = {
        pricing_type: 'cart_value', is_pickup: false, cart_value_tiers: [],
        packages: [{ code: 'small', internal_length_mm: 200, price: '9.95' }],
        weight_tiers: [{ max_weight_grams: 1000, price: '5.00' }],
        shipment_label: 'Shipment', immediate_status_label: 'Ships now', delayed_status_label: 'Ships later',
    };
    const packages = JSON.stringify(method.packages);
    const weights = JSON.stringify(method.weight_tiers);
    pricing.changePricingType(method);
    assert.deepEqual(JSON.parse(JSON.stringify(method.cart_value_tiers)), [
        { min_cart_value: '0.00', price: '12.95' }, { min_cart_value: '150.00', price: '0.00' },
    ]);
    method.cart_value_tiers[0].price = '8.50';
    method.pricing_type = 'pickup';
    pricing.changePricingType(method);
    assert.equal(method.is_pickup, true);
    assert.equal(method.shipment_label, 'Collection');
    method.pricing_type = 'calculated';
    pricing.changePricingType(method);
    assert.equal(method.is_pickup, false);
    assert.equal(method.shipment_label, 'Shipment');
    method.pricing_type = 'cart_value';
    pricing.changePricingType(method);
    assert.equal(method.cart_value_tiers[0].price, '8.50');
    assert.equal(JSON.stringify(method.packages), packages);
    assert.equal(JSON.stringify(method.weight_tiers), weights);
});

test('changing pricing types preserves custom delivery terminology', () => {
    const method = {
        pricing_type: 'pickup', is_pickup: false, cart_value_tiers: [],
        shipment_label: 'Parcel', immediate_status_label: 'Ready', delayed_status_label: 'Later',
    };
    pricing.changePricingType(method);
    assert.equal(method.shipment_label, 'Parcel');
    assert.equal(method.immediate_status_label, 'Ready');
    assert.equal(method.delayed_status_label, 'Later');
});

test('free freight still requires an address while a pickup channel with a custom code does not', () => {
    const path = 'resources/views/shop/checkout.blade.php';
    const state = {
        shippingMethodCode: 'regular',
        cartState: { summary: { shipping_methods: [
            { code: 'regular', estimated_amount: 0, is_pickup: false },
            { code: 'collect-at-workshop', estimated_amount: 0, is_pickup: true },
        ] } },
        hasPhysicalItems: () => true,
        ...methods(path, 'selectedShippingIsPickup() {', 'consolidationSavingsLabel() {'),
        ...methods(path, 'needsShippingAddress() {', 'hasShipmentPlan() {'),
    };
    assert.equal(state.needsShippingAddress(), true);
    state.shippingMethodCode = 'collect-at-workshop';
    assert.equal(state.needsShippingAddress(), false);
});
