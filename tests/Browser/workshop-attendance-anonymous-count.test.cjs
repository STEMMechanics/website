const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function attendanceEditor(entries, anonymousCount) {
    const blade = fs.readFileSync('resources/views/admin/workshop/attendance.blade.php', 'utf8');
    const start = blade.indexOf('                entries: @js($seedEntries),');
    const end = blade.indexOf('            }" x-init=', start);
    const methods = blade.slice(start, end)
        .replace('entries: @js($seedEntries),', 'entries: [],')
        .replace('anonymousCount: @js($anonymousEntryCount),', `anonymousCount: ${anonymousCount},`);
    const state = vm.runInNewContext('({' + methods + '})');
    state.entries = entries;
    return state;
}

function namedEntry(id, childName) {
    return {
        id,
        child_name: childName,
        guardian_name: '',
        email: '',
        phone: '',
        media_consent: false,
    };
}

function blankEntry() {
    return {
        id: 0,
        child_name: '',
        guardian_name: '',
        email: '',
        phone: '',
        media_consent: false,
    };
}

test('anonymous attendance is represented by a count while named records remain rows', () => {
    const blade = fs.readFileSync('resources/views/admin/workshop/attendance.blade.php', 'utf8');
    const state = attendanceEditor([namedEntry(1, 'Ada Lovelace'), blankEntry()], 32);

    assert.equal(state.anonymousCount, 32);
    assert.equal(state.namedEntryCount(), 1);
    assert.equal(state.entries.length, 2);
    assert.equal(state.entries.filter(entry => entry.child_name === 'Ada Lovelace').length, 1);
    assert.equal(blade.includes('name="anonymous_count"'), true);
    assert.equal(blade.includes('bulk-anonymous-dialog'), false);
    assert.equal(blade.includes('x-model="entry.is_anonymous"'), false);
});
