import './bootstrap';
// Emit a local, versioned classic script for layouts that need dialogs before modules run.
import.meta.glob(['../../node_modules/sweetalert2/dist/sweetalert2.all.min.js'], { eager: true, query: '?url', import: 'default' });
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
import './product-allocation';
import './product-allocation-editor';
import './workshop-equipment-checkout';
import './workshop-delivery';
import './workshop-hold-countdown';
import './workshop-line';

import './reminder-list';
import './invoice-allocation-list';

import './list-reorder';

import './allocation-plan-preview';
import './invoice-allocation-calculator';

import './invoice-allocation-editor';

import './drawing-type-picker';
