@once
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    window.SMImagePaste = window.SMImagePaste || {
        activeDropzone: null,
        handlers: new WeakMap(),
        listenerAttached: false,
        register(dropzone, handler) {
            if (!(dropzone instanceof HTMLElement) || typeof handler !== 'function') {
                return;
            }

            const registered = this.handlers.has(dropzone);
            this.handlers.set(dropzone, handler);
            if (registered) return;

            dropzone.addEventListener('click', (event) => {
                this.activeDropzone = dropzone;

                if (!(event.target instanceof Element) || !event.target.closest('button, a, input, select, textarea, [contenteditable]')) {
                    dropzone.focus({ preventScroll: true });
                }
            });

            if (this.listenerAttached) {
                return;
            }

            this.listenerAttached = true;
            const pasteState = this;

            document.addEventListener('focusin', (event) => {
                const focusedDropzone = event.target instanceof Element
                    ? event.target.closest('[data-image-paste]')
                    : null;

                pasteState.activeDropzone = focusedDropzone && pasteState.handlers.has(focusedDropzone)
                    ? focusedDropzone
                    : null;
            }, true);

            document.addEventListener('pointerdown', (event) => {
                const clickedDropzone = event.target instanceof Element
                    ? event.target.closest('[data-image-paste]')
                    : null;

                if (!clickedDropzone) {
                    pasteState.activeDropzone = null;
                }
            }, true);

            document.addEventListener('paste', (event) => {
                if (event.defaultPrevented) {
                    return;
                }

                const focusedDropzone = document.activeElement instanceof Element
                    ? document.activeElement.closest('[data-image-paste]')
                    : null;
                const dropzone = pasteState.activeDropzone?.isConnected
                    ? pasteState.activeDropzone
                    : focusedDropzone;
                const handler = dropzone ? pasteState.handlers.get(dropzone) : null;

                if (!handler) {
                    return;
                }

                const clipboardFiles = [];
                const seen = new Set();
                const addFile = (file) => {
                    if (!file || !String(file.type || '').startsWith('image/')) {
                        return;
                    }

                    const key = `${file.name || ''}:${file.size}:${file.lastModified}:${file.type}`;
                    if (seen.has(key)) {
                        return;
                    }

                    seen.add(key);
                    if (String(file.name || '').trim() === '') {
                        const extension = String(file.type || '').split('/')[1]?.replace(/[^a-z0-9]+/gi, '') || 'png';
                        clipboardFiles.push(new File([file], `pasted-image-${Date.now()}.${extension}`, {
                            type: file.type,
                            lastModified: Date.now(),
                        }));
                        return;
                    }

                    clipboardFiles.push(file);
                };

                Array.from(event.clipboardData?.files || []).forEach(addFile);
                Array.from(event.clipboardData?.items || [])
                    .filter((item) => item.kind === 'file')
                    .map((item) => item.getAsFile())
                    .forEach(addFile);

                if (clipboardFiles.length === 0) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                handler(clipboardFiles);
            }, true);
        },
    };
</script>
@endonce
