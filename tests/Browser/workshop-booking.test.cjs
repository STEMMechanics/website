const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function load(fetch, overrides = {}) {
    const events = [];
    const window = {dispatchEvent: e => events.push(e), location: {assign() { throw new Error('Unexpected navigation'); }}};
    vm.runInNewContext(fs.readFileSync('resources/js/workshop-booking.js', 'utf8'), {window, fetch, Intl, setTimeout, clearTimeout, ...overrides, CustomEvent: class {constructor(type, options) {this.type = type; this.detail = options.detail;}}});
    return {SM: window.SM, events};
}

test('participants choose independent workshops and totals follow their selections', () => {
    const {SM} = load();
    const review = SM.workshopBookingReview({surname:'Example', participants:[
        {firstname:'Young', workshops:['junior']}, {firstname:'Older', workshops:['senior']},
    ], prices:{junior:{price:12, early_price:null, early_places:0}, senior:{price:20, early_price:15, early_places:1}}});
    assert.equal(review.quantity('junior'), 1);
    assert.equal(review.quantity('senior'), 1);
    assert.equal(review.total, 27);
    review.participants[0].workshops.push('senior');
    assert.equal(review.total, 47);
    review.participants.splice(1, 1);
    assert.equal(review.total, 27);
    review.addParticipant();
    assert.equal(review.participants[1].workshops.length, 0);
});

test('adding and removing workshops updates selection, timer and cart without navigating', async () => {
    let received;
    const {SM, events} = load(async (url, options) => {
        received = JSON.parse(options.body);
        return {ok:true, json:async () => ({selected:['anchor','other'], expires_at:'deadline', bookings:[{count:3}]})};
    });
    const suggestions = SM.workshopSuggestions({url:'/cart', csrf:'token', bookingId:'anchor', selected:['anchor']});
    await suggestions.change('other');
    assert.equal(received.action, 'add');
    assert.equal(suggestions.selected.includes('other'), true);
    assert.equal(events[0].detail.booking_id, 'anchor');
    assert.equal(events[1].type, 'workshop-cart-updated');
    await suggestions.change('other');
    assert.equal(received.action, 'remove');
    assert.equal(suggestions.busy, false);
});

test('a capacity error keeps the selection unchanged and displays the server message', async () => {
    const {SM, events} = load(async () => ({ok:false, json:async () => ({errors:{workshop_id:['No places remain.']}})}));
    const suggestions = SM.workshopSuggestions({selected:['anchor']});
    await suggestions.change('full');
    assert.equal(suggestions.selected.length, 1);
    assert.equal(suggestions.error, 'No places remain.');
    assert.equal(suggestions.busy, false);
    assert.equal(events.length, 0);
});

test('review sends the current ticket selection to the cart, including unchecked workshops', () => {
    const {SM, events} = load();
    const review = SM.workshopBookingReview({bookingId:'anchor', surname:'Example', participants:[
        {workshops:['anchor', 'other']}, {workshops:['anchor']}, {workshops:['anchor']},
    ], prices:{anchor:{price:0}, other:{price:0}}});
    let watch;
    review.$watch = (key, callback) => { assert.equal(key, 'participants'); watch = callback; };
    review.$nextTick = callback => callback();
    review.init();
    assert.equal(events.at(-1).detail.count, 4);
    review.participants[0].workshops = ['anchor'];
    watch();
    assert.equal(events.at(-1).type, 'workshop-selection-updated');
    assert.equal(events.at(-1).detail.bookingId, 'anchor');
    assert.equal(events.at(-1).detail.count, 3);
    review.participants.splice(2, 1);
    watch();
    assert.equal(events.at(-1).detail.count, 2);
});

test('navigation waits for participant draft saving and preserves the latest edits', async () => {
    let click, watch, resolveSave;
    const requests = [], navigations = [];
    const window = {dispatchEvent() {}, addEventListener() {}, removeEventListener() {}, location:{assign:url => navigations.push(url)}};
    const {SM} = load(async (url, options) => {
        requests.push(JSON.parse(options.body));
        if (requests.length === 1) await new Promise(resolve => { resolveSave = resolve; });
        return {ok:true,json:async () => ({review_version:"next"})};
    }, {window, document:{addEventListener:(type, callback) => {click = callback;}, removeEventListener() {}}});
    const review = window.SM.workshopBookingReview({draftUrl:'/draft', csrf:'token', bookingId:'anchor', participants:[{firstname:'', surname:'Example', workshops:['anchor']}], prices:{anchor:{price:0}}});
    review.$watch = (key, callback) => {watch = callback;};
    review.$nextTick = callback => callback();
    review.init();
    review.participants[0].firstname = 'Alex';
    watch();
    let prevented = false;
    const navigation = click({button:0, target:{closest:() => ({href:'/workshops', getAttribute:() => '/workshops', hasAttribute:() => false})}, preventDefault() {prevented = true;}});
    assert.equal(prevented, true);
    assert.equal(navigations.length, 0);
    review.participants[0].firstname = 'Alexandra';
    resolveSave();
    await navigation;
    assert.equal(requests.length, 2);
    assert.equal(requests[1].participants[0].firstname, 'Alexandra');
    assert.deepEqual(navigations, ['/workshops']);
    review.destroy();
});

