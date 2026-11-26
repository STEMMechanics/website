const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');

function setup(states) {
    const dom = new JSDOM('<!doctype html><table><tbody></tbody></table>', {
        url: 'https://test.stemmechanics.com.au/',
        runScripts: 'outside-only',
        pretendToBeVisual: true,
    });
    const { window } = dom;
    for (const state of states) {
        const row = window.document.createElement('tr');
        row.setAttribute('x-data', 'smCspAdminWorkshopPhotos3');
        row.setAttribute('data-photo-editor', JSON.stringify(state));
        row.innerHTML = '<td><input x-model="title"><span x-text="caption"></span></td>';
        window.document.querySelector('tbody').append(row);
    }
    window.livewireScriptConfig = {};
    window.eval(fs.readFileSync('vendor/livewire/livewire/dist/livewire.csp.js', 'utf8'));
    const source = fs.readFileSync('resources/views/admin/workshop/photos.blade.php', 'utf8');
    const start = source.indexOf("Alpine.data('smCspAdminWorkshopPhotos3',");
    const end = source.indexOf("Alpine.data('smCspAdminWorkshopPhotos4',", start);
    assert.ok(start >= 0 && end > start);
    window.eval(source.slice(start, end).replaceAll('@js(csrf_token())', JSON.stringify('test-csrf-token')));
    const requests = [];
    window.axios = { put: async (url, payload) => requests.push({ url, payload: JSON.parse(JSON.stringify(payload)) }) };
    window.Alpine.start();
    return { dom, window, requests };
}

test('the photo editor provider initializes on an empty page without any photo variables', async () => {
    const { dom, window, requests } = setup([]);
    try {
        await window.Alpine.nextTick();
        assert.equal(window.document.querySelectorAll('[data-photo-editor]').length, 0);
        assert.deepEqual(requests, []);
    } finally { dom.window.close(); }
});

test('CSP Alpine photo rows initialize, edit and save their own metadata and URLs', async () => {
    const first = {
        title: 'First "photo" <script>not code</script>', photographedAt: '2026-11-20',
        tags: ['first', 'workshop'], storage: 'media', visibility: 'public',
        caption: 'First caption', notes: 'First consent', updateUrl: '/admin/workshops/1/photos/first.jpg',
    };
    const second = {
        title: 'Second photo', photographedAt: '2026-11-21',
        tags: ['second'], storage: 'archive', visibility: 'private',
        caption: 'Second caption', notes: 'Second consent', updateUrl: '/admin/workshops/1/photos/second.jpg',
    };
    const { dom, window, requests } = setup([first, second]);
    try {
        await window.Alpine.nextTick();
        const rows = [...window.document.querySelectorAll('[data-photo-editor]')];
        const states = rows.map(row => window.Alpine.$data(row));
        assert.deepEqual(rows.map(row => row.querySelector('input').value), [first.title, second.title]);
        assert.deepEqual(rows.map(row => row.querySelector('span').textContent), [first.caption, second.caption]);
        assert.notEqual(states[0].tags, states[1].tags);

        rows[0].querySelector('input').value = 'Edited first photo';
        rows[0].querySelector('input').dispatchEvent(new window.Event('input', { bubbles: true }));
        await window.Alpine.nextTick();
        states[0].tags.push('added');
        await states[0].save();
        await states[1].save();
        assert.deepEqual(requests.map(request => request.url), [first.updateUrl, second.updateUrl]);
        assert.deepEqual(requests[0].payload, {
            _token: 'test-csrf-token', title: 'Edited first photo', photographed_at: first.photographedAt,
            tags: 'first, workshop, added', storage_disk: first.storage, visibility: first.visibility,
            caption: first.caption, consent_notes: first.notes,
        });
        assert.equal(requests[1].payload.title, second.title);
        assert.equal(requests[1].payload.tags, 'second');
        assert.equal(requests[1].payload.storage_disk, second.storage);
        assert.equal(requests[1].payload.visibility, second.visibility);
        assert.equal(states[0].saving, false);
        assert.equal(states[1].saving, false);
        assert.equal(window.document.querySelector('script'), null);
    } finally { dom.window.close(); }
});
