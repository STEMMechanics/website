// Hand the receipt to the editor's existing IndexedDB attachment-draft flow.
document.querySelectorAll('[data-expense-drop]').forEach((zone) => {
    const overlay = zone.querySelector('[data-expense-drop-overlay]');
    const error = zone.querySelector('[data-expense-drop-error]');
    let busy = false;
    let dragDepth = 0;
    const isFileDrag = (event) => Array.from(event.dataTransfer?.types || []).includes('Files');
    const resetDrag = () => {
        dragDepth = 0;
        overlay.hidden = true;
    };
    document.addEventListener('dragenter', (event) => {
        if (!isFileDrag(event)) return;
        event.preventDefault();
        dragDepth++;
        if (!busy) overlay.hidden = false;
    });
    document.addEventListener('dragover', (event) => {
        if (!isFileDrag(event)) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = busy ? 'none' : 'copy';
    });
    document.addEventListener('dragleave', () => {
        dragDepth = Math.max(0, dragDepth - 1);
        if (!dragDepth) overlay.hidden = true;
    });
    window.addEventListener('blur', resetDrag);
    document.addEventListener('dragend', resetDrag);
    document.addEventListener('drop', async (event) => {
        resetDrag();
        if (!isFileDrag(event)) return;
        event.preventDefault();
        if (busy) return;
        const files = event.dataTransfer.files;
        if (!files.length) return;
        error.hidden = true;
        const file = files[0];
        const max = Number(document.querySelector('meta[name="max-upload-size"]')?.content || 0);
        try {
            if (files.length !== 1) throw new Error('Please drop one receipt per expense.');
            if (max > 0 && file.size > max) throw new Error('This receipt exceeds the maximum upload size.');
            busy = true;
            const id = crypto.randomUUID();
            const db = await new Promise((resolve, reject) => {
                const request = indexedDB.open('sm-file-drafts', 1);
                request.onupgradeneeded = () => {
                    if (!request.result.objectStoreNames.contains('drafts')) request.result.createObjectStore('drafts');
                };
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(new Error('Unable to prepare the receipt. Please use Record and attach it in the editor.'));
            });
            try {
                await new Promise((resolve, reject) => {
                    const tx = db.transaction('drafts', 'readwrite');
                    tx.objectStore('drafts').put({ blob: file, name: file.name, type: file.type, lastModified: file.lastModified }, `expense-drop:${id}`);
                    tx.oncomplete = resolve;
                    tx.onerror = tx.onabort = () => reject(new Error('Unable to prepare the receipt. Please use Record and attach it in the editor.'));
                });
            } finally {
                db.close();
            }
            const url = new URL(zone.dataset.createUrl, window.location.href);
            url.searchParams.set('receipt_draft', id);
            window.location.assign(url.href);
        } catch (reason) {
            error.textContent = reason.message || 'Unable to prepare the receipt. Please try again.';
            error.hidden = false;
            busy = false;
        }
    });
});
