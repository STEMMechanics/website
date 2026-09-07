// Keep the picker and camera UI out of the initial bundle on every page.
let pickerPromise;
window.SMMediaPicker = {
    async open(...args) {
        try {
            pickerPromise ||= import('./media-picker.js');
            await pickerPromise;
            return window.SMMediaPicker.open(...args);
        } catch (error) {
            pickerPromise = null;
            window.SM?.banner('Could not load media picker', 'Please try again or refresh the page.', 'danger');
        }
    },
};
