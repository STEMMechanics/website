export function initialisePageUpload(root, upload) {
    const input = root.querySelector('input[type="file"]');
    const overlay = root.querySelector('[data-page-drop-overlay]');
    let depth = 0, busy = false;
    const show = value => { overlay.hidden = !value; };
    const hasFiles = event => Array.from(event.dataTransfer?.types || []).includes('Files');
    const run = async files => {
        if (busy || !files.length) return;
        busy = true;
        input.disabled = true;
        root.querySelector('[data-upload-browse]').disabled = true;
        try { await upload(Array.from(files)); }
        finally { busy = false; input.disabled = false; input.value = ''; root.querySelector('[data-upload-browse]').disabled = false; }
    };
    root.querySelector('[data-upload-browse]').addEventListener('click', () => input.click());
    input.addEventListener('change', () => void run(input.files));
    document.addEventListener('dragenter', event => { if (hasFiles(event)) { event.preventDefault(); depth++; show(!busy); } });
    document.addEventListener('dragover', event => { if (hasFiles(event)) { event.preventDefault(); event.dataTransfer.dropEffect = busy ? 'none' : 'copy'; } });
    document.addEventListener('dragleave', event => { if (hasFiles(event)) { depth = Math.max(0, depth - 1); if (!depth) show(false); } });
    document.addEventListener('drop', event => {
        if (!hasFiles(event)) return;
        const handled = event.defaultPrevented;
        event.preventDefault(); depth = 0; show(false);
        if (!handled) void run(event.dataTransfer.files);
    });
    window.addEventListener('blur', () => { depth = 0; show(false); });
}
