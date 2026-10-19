const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const context = { window: {} };
vm.runInNewContext(fs.readFileSync('resources/js/workshop-line.js', 'utf8').replace('export function', 'function'), context);
const sm = context.window.SM;

function single(notes = '') {
    return { kind: 'workshop', description: 'Library Minecraft', workshop_date: '2027-02-17', workshop_hours: 1, workshop_seats: 10, notes };
}

test('single workshop descriptions and names are separate and placeholders stay live after edits', () => {
    const item = single('1 hr × 10 seats');
    sm.initializeWorkshopText(item);
    assert.equal(item.description, 'Charged per hour, per seat');
    assert.equal(item.details_json.workshop.name, 'Library Minecraft');
    assert.equal(item.notes, '- {date} - {workshop} - ({duration} x {seats})');
    assert.equal(sm.workshopNotesPreview(item), '- 17/02/2027 - Library Minecraft - (1 hr x 10 seats)');

    const editor = sm.workshopFundingEditor(item, []);
    editor.init();
    editor.descriptionValue = 'Paper Speakers';
    item.workshop_hours = 1.5;
    item.workshop_seats = 12;
    sm.updateWorkshopLine(item);
    assert.equal(item.description, 'Charged per hour, per seat');
    assert.equal(item.details_json.workshop.name, 'Paper Speakers');
    assert.equal(item.quantity, 18);
    assert.equal(sm.workshopNotesPreview(item), '- 17/02/2027 - Paper Speakers - (1.5 hrs x 12 seats)');
    assert.equal(item.notes, sm.defaultWorkshopText);

    editor.choose({ id: 'linked', title: 'Straw Towers', date: '2027-03-02', hours: 2 });
    assert.equal(item.description, 'Charged per hour, per seat');
    assert.equal(item.details_json.workshop.name, 'Straw Towers');
    assert.equal(sm.workshopNotesPreview(item), '- 02/03/2027 - Straw Towers - (2 hrs x 12 seats)');
});

test('custom and cleared text survives changes and reloads until the template is explicitly reset', () => {
    let item = single();
    sm.initializeWorkshopText(item);
    item.notes = 'Bring a laptop.\n- {workshop}: {seats} for {duration}';
    sm.markWorkshopNotesEdited(item);
    item.description = '';
    item.workshop_date = '';
    sm.updateWorkshopLine(item);
    assert.equal(item.details_json.workshop.date, null);
    assert.equal(sm.workshopNotesPreview(item), 'Bring a laptop.\n- Library Minecraft: 10 seats for 1 hr');

    item.notes = '';
    sm.markWorkshopNotesEdited(item);
    item = JSON.parse(JSON.stringify(item));
    sm.initializeWorkshopText(item);
    item.workshop_seats = 0;
    sm.updateWorkshopLine(item);
    assert.equal(item.description, '');
    assert.equal(item.notes, '');
    assert.equal(sm.workshopNotesPreview(item), '');
    assert.equal(item.quantity, 0);
    sm.refreshWorkshopNotes(item);
    assert.equal(sm.workshopNotesPreview(item), '- Library Minecraft - (1 hr x 0 seats)');
});

test('multi-workshop placeholders repeat per row while plain instructions appear once', () => {
    const item = { kind: 'multi_workshop', description: 'Charged per hour, per seat', notes: '', workshops: [
        { description: 'Minecraft', workshop_date: '2027-02-17', workshop_hours: 1, workshop_seats: 10 },
        { description: 'STEM', workshop_date: '', workshop_hours: 2, workshop_seats: 15 },
    ] };
    sm.initializeWorkshopText(item);
    item.notes = 'Bring a laptop.\n' + item.notes;
    assert.equal(sm.workshopNotesPreview(item), 'Bring a laptop.\n- 17/02/2027 - Minecraft - (1 hr x 10 seats)\n- STEM - (2 hrs x 15 seats)');
    item.workshops.splice(0, 1);
    sm.updateWorkshopLine(item);
    assert.equal(sm.workshopNotesPreview(item), 'Bring a laptop.\n- STEM - (2 hrs x 15 seats)');
    assert.equal(item.notes, 'Bring a laptop.\n' + sm.defaultWorkshopText);
});

test('legacy automatic notes upgrade without duplicating bullets or losing custom instructions', () => {
    for (const notes of ['1 hr / 10 seats', '1 hr × 10 seats = 10 seat-hours', '- 17/02/2027 - Library Minecraft - (1 hr × 10 seats)']) {
        const item = single('Bring a laptop.\n' + notes);
        sm.initializeWorkshopText(item);
        assert.equal(item.notes, 'Bring a laptop.\n' + sm.defaultWorkshopText);
        sm.initializeWorkshopText(item);
        assert.equal(item.notes, 'Bring a laptop.\n' + sm.defaultWorkshopText);
    }
    const empty = single('');
    empty.details_json = { workshop_notes_mode: 'manual' };
    sm.initializeWorkshopText(empty);
    assert.equal(empty.notes, '');
});
