const { test } = require('node:test');
const assert = require('node:assert/strict');

test('workshop copy preview resolves current values while saved placeholder tokens remain reusable', async () => {
    global.window = { SM: {} };
    const { resolveWorkshopTaskCopy } = await import('../../resources/js/workshop-task-copy.js');
    const values = {
        startDate: new Date(2026, 8, 26, 16, 0),
        '{date-long}': 'Saturday 26 September',
        '{time-range}': '4:00-5:00pm',
        '{location}': 'Julia Creek Smart Hub',
        '{ages}': '8–12',
        '{cost}': 'Free',
        '{workshop-url}': 'https://example.test/workshop',
    };
    const templateCopy = '<p>Join us {date-long}, {time-range} at {location}.</p><p>For ages {ages}; {cost}.</p><p>{workshop-url}</p>';

    assert.equal(
        resolveWorkshopTaskCopy(templateCopy, values),
        'Join us Saturday 26 September, 4:00-5:00pm at Julia Creek Smart Hub.\n\nFor ages 8–12; Free.\n\nhttps://example.test/workshop',
    );
    assert.match(templateCopy, /\{date-long\}/);
    assert.match(templateCopy, /\{workshop-url\}/);
});

test('an unsaved workshop preview removes the unresolved link token instead of copying it', async () => {
    global.window = { SM: {} };
    const { resolveWorkshopTaskCopy } = await import('../../resources/js/workshop-task-copy.js');

    assert.equal(
        resolveWorkshopTaskCopy('<p>Details:</p><p>{workshop-url}</p>', { '{workshop-url}': '' }),
        'Details:',
    );
});
