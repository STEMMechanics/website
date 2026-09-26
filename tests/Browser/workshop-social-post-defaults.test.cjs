const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const blade = fs.readFileSync('resources/views/admin/pick-list-template/edit.blade.php', 'utf8');

function socialTaskMethods() {
    const start = blade.indexOf('defaultSocialPostTasks() {');
    const end = blade.indexOf('\n            openTaskEditor(index) {', start);
    assert.notEqual(start, -1);
    assert.notEqual(end, -1);
    return vm.runInNewContext(`({${blade.slice(start, end)}})`);
}

test('default workshop social posts add reusable copy and correctly timed reminders without duplicates', () => {
    const state = {
        tasks: [{ name: 'Prepare materials', notes: '', subtasks: [] }],
        hasSingleTrailingBlankTask() { return this.tasks.length > 0 && String(this.tasks[this.tasks.length - 1].name || '').trim() === ''; },
        ensureSingleTrailingBlankTask() { this.tasks = [...this.tasks.filter(task => String(task.name || '').trim() !== ''), { id: null, name: '', notes: '', subtasks: [] }]; },
        ...socialTaskMethods(),
    };

    state.addDefaultSocialPostTasks();

    assert.deepEqual(
        Array.from(state.tasks, task => task.name),
        [
            'Prepare materials',
            'Social Media: Workshop Announcement',
            'Social Media: Before the Workshop',
            'Workshop Packing',
            'Social Media: Workshop Day Post',
            'Social Media: After the Workshop',
            '',
        ],
    );
    assert.equal(state.allDefaultSocialPostTasksAdded(), true);
    const defaults = state.tasks.slice(1, -1);
    assert.ok(defaults.every(task => task.id === null && task.reminder_enabled === true));
    assert.ok(defaults.filter(task => task.name.startsWith('Social Media:')).every(task => task.notes.includes('{workshop-url}')));
    assert.ok(defaults.find(task => task.name === 'Social Media: Workshop Announcement').notes.includes('{date-long}'));
    assert.ok(defaults.find(task => task.name === 'Social Media: Before the Workshop').notes.includes('{cost}'));
    assert.ok(defaults.find(task => task.name === 'Social Media: Workshop Day Post').notes.includes('{location}'));
    assert.ok(defaults.find(task => task.name === 'Social Media: After the Workshop').notes.includes('{date-long}'));
    assert.deepEqual(
        Array.from(defaults, task => [task.reminder_days, task.reminder_direction, task.reminder_time]),
        [[14, 'before', '12:00'], [3, 'before', '12:00'], [7, 'before', '16:00'], [0, 'before', '06:00'], [1, 'after', '12:00']],
    );

    state.addDefaultSocialPostTasks();
    assert.equal(state.tasks.length, 7);
});

test('default social post button remains useful when only some default tasks already exist', () => {
    const state = {
        tasks: [{ name: 'Social Media: Workshop Announcement', notes: 'Keep this draft', subtasks: [] }],
        hasSingleTrailingBlankTask() { return this.tasks.length > 0 && String(this.tasks[this.tasks.length - 1].name || '').trim() === ''; },
        ensureSingleTrailingBlankTask() { this.tasks = [...this.tasks.filter(task => String(task.name || '').trim() !== ''), { id: null, name: '', notes: '', subtasks: [] }]; },
        ...socialTaskMethods(),
    };

    assert.equal(state.allDefaultSocialPostTasksAdded(), false);
    state.addDefaultSocialPostTasks();

    assert.equal(state.tasks.length, 6);
    assert.equal(state.tasks[0].notes, 'Keep this draft');
    assert.equal(state.allDefaultSocialPostTasksAdded(), true);
});

test('AI social post drafts fill only the new tasks that have not been edited while processing', () => {
    const state = {
        tasks: socialTaskMethods().defaultSocialPostTasks().map(task => ({ ...task, id: null, subtasks: [] })),
        ...socialTaskMethods(),
    };
    const socialTasks = state.tasks.filter(task => task.name.startsWith('Social Media:'));
    const targets = socialTasks.map(task => ({ name: task.name, current_content: task.notes }));
    state.tasks.find(task => task.name === 'Social Media: Before the Workshop').notes = '<p>My edited copy</p>';
    const copyKeys = ['announcement', 'before_workshop', 'workshop_day', 'after_workshop'];
    const result = Object.fromEntries(copyKeys.map(key => [key, `Draft for ${key} with {workshop-url}`]));
    const htmlByKey = Object.fromEntries(copyKeys.map(key => [key, `<p>Draft for ${key} with {workshop-url}</p>`]));

    state.applyDefaultSocialPostCopies({
        result,
        htmlByKey,
        context: { target: { social_tasks: targets } },
    });

    assert.equal(state.tasks.find(task => task.name === 'Social Media: Workshop Announcement').notes, htmlByKey.announcement);
    assert.equal(state.tasks.find(task => task.name === 'Social Media: Before the Workshop').notes, '<p>My edited copy</p>');
    assert.equal(state.tasks.find(task => task.name === 'Social Media: Workshop Day Post').notes, htmlByKey.workshop_day);
    assert.equal(state.tasks.find(task => task.name === 'Social Media: After the Workshop').notes, htmlByKey.after_workshop);
    assert.equal(state.tasks.find(task => task.name === 'Workshop Packing').notes, '');
});

test('calendar action can draft copy when the default tasks already exist', () => {
    const state = {
        tasks: [
            ...socialTaskMethods().defaultSocialPostTasks().map(task => ({ ...task, id: null, subtasks: [] })),
            { id: null, name: '', notes: '', subtasks: [] },
        ],
        hasSingleTrailingBlankTask() { return String(this.tasks[this.tasks.length - 1]?.name || '').trim() === ''; },
        ensureSingleTrailingBlankTask() { this.tasks = [...this.tasks.filter(task => String(task.name || '').trim() !== ''), { id: null, name: '', notes: '', subtasks: [] }]; },
        blueprintFormContext() { return { source: 'blueprint', blueprint: { default_description: 'Build a cardboard game with simple circuits.' } }; },
        ...socialTaskMethods(),
    };
    const originalTasks = state.tasks.length;
    const trigger = { dataset: {} };

    assert.equal(state.allDefaultSocialPostTasksAdded(), true);
    assert.equal(state.addDefaultSocialPostTasks(trigger), true);
    assert.equal(state.tasks.length, originalTasks);

    const requestContext = JSON.parse(trigger.dataset.aiContext);
    assert.equal(requestContext.blueprint.default_description, 'Build a cardboard game with simple circuits.');
    assert.equal(requestContext.target.social_tasks.length, 4);
    assert.ok(requestContext.target.social_tasks.every(task => task.current_content));
});
