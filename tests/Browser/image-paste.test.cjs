const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('resources/views/components/ui/partials/image-paste.blade.php', 'utf8')
    .split(/<script[^>]*>/)[1].split('</script>')[0];

function setup() {
    const events = {};
    const document = { activeElement: null, addEventListener: (name, listener) => { events[name] = listener; } };
    class Element {}
    class HTMLElement extends Element {
        isConnected = true;
        events = {};
        addEventListener(name, listener) { this.events[name] = listener; }
        closest(selector) { return selector === '[data-image-paste]' ? this : null; }
        focus() { document.activeElement = this; }
    }
    const navigator = { get clipboard() { assert.fail('must only receive native paste events'); } };
    const context = { window: {}, navigator, document, Element, HTMLElement, File };
    vm.runInNewContext(source, context);
    const paste = context.window.SMImagePaste;
    const uploads = [];
    const zone = new HTMLElement();
    paste.register(zone, files => uploads.push(files));
    return { zone, uploads, events, document };
}

test('touch gestures and the native context menu are left to the browser', () => {
    const app = setup();
    const event = { target: app.zone, pointerType: 'touch', defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } };
    for (const name of ['pointerdown', 'pointerup', 'contextmenu']) {
        app.events[name]?.(event);
        app.zone.events[name]?.(event);
    }
    assert.equal(event.defaultPrevented, false);
    assert.equal(app.uploads.length, 0);
});

test('native image pastes upload once and normal text pastes are preserved', () => {
    const app = setup();
    app.document.activeElement = app.zone;
    app.events.focusin({ target: app.zone });
    const image = new File(['photo'], 'image.png', { type: 'image/png' });
    const event = files => ({
        clipboardData: { files, items: files.map(file => ({ kind: 'file', getAsFile: () => file })) },
        defaultPrevented: false,
        preventDefault() { this.defaultPrevented = true; },
        stopPropagation() {},
    });
    const imagePaste = event([image]);
    app.events.paste(imagePaste);
    assert.equal(imagePaste.defaultPrevented, true);
    assert.equal(app.uploads.length, 1);
    assert.equal(app.uploads[0].length, 1);
    const textPaste = event([]);
    app.events.paste(textPaste);
    assert.equal(textPaste.defaultPrevented, false);
});
