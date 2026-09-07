import './bootstrap';
import './media-picker-loader.js';
import './tooltip.js';

document.addEventListener('click', (event) => {
    const spoiler = event.target instanceof Element ? event.target.closest('[data-spoiler]') : null;

    if (!(spoiler instanceof HTMLElement)) {
        return;
    }

    spoiler.classList.toggle('is-revealed');
});

import './device-preferences.js';

import './dynamic-lists.js';

import './list-controls';
import './admin-media-list';
import './bulk-editor';
import './record-editor';
import './allocation-tally';
import './workshop-line';

import './reminder-list';
import './invoice-allocation-list';

import './list-reorder';

import './allocation-plan-preview';

import './invoice-allocation-editor';

import './drawing-type-picker';
