window.SM = window.SM || {};
window.SM.workplanCheckoff = (url, initial) => ({
    checked: initial, saving: false,
    async save(task = false) {
        const previous = !this.checked;
        this.saving = true;
        try {
            const response = await fetch(url, { method: 'PATCH', credentials: 'same-origin', headers: {
                'Accept': 'application/json', 'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            }, body: JSON.stringify({ checked: this.checked }) });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'Please try again.');
            this.checked = result.checked;
            if (task && this.checked !== previous) window.dispatchEvent(new CustomEvent('workplan-task-checked', { detail: { change: this.checked ? -1 : 1 } }));
        } catch (error) {
            this.checked = previous;
            SM.banner('Could not save check-off', error.message || 'Please try again.', 'danger');
        } finally { this.saving = false; }
    },
});

window.SM.invoiceFollowup = (url, notes, contacted) => ({
    notes, draft: notes, contacted, saving: false,
    async save(data, checkbox = null, dialog = null) {
        this.saving = true;
        try {
            const response = await fetch(url, { method: 'PATCH', credentials: 'same-origin', headers: {
                Accept: 'application/json', 'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            }, body: JSON.stringify(data) });
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Please try again.');
            this.notes = result.notes;
            this.contacted = result.contacted;
            dialog?.close();
        } catch (error) {
            SM.banner('Could not save invoice follow-up', error.message || 'Please try again.', 'danger', { target: dialog });
        } finally {
            if (checkbox) checkbox.checked = this.contacted !== null;
            this.saving = false;
        }
    },
});