test('failed draft saving keeps details on the page and blocks navigation', async () => {
    let click;
    const window = {dispatchEvent() {}, addEventListener() {}, removeEventListener() {}, location:{assign() {throw new Error('Must not navigate');}}};
    load(async () => {throw new Error('Network unavailable');}, {window, document:{addEventListener:(type, callback) => {click = callback;}, removeEventListener() {}}});
    const review = window.SM.workshopBookingReview({draftUrl:'/draft', participants:[{firstname:'', workshops:['anchor']}], prices:{anchor:{price:0}}});
    review.$watch = () => {};
    review.$nextTick = callback => callback();
    review.init();
    review.participants[0].firstname = 'Alex';
    await click({button:0, target:{closest:() => ({href:'/workshops', getAttribute:() => '/workshops', hasAttribute:() => false})}, preventDefault() {}});
    assert.equal(review.saveError, 'Network unavailable');
    assert.equal(review.participants[0].firstname, 'Alex');
    review.destroy();
});

test('confirming a booking waits for any draft save and prevents later autosaves', async () => {
    let resolveSave, requests = 0, submitted = false, prevented = false;
    const {SM} = load(async () => { requests++; await new Promise(resolve => {resolveSave = resolve;}); return {ok:true,json:async () => ({review_version:"next"})}; });
    const review = SM.workshopBookingReview({draftUrl:'/draft', participants:[{firstname:'Alex', workshops:['anchor']}], prices:{anchor:{price:0}}});
    review.$nextTick = async () => {};
    const saving = review.saveDraft();
    const submit = review.submitReview({preventDefault() {prevented = true;}, target:{requestSubmit() {submitted = true;}}});
    assert.equal(prevented, true);
    assert.equal(submitted, false);
    resolveSave();
    await saving;
    await submit;
    await review.saveDraft();
    assert.equal(submitted, true);
    assert.equal(requests, 1);
});

test('capacity includes reserved spots and allows swapping participants without selecting too many', () => {
    const {SM} = load();
    const review = SM.workshopBookingReview({participants:[{workshops:['limited']},{workshops:['limited']},{workshops:[]}], prices:{limited:{capacity:2}, unlimited:{capacity:null}}});
    assert.equal(review.selectionFull(review.participants[2], 'limited'), true);
    assert.equal(review.selectionFull(review.participants[0], 'limited'), false);
    review.participants[0].workshops = [];
    assert.equal(review.selectionFull(review.participants[2], 'limited'), false);
    review.participants[2].workshops = ['limited'];
    assert.equal(review.selectionFull(review.participants[0], 'limited'), true);
    assert.equal(review.selectionFull(review.participants[0], 'unlimited'), false);
    review.participants[0].workshops = ['limited'];
    assert.equal(review.hasOverCapacitySelection, true);
    review.participants.splice(0, 1);
    assert.equal(review.hasOverCapacitySelection, false);
});

test('removing a selected sold-out workshop updates availability and allows adding it again', async () => {
    const {SM} = load(async (url, options) => {
        const adding = JSON.parse(options.body).action === 'add';
        return {ok:true,json:async () => ({selected:adding ? ['anchor','other'] : ['anchor'],availability:{other:adding ? 0 : 2},bookings:[],expires_at:'deadline'})};
    });
    const suggestions = SM.workshopSuggestions({selected:['anchor','other'],availability:{other:0},participantCount:3});
    assert.equal(suggestions.label('other'), 'Remove');
    await suggestions.change('other');
    assert.equal(suggestions.soldOut('other'), false);
    assert.equal(suggestions.label('other'), 'Add to booking');
    await suggestions.change('other');
    assert.equal(suggestions.label('other'), 'Remove');
    assert.equal(suggestions.error, '');
});

test('draft saves use the latest booking version and stale windows stop submitting', async () => {
    const requests = [], redirects = [];
    const window = {location:{assign:url => redirects.push(url)}};
    load(async (url, options) => {
        requests.push(JSON.parse(options.body));
        return requests.length === 1 ? {ok:true,json:async () => ({review_version:'updated'})} : {ok:false,status:409,json:async () => ({redirect:'/review',message:'Booking changed'})};
    }, {window});
    const review = window.SM.workshopBookingReview({draftUrl:'/draft',reviewVersion:'original',participants:[{firstname:'Alex',workshops:['anchor']}],prices:{anchor:{price:0}}});
    await review.saveDraft();
    assert.equal(requests[0].review_version, 'original');
    assert.equal(review.reviewVersion, 'updated');
    review.participants[0].firstname = 'Sam';
    assert.equal(await review.saveDraft(), false);
    assert.equal(requests[1].review_version, 'updated');
    assert.deepEqual(redirects, ['/review']);
    let prevented = false;
    await review.submitReview({preventDefault() {prevented = true;}});
    assert.equal(prevented, true);
    assert.equal(await review.saveDraft(), false);
    assert.equal(requests.length, 2);
});
