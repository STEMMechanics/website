const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function attendanceEditor() {
    const blade = fs.readFileSync('resources/views/admin/workshop/attendance.blade.php', 'utf8');
    const methods = blade.slice(blade.indexOf('                    ticketAttendanceIds() {'), blade.indexOf('                    selectedPaymentTickets() {'))
        .replace(/@if\(\$courseSession\).*?@endif/g, '');
    const requests = [];
    const timers = new Map();
    let timerId = 0;
    const state = vm.runInNewContext('({' + methods + '})', {
        FormData,
        window: {
            setTimeout(callback) { timers.set(++timerId, callback); return timerId; },
            clearTimeout(id) { timers.delete(id); },
        },
        fetch(url, options) {
            return new Promise(resolve => requests.push({
                ids: options.body.getAll('attended_ticket_ids[]'),
                respond(ids, ok = true) {
                    resolve({ ok, json: async () => ({ attended_ticket_ids: ids, saved_at_display: 'just now', message: ok ? 'Saved' : 'Save failed' }) });
                },
            }));
        },
    });
    Object.assign(state, {
        tickets: [{ id: 1 }, { id: 2 }, { id: 3 }],
        ticketAttendance: { 1: false, 2: false, 3: false },
        ticketAttendanceSaving: false, ticketAttendanceSaveQueued: false,
        ticketAttendanceSaveTimer: null, ticketAttendanceError: '',
        csrfToken: 'test', ticketAttendanceSaveUrl: '/attendance',
        normalizeTicketId: id => Number(id),
    });
    return { state, requests, timers };
}

test('rapid checks and unchecks survive older responses and reach the queued save', async () => {
    const { state, requests, timers } = attendanceEditor();
    state.toggleTicketAttendance(1, true);
    const first = state.saveTicketAttendance();
    assert.deepEqual(requests[0].ids, ['1']);

    state.toggleTicketAttendance(2, true);
    await state.saveTicketAttendance(); // debounce expires while first request is pending
    assert.equal(requests.length, 1);
    requests[0].respond([1]);
    await first;
    assert.equal(state.ticketAttendance[2], true);
    assert.equal(timers.size, 1);

    const second = state.saveTicketAttendance();
    assert.deepEqual(requests[1].ids, ['1', '2']);
    state.toggleTicketAttendance(1, false);
    state.toggleTicketAttendance(3, true);
    requests[1].respond([1, 2]);
    await second;
    assert.equal(state.ticketAttendance[1], false);
    assert.equal(state.ticketAttendance[3], true);

    const third = state.saveTicketAttendance();
    assert.deepEqual(requests[2].ids, ['2', '3']);
    requests[2].respond([2, 3]);
    await third;
    assert.deepEqual(Array.from(state.ticketAttendanceIds()), [2, 3]);
    assert.equal(state.ticketAttendanceSaving, false);
    assert.equal(state.ticketAttendanceSaveQueued, false);
    assert.equal(timers.size, 0);
});

test('an unchanged selection accepts the server-confirmed active ticket ids', async () => {
    const { state, requests } = attendanceEditor();
    state.toggleTicketAttendance(1, true);
    const saving = state.saveTicketAttendance();
    requests[0].respond([]);
    await saving;
    assert.equal(state.ticketAttendance[1], false);
    assert.equal(state.lastAttendanceSavedAtDisplay, 'just now');
});

test('a failed save preserves the latest clicks for the queued retry', async () => {
    const { state, requests } = attendanceEditor();
    state.toggleTicketAttendance(1, true);
    const first = state.saveTicketAttendance();
    state.toggleTicketAttendance(2, true);
    requests[0].respond([], false);
    await first;
    assert.deepEqual(Array.from(state.ticketAttendanceIds()), [1, 2]);
    const retry = state.saveTicketAttendance();
    assert.deepEqual(requests[1].ids, ['1', '2']);
    requests[1].respond([1, 2]);
    await retry;
    assert.equal(state.ticketAttendanceSaveQueued, false);
});
