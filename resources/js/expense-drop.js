const expenseReceiptTypes = new Set(['application/pdf', 'image/jpeg', 'image/png', 'image/webp']);
const expenseReceiptExtensions = /\.(?:pdf|jpe?g|png|webp)$/i;

const expenseBatchFormatBytes = (bytes) => {
    if (!Number.isFinite(bytes) || bytes <= 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const exponent = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return (bytes / (1024 ** exponent)).toFixed(exponent === 0 ? 0 : 1) + ' ' + units[exponent];
};

const expenseBatchErrorMessage = (status, body) => {
    const firstError = body?.errors
        ? Object.values(body.errors).flat().find((message) => typeof message === 'string' && message.trim())
        : null;
    if (firstError) return firstError;
    if (status === 413) return 'The selected receipts are too large to upload together. Choose fewer files or smaller files.';
    if (status === 419) return 'Your session expired. Reload the page and try again.';
    if (status === 422) return body?.message || 'One or more files could not be accepted.';

    return body?.message || 'The receipts could not be uploaded. Please try again.';
};

document.querySelectorAll('[data-expense-drop]').forEach((root) => {
    const overlay = root.querySelector('[data-expense-drop-overlay]');
    const error = root.querySelector('[data-expense-drop-error]');
    const input = root.querySelector('[data-expense-batch-input]');
    const batch = root.querySelector('[data-expense-batch]');
    const summary = root.querySelector('[data-expense-batch-summary]');
    const detail = root.querySelector('[data-expense-batch-detail]');
    const percent = root.querySelector('[data-expense-batch-percent]');
    const progress = root.querySelector('[data-expense-batch-progress]');
    const progressBar = root.querySelector('[data-expense-batch-progress-bar]');
    const importUrl = root.dataset.importUrl;
    const csrfUrl = root.dataset.csrfUrl;
    const maxUploadBytes = Number(document.querySelector('meta[name="max-upload-size"]')?.content || 0);
    let csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let activeXhr = null;
    let uploading = false;
    let cancelled = false;
    let dragDepth = 0;

    if (!overlay || !error || !input || !batch || !summary || !detail || !percent || !progress || !progressBar || !importUrl) return;

    const setCsrfToken = (token) => {
        if (typeof token !== 'string' || token === '') return;
        csrfToken = token;
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.content = token;
    };

    const refreshCsrfToken = async () => {
        if (!csrfUrl) throw new Error('Your session expired. Reload the page and try again.');
        const response = await fetch(csrfUrl, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store',
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok || typeof body.token !== 'string' || body.token === '') {
            throw new Error('Your session expired. Reload the page and try again.');
        }
        setCsrfToken(body.token);
        return body.token;
    };

    const showError = (message) => {
        error.textContent = message || '';
        error.hidden = !message;
    };

    const setProgress = (value, loaded = 0, total = 0) => {
        const safeValue = Math.max(0, Math.min(100, Math.round(value)));
        progressBar.style.width = safeValue + '%';
        progress.setAttribute('aria-valuenow', String(safeValue));
        percent.textContent = safeValue + '% complete';
        if (total > 0) {
            detail.textContent = 'Uploading the complete receipt batch · ' + expenseBatchFormatBytes(loaded) + ' of ' + expenseBatchFormatBytes(total);
        }
    };

    const resetBatch = () => {
        activeXhr = null;
        uploading = false;
        if (batch.open) batch.close();
        batch.setAttribute('aria-hidden', 'true');
        input.value = '';
        overlay.hidden = true;
        setProgress(0);
        summary.textContent = 'Preparing receipts…';
        detail.textContent = 'Preparing receipts…';
    };

    const parseResponse = (xhr) => {
        if (typeof xhr.response === 'object' && xhr.response !== null) return xhr.response;
        try {
            return JSON.parse(xhr.responseText || '{}');
        } catch {
            return {};
        }
    };

    const sendUpload = (files, token) => new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        activeXhr = xhr;
        xhr.open('POST', importUrl, true);
        xhr.responseType = 'text';
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        if (token) xhr.setRequestHeader('X-CSRF-TOKEN', token);
        xhr.upload.addEventListener('progress', (event) => {
            if (!event.lengthComputable) return;
            setProgress(event.loaded / event.total * 100, event.loaded, event.total);
        });
        xhr.addEventListener('load', () => {
            activeXhr = null;
            resolve({ status: xhr.status, body: parseResponse(xhr) });
        });
        xhr.addEventListener('error', () => {
            activeXhr = null;
            reject(new Error('The receipt upload could not reach the server.'));
        });
        xhr.addEventListener('abort', () => {
            activeXhr = null;
            reject(new Error('The receipt upload was cancelled.'));
        });

        const payload = new FormData();
        if (token) payload.append('_token', token);
        files.forEach((file) => payload.append('files[]', file, file.name));
        xhr.send(payload);
    });

    const uploadBatch = async (files) => {
        let response = await sendUpload(files, csrfToken);
        if (response.status === 419) {
            const refreshedToken = await refreshCsrfToken();
            response = await sendUpload(files, refreshedToken);
        }
        if (response.status < 200 || response.status >= 300) {
            throw new Error(expenseBatchErrorMessage(response.status, response.body));
        }
        if (!response.body?.redirect) {
            throw new Error('The upload completed without an expense editor to open.');
        }

        return response.body;
    };

    const startUpload = async (fileList) => {
        if (uploading) return;
        const files = Array.from(fileList || []);
        if (files.length === 0) return;

        showError('');
        const invalid = files.find((file) => {
            const type = String(file.type || '').toLowerCase();
            return (!expenseReceiptTypes.has(type) && !expenseReceiptExtensions.test(file.name))
                || (maxUploadBytes > 0 && file.size > maxUploadBytes);
        });
        if (invalid) {
            const tooLarge = maxUploadBytes > 0 && invalid.size > maxUploadBytes;
            showError(tooLarge
                ? invalid.name + ' is larger than the maximum upload size.'
                : invalid.name + ' is not a PDF, JPEG, PNG or WebP image.');
            return;
        }

        uploading = true;
        cancelled = false;
        if (!batch.open) batch.showModal();
        batch.setAttribute('aria-hidden', 'false');
        summary.textContent = 'Uploading ' + files.length + ' ' + (files.length === 1 ? 'receipt' : 'receipts') + '…';
        detail.textContent = 'Preparing the complete upload…';
        setProgress(0);

        try {
            const result = await uploadBatch(files);
            if (cancelled) return;
            setProgress(100);
            summary.textContent = 'Upload complete';
            detail.textContent = 'Opening receipt 1 of ' + (result.count || files.length) + '…';
            window.location.assign(result.redirect);
        } catch (uploadError) {
            if (cancelled) return;
            uploading = false;
            showError(uploadError instanceof Error ? uploadError.message : 'The receipts could not be uploaded.');
            summary.textContent = 'Upload failed';
            detail.textContent = 'Correct the issue and choose the files again.';
        }
    };

    const closeBatch = () => {
        if (!uploading) {
            resetBatch();
            return;
        }

        window.SM?.confirm?.(
            'Cancel receipt upload?',
            'The receipts already selected will not be imported.',
            'Cancel upload',
            (confirmed) => {
                if (!confirmed) return;
                cancelled = true;
                activeXhr?.abort();
                resetBatch();
            },
        );
    };

    batch.addEventListener('cancel', (event) => {
        event.preventDefault();
        closeBatch();
    });
    batch.addEventListener('click', (event) => {
        if (event.target === batch) closeBatch();
    });

    const isFileDrag = (event) => Array.from(event.dataTransfer?.types || []).includes('Files');
    const resetDrag = () => {
        dragDepth = 0;
        overlay.hidden = true;
    };

    document.addEventListener('dragenter', (event) => {
        if (!isFileDrag(event)) return;
        event.preventDefault();
        dragDepth++;
        overlay.hidden = uploading;
    });
    document.addEventListener('dragover', (event) => {
        if (!isFileDrag(event)) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = uploading ? 'none' : 'copy';
    });
    document.addEventListener('dragleave', () => {
        dragDepth = Math.max(0, dragDepth - 1);
        if (!dragDepth) overlay.hidden = true;
    });
    window.addEventListener('blur', resetDrag);
    document.addEventListener('dragend', resetDrag);
    document.addEventListener('drop', (event) => {
        if (!isFileDrag(event)) return;
        resetDrag();
        event.preventDefault();
        void startUpload(event.dataTransfer.files);
    });
    input.addEventListener('change', () => {
        void startUpload(input.files);
        input.value = '';
    });
    root.querySelectorAll('[data-expense-batch-add]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!uploading) input.click();
        });
    });
    root.querySelector('[data-expense-batch-close]')?.addEventListener('click', closeBatch);
});
