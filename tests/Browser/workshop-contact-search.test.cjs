const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const blade = fs.readFileSync('resources/views/admin/workshop/edit.blade.php', 'utf8');
const contacts = [
    { id: 1, name: 'Alex Brown', email: 'alex@example.test', organisation_id: 8, organisation_name: 'Library' },
    { id: 2, name: 'Alex Green', email: 'green@example.test', organisation_id: 9, organisation_name: 'School' },
];

function contactEditor(fetch = async () => ({ ok: true, json: async () => ({ users: contacts }) })) {
    const start = blade.indexOf('                        editContact() {');
    const end = blade.indexOf('                        async findHosts() {', start);
    const methods = blade.slice(start, end).replace("@js(route('admin.organisation.contact-options'))", "'/contact-options'");
    const context = { URL, window: { location: { origin: 'https://example.test' } }, fetch };
    const editor = {
        ...vm.runInNewContext(`({${methods}})`, context),
        contactId: '', contactSearch: '', contactResults: [], contactSearching: false,
        contactSelectedIndex: -1, contactSearchOpen: false, contactSequence: 0,
        hostId: '', hostSearch: '', $refs: {}, $nextTick: callback => callback(),
    };
    const field = blade.match(/<x-ui.input-control id="requested_by_user_search"[\s\S]*?\/>/)[0];
    editor.key = key => {
        const handler = field.match(new RegExp(`x-on:keydown\\.${key}(?:\\.[a-z]+)*="([^"]*)"`))[1];
        vm.runInNewContext(`(function() { with (this) { ${handler} } })`, context).call(editor);
    };
    return editor;
}

test('contact suggestions support arrows, wrapping and Enter selection with an inferred host', async () => {
    const editor = contactEditor();
    editor.contactSearch = 'Alex';
    editor.editContact();
    await editor.findContacts();
    assert.equal(editor.contactSelectedIndex, -1);
    editor.key('enter');
    assert.equal(editor.contactId, '');
    assert.equal(editor.contactResults.length, 2);
    editor.key('down');
    assert.equal(editor.contactSelectedIndex, 0);
    editor.key('down');
    assert.equal(editor.contactSelectedIndex, 1);
    editor.key('down');
    assert.equal(editor.contactSelectedIndex, 0);
    editor.key('up');
    assert.equal(editor.contactSelectedIndex, 1);
    editor.key('enter');
    assert.equal(editor.contactId, 2);
    assert.equal(editor.contactSearch, 'Alex Green · green@example.test · School');
    assert.equal(editor.hostId, 9);
    assert.equal(editor.contactResults.length, 0);
});

test('typing clears a selected contact and choosing another preserves an explicit host', async () => {
    const editor = contactEditor();
    editor.hostId = 20;
    editor.chooseContact(contacts[0]);
    assert.equal(editor.hostId, 20);
    editor.contactSearch = 'Alex';
    editor.editContact();
    assert.equal(editor.contactId, '');
    await editor.findContacts();
    assert.equal(editor.contactSelectedIndex, -1);
    editor.key('up');
    assert.equal(editor.contactSelectedIndex, 1);
    editor.key('down');
    editor.key('enter');
    assert.equal(editor.contactId, 1);
    assert.equal(editor.hostId, 20);
});

test('Escape cancels pending results and prevents a debounced search from reopening the list', async () => {
    let respond;
    let searches = 0;
    const editor = contactEditor(() => { searches++; return new Promise(resolve => { respond = resolve; }); });
    editor.contactSearch = 'Alex';
    editor.editContact();
    const pending = editor.findContacts();
    editor.key('escape');
    respond({ ok: true, json: async () => ({ users: contacts }) });
    await pending;
    await editor.findContacts();
    assert.equal(searches, 1);
    assert.equal(editor.contactResults.length, 0);
    assert.equal(editor.contactSearching, false);
    editor.key('down');
    editor.key('enter');
    assert.equal(editor.contactId, '');
});
