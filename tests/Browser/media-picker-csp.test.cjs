const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function loadPicker() {
    const store = {
        custom_tabs: [],
        custom_tab_state: {},
        allow_uploads: true,
        camera_supported: false,
        allow_browser: true,
        items: [],
        selected: [],
        disabled: [],
        pagination: [],
        total: 0,
        current_page: 1,
        per_page: 24,
        uploading: false,
        upload_progress: 0,
    };
    const SM = {
        whenAlpineReady() {},
        bytesToString: () => '10 MB',
        maxUploadSize: () => 10,
        mimeMatches: () => true,
        toTitleCase: (value) => value,
        upload() {},
    };
    const window = { SM };
    const context = { window, SM, Alpine: { store: () => store } };

    vm.runInNewContext(fs.readFileSync('resources/js/media-picker.js', 'utf8'), context);

    return { picker: window.SMMediaPicker, SM, store };
}

test('media picker directives use CSP-compatible expressions', () => {
    const { picker, store } = loadPicker();
    store.custom_tabs = picker.normalizeCustomTabs([{ id: 'custom-filter', label: 'Custom Filter' }]);
    const html = picker.buildHtml();
    const expressions = [...html.matchAll(/\b(?:x-[\w:.-]+|:[\w.-]+)\s*=\s*"([^"]*)"/gs)]
        .map(([, expression]) => expression);

    assert.doesNotMatch(html, /\bx-html\s*=/);
    assert.ok(expressions.every((expression) => !expression.includes('?.')));
    assert.ok(expressions.every((expression) => !expression.includes('=>')));
    assert.ok(expressions.every((expression) => !/\b(?:Number|Math)\b/.test(expression)));
    assert.ok(expressions.every((expression) => !/(?<![$\w])SMMediaPicker\./.test(expression)));
    assert.ok(html.includes('x-on:click.prevent="$SMMediaPicker.activateTab(\'browser\')"'));
    assert.ok(html.includes('x-on:click.prevent="$SMMediaPicker.activateTab(\'custom-filter\')"'));
});

test('media picker display helpers clamp progress and summarize results', () => {
    const { SM } = loadPicker();

    assert.equal(SM.mediaPickerProgressStyle(130), 'width: 100%');
    assert.equal(SM.mediaPickerProgressLabel(-5), '0% complete');
    assert.equal(SM.mediaPickerResultSummary(2, 24, 33), 'Showing 25 to 33 of 33 results');
    assert.equal(SM.mediaPickerResultSummary(1, 24, 0), '');
});
