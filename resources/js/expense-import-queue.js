const initialiseExpenseImportQueue = () => {
    const form = document.querySelector('form[data-expense-import-file-url]');
    if (!(form instanceof HTMLFormElement) || form.dataset.expenseImportLoading !== 'true') return;

    const receiptInput = document.getElementById('expense-receipt-file');
    const saveButton = document.getElementById('expense-save-button');
    const skipButton = document.getElementById('expense-skip-button');
    const skipLabel = document.getElementById('expense-skip-label');
    const skipLoading = document.getElementById('expense-skip-loading');
    const loading = form.querySelector('[data-expense-import-loading]');
    const loadingText = form.querySelector('[data-expense-import-loading-text]');
    const fileUrl = form.dataset.expenseImportFileUrl;
    const aiStatusUrl = form.dataset.expenseImportAiStatusUrl;
    const skipUrl = form.dataset.expenseImportSkipUrl;
    if (!(receiptInput instanceof HTMLInputElement) || !saveButton || !fileUrl) return;

    saveButton.disabled = true;
    let skipping = false;
    let aiPollTimer = null;
    let aiPollController = null;
    let aiPollingStopped = false;

    const stopAiPolling = () => {
        aiPollingStopped = true;
        if (aiPollTimer !== null) window.clearTimeout(aiPollTimer);
        aiPollTimer = null;
        aiPollController?.abort();
        aiPollController = null;
    };

    const scheduleAiPoll = () => {
        if (aiPollingStopped || !aiStatusUrl) return;
        if (aiPollTimer !== null) window.clearTimeout(aiPollTimer);
        aiPollTimer = window.setTimeout(() => {
            aiPollTimer = null;
            void pollAiStatus();
        }, 1200);
    };

    const updateAiStatus = (message, isError = false, isProcessing = false) => {
        window.SM?.setExpenseAiBackgroundStatus?.(message, isError, isProcessing);
    };

    const pollAiStatus = async () => {
        if (aiPollingStopped || !aiStatusUrl) return;

        aiPollController?.abort();
        const controller = new AbortController();
        aiPollController = controller;

        try {
            const response = await fetch(aiStatusUrl, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
                signal: controller.signal,
            });
            if (controller.signal.aborted || aiPollingStopped) return;
            if (response.status === 404) {
                stopAiPolling();
                return;
            }
            if (!response.ok) throw new Error('The AI status could not be read.');

            const body = await response.json().catch(() => ({}));
            const status = String(body.status || 'queued');
            if (status === 'completed') {
                stopAiPolling();
                if (!window.SM?.applyExpenseAiBackgroundResult?.(body.result)) {
                    updateAiStatus('The AI result could not be applied. Complete the fields manually.', true);
                }
                return;
            }
            if (status === 'failed') {
                stopAiPolling();
                updateAiStatus(
                    body.message || 'The AI could not read this receipt. Complete the fields manually.',
                    true,
                );
                return;
            }

            updateAiStatus(
                status === 'processing' ? 'AI is reading this receipt in the background…' : 'AI is queued for this receipt…',
                false,
                true,
            );
            scheduleAiPoll();
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') return;
            if (aiPollingStopped) return;
            updateAiStatus('Waiting for the AI receipt result…', false, true);
            scheduleAiPoll();
        } finally {
            if (aiPollController === controller) aiPollController = null;
        }
    };

    const resetSkipButton = () => {
        if (!skipButton) return;
        skipButton.disabled = false;
        skipLabel?.classList.remove('hidden');
        skipLoading?.classList.add('hidden');
        skipLoading?.classList.remove('inline-flex');
    };

    const showFailure = (message) => {
        stopAiPolling();
        if (loadingText) loadingText.textContent = message;
        loading?.classList.remove('border-sky-200', 'bg-sky-50', 'text-sky-900');
        loading?.classList.add('border-red-200', 'bg-red-50', 'text-red-800');
        loading?.querySelector('i')?.classList.replace('fa-circle-notch', 'fa-circle-exclamation');
        loading?.querySelector('i')?.classList.remove('animate-spin');
        saveButton.disabled = false;
        resetSkipButton();
    };

    const confirmSkip = () => new Promise((resolve) => {
        if (typeof window.SM?.confirm !== 'function') {
            resolve(true);
            return;
        }

        window.SM.confirm(
            'Skip this receipt?',
            'This staged receipt will be discarded without creating an expense.',
            'Skip receipt',
            (confirmed) => resolve(Boolean(confirmed)),
        );
    });

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const csrfUrl = document.querySelector('[data-ai-csrf-url]')?.dataset.aiCsrfUrl || '';

    const refreshCsrfToken = async () => {
        if (!csrfUrl) throw new Error('Your session has expired. Reload the page and try again.');
        const response = await fetch(csrfUrl, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store',
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok || typeof body.token !== 'string' || body.token === '') {
            throw new Error('Your session has expired. Reload the page and try again.');
        }
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.content = body.token;
        form.querySelectorAll('input[name="_token"]').forEach((field) => { field.value = body.token; });
        return body.token;
    };

    const skipReceipt = async () => {
        if (!skipButton || !skipUrl || skipping) return;
        if (!await confirmSkip()) return;

        stopAiPolling();
        skipping = true;
        skipButton.disabled = true;
        skipLabel?.classList.add('hidden');
        skipLoading?.classList.remove('hidden');
        skipLoading?.classList.add('inline-flex');
        saveButton.disabled = true;

        try {
            let token = csrfToken();
            let response = await fetch(skipUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (response.status === 419) {
                token = await refreshCsrfToken();
                response = await fetch(skipUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
            }
            const body = await response.json().catch(() => ({}));
            if (!response.ok || !body.redirect) throw new Error(body.message || 'The receipt could not be skipped. Please try again.');
            window.location.assign(body.redirect);
        } catch (error) {
            skipping = false;
            aiPollingStopped = false;
            saveButton.disabled = false;
            resetSkipButton();
            window.SM?.alert?.('Could not skip receipt', error instanceof Error ? error.message : 'The receipt could not be skipped. Please try again.', 'error');
            scheduleAiPoll();
        }
    };

    skipButton?.addEventListener('click', () => { void skipReceipt(); });

    const loadReceipt = async () => {
        const response = await fetch(fileUrl, {
            credentials: 'same-origin',
            headers: { Accept: 'application/octet-stream', 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store',
        });
        if (!response.ok) throw new Error('The staged receipt could not be loaded. Please return to Expenses and import it again.');

        const blob = await response.blob();
        const file = new File(
            [blob],
            form.dataset.expenseImportFileName || 'receipt',
            { type: form.dataset.expenseImportFileType || blob.type || 'application/octet-stream', lastModified: Date.now() },
        );
        const transfer = new DataTransfer();
        transfer.items.add(file);
        receiptInput.files = transfer.files;
        receiptInput.dispatchEvent(new Event('change', { bubbles: true }));
        loading?.remove();
        saveButton.disabled = false;
        resetSkipButton();
        updateAiStatus('AI is queued for this receipt…', false, true);
        void pollAiStatus();
    };

    loadReceipt().catch((error) => {
        showFailure(error instanceof Error ? error.message : 'The staged receipt could not be loaded.');
    });
};

if (document.readyState === 'complete') {
    initialiseExpenseImportQueue();
} else {
    document.addEventListener('DOMContentLoaded', initialiseExpenseImportQueue, { once: true });
}
