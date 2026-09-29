const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function attendanceEditor(entries) {
    const blade = fs.readFileSync('resources/views/admin/workshop/attendance.blade.php', 'utf8');
    const start = blade.indexOf('                entries: @js($seedEntries),');
    const end = blade.indexOf('            }" x-init=', start);
    const methods = blade.slice(start, end)
        .replace('entries: @js($seedEntries),', 'entries: [],');
    const state = vm.runInNewContext('({' + methods + '})');
    state.entries = entries;
    state.$nextTick = callback => callback();
    return state;
}

function anonymousEntry(id = 0) {
    return {
        id,
        is_anonymous: true,
        child_name: '',
        guardian_name: '',
        email: '',
        phone: '',
        media_consent: false,
    };
}

function namedEntry(id, childName) {
    return {
        id,
        is_anonymous: false,
        child_name: childName,
        guardian_name: '',
        email: '',
        phone: '',
        media_consent: false,
    };
}

test('bulk anonymous attendance appends rows while retaining named attendees', () => {
    const state = attendanceEditor([
        anonymousEntry(1),
        anonymousEntry(2),
        anonymousEntry(3),
        namedEntry(4, 'Ada Lovelace'),
        namedEntry(5, 'Katherine Johnson'),
        stateBlankEntry(),
    ]);

    state.bulkAnonymousCount = 15;
    state.addBulkAnonymous();

    assert.equal(state.recordedEntryCount(), 20);
    assert.equal(state.anonymousEntryCount(), 18);
    assert.equal(state.namedEntryCount(), 2);
    assert.equal(state.entries.length, 21);
    assert.equal(state.entries.at(-1).is_anonymous, false);
    assert.equal(state.entries.filter(entry => entry.child_name === 'Ada Lovelace').length, 1);
    assert.equal(state.entries.filter(entry => entry.child_name === 'Katherine Johnson').length, 1);
});

test('bulk anonymous attendance rejects an invalid count without changing the list', () => {
    const state = attendanceEditor([anonymousEntry(1), stateBlankEntry()]);
    const originalLength = state.entries.length;

    state.bulkAnonymousCount = 0;
    state.addBulkAnonymous();

    assert.equal(state.entries.length, originalLength);
    assert.equal(state.bulkAnonymousError, 'Enter a number between 1 and 1,000.');
});

function stateBlankEntry() {
    return {
        id: 0,
        is_anonymous: false,
        child_name: '',
        guardian_name: '',
        email: '',
        phone: '',
        media_consent: false,
    };
}
