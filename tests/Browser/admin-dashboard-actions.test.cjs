const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('refreshed attendance cards show only the workshop, schedule and location', () => {
    const source = fs.readFileSync('resources/js/admin-dashboard-actions.js', 'utf8');
    const end = source.indexOf('\n\nconst updateActionColumns');
    assert.notEqual(end, -1);

    class FakeElement {
        constructor(tagName) {
            this.tagName = tagName;
            this.children = [];
            this.dataset = {};
            this.attributes = {};
            this.className = '';
            this.href = '';
            this._textContent = '';
        }

        append(...children) {
            this.children.push(...children);
        }

        setAttribute(name, value) {
            this.attributes[name] = value;
        }

        set textContent(value) {
            this._textContent = String(value);
        }

        get textContent() {
            return this._textContent + this.children.map(child => child.textContent).join('');
        }
    }

    const context = {
        URL,
        window: { location: { origin: 'https://test.stemmechanics.com.au' } },
        document: { createElement: tagName => new FakeElement(tagName) },
    };
    vm.createContext(context);
    vm.runInContext(`${source.slice(0, end)}\nglobalThis.renderActionCard = renderActionCard;`, context);

    const card = context.renderActionCard({
        title: 'Mark Attendance',
        description: 'Recent robotics workshop · Tue 22 Sep, 10:30 am · Julia Creek Smart Hub · 0/1 marked',
        url: '/admin/workshops/recent-robotics/attendance',
        icon: 'fa-solid fa-clipboard-check',
        tone: 'violet',
        title_no_wrap: true,
        attendance_details: {
            workshop: 'Recent robotics workshop',
            schedule: 'Tue 22 Sep, 10:30 am',
            location: 'Julia Creek Smart Hub',
            phase: 'Needs attendance',
            attended: 0,
            total: 1,
        },
    });
    const copy = card.children[0].children[1];

    assert.deepEqual(copy.children.map(child => child.textContent), [
        'Mark Attendance',
        'Recent robotics workshop',
        'Tue 22 Sep, 10:30 am',
        'Julia Creek Smart Hub',
    ]);
    assert.equal(card.textContent.includes('Needs attendance'), false);
    assert.equal(card.textContent.includes('0 of 1 marked'), false);
    assert.equal(copy.children[2].className, 'mt-1.5 block text-sm leading-snug text-gray-700');
});
