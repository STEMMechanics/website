import './bootstrap';
import './media-picker.js';
import './tooltip.js';
import './editor/TipTap.js';
import './workshop-pick-list.js';

document.addEventListener('click', (event) => {
    const spoiler = event.target instanceof Element ? event.target.closest('[data-spoiler]') : null;

    if (!(spoiler instanceof HTMLElement)) {
        return;
    }

    spoiler.classList.toggle('is-revealed');
});
