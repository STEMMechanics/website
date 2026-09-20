const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup() {
    let queued, resize, change, disconnected = false;
    const sections = [400, 100, 250, 150].map(height => ({style: {}, height, getBoundingClientRect() {return {height: this.height};}}));
    const root = {style: {}, children: sections, getBoundingClientRect: () => ({width: 1000})};
    const media = {matches: true, addEventListener: (_, handler) => change = handler, removeEventListener: () => change = null};
    const window = {SM: {}, matchMedia: () => media};
    vm.runInNewContext(fs.readFileSync('resources/js/workplan-layout.js', 'utf8'), {
        window, requestAnimationFrame: callback => { queued = callback; return 1; }, cancelAnimationFrame: () => {queued = null;},
        getComputedStyle: () => ({columnGap: '20px'}),
        ResizeObserver: class {constructor(callback) {resize = callback;} observe() {} disconnect() {disconnected = true;}},
    });
    const component = window.SM.workplanLayout(); component.$el = root; component.init();
    return {component, root, sections, media, flush: () => queued?.(), resize: () => resize(), change: () => change(), disconnected: () => disconnected};
}

test('anchors the first two sections and sizes rows from rendered content', () => {
    const t = setup(); t.flush();
    assert.equal(t.sections[0].style.gridColumn, '1');
    assert.equal(t.sections[1].style.gridColumn, '2');
    assert.equal(t.sections[2].style.gridColumn, '');
    assert.equal(t.root.style.gridAutoFlow, 'row dense');
    assert.equal(t.sections[0].style.gridRowEnd, 'span 420');
    t.sections[0].height = 600.5; t.resize(); t.flush();
    assert.equal(t.sections[0].style.gridRowEnd, 'span 621');
});

test('restores natural mobile layout and cleans up observers on removal', () => {
    const t = setup(); t.flush(); t.media.matches = false; t.change(); t.flush();
    assert.equal(t.root.style.gridAutoRows, '');
    assert.equal(t.root.style.rowGap, '');
    assert(t.sections.every(section => section.style.gridColumn === '' && section.style.gridRowEnd === ''));
    t.component.destroy(); assert(t.disconnected());
});
