<x-layout>
    <x-mast backRoute="admin.expense.index" backTitle="Expenses">{{ isset($expense) ? 'Edit' : 'Record' }} Expense</x-mast>

    @php
        $defaultPaidOn = isset($expense) && $expense->paid_on
            ? $expense->paid_on->format('Y-m-d')
            : now()->format('Y-m-d');
        $documentExists = isset($expense) && $expense->hasReceiptDocument();
        $documentViewUrl = isset($expense) && $expense->receipt_document_path
            ? route('admin.expense.document.view', $expense)
            : null;
        $documentName = isset($expense) ? (string) ($expense->receipt_document_name ?? '') : '';
    @endphp

    <x-container class="mt-4">
        <form id="expense-form" data-sm-file-upload-managed method="POST" enctype="multipart/form-data" action="{{ route('admin.expense.' . (isset($expense) ? 'update' : 'store'), $expense ?? []) }}">
            @csrf

            <x-ui.input
                label="Supplier"
                name="supplier"
                id="expense-supplier"
                value="{{ $expense->supplier ?? $supplierName ?? '' }}"
                required
                :suggestions="$supplierSuggestions ?? []"
                info="Start typing to choose an existing supplier or enter a new one."
            />
            <x-ui.input label="Description" name="description" id="expense-description" value="{{ $expense->description ?? '' }}" required />
            <x-ui.input
                label="Invoice / Receipt ID"
                name="invoice_id"
                value="{{ $expense->invoice_id ?? '' }}"
                required
                info="Supplier invoice or receipt reference used in BAS exports and document naming."
            />
            <div data-validation-field="paid_on" class="mb-4">
                <label for="expense-paid-on" class="flex text-sm pl-1 items-center">Expense Date</label>
                <input
                    id="expense-paid-on"
                    name="paid_on"
                    type="date"
                    value="{{ old('paid_on', $defaultPaidOn) }}"
                    class="disabled:bg-gray-100 bg-white block mt-1 px-2.5 pt-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border appearance-auto focus:outline-none focus:ring-0 focus:border-blue-600 {{ $errors->has('paid_on') ? 'border-red-600 ring-red-600 focus:border-red-600 focus:ring-red-600' : 'border-gray-300 focus:border-indigo-300 focus:ring-indigo-300' }}"
                    @if($errors->has('paid_on')) aria-invalid="true" aria-describedby="expense-paid-on-error" @endif
                    @if(!isset($expense))
                        data-ai-replace-default
                        data-ai-default-value="{{ $defaultPaidOn }}"
                    @endif
                />
                @if($errors->has('paid_on'))
                    <p data-validation-error id="expense-paid-on-error" role="alert" class="text-xs text-red-600 ml-2 mt-2">{{ $errors->first('paid_on') }}</p>
                @endif
            </div>

            <div class="grid gap-x-6 sm:grid-cols-2">
                <div class="flex-1">
                    <x-ui.input
                        type="number"
                        step="0.01"
                        min="0"
                        label="Total Amount"
                        labelInfo="(incl GST)"
                        name="total_amount"
                        id="expense-total-amount"
                        value="{{ $expense->total_amount ?? '' }}"
                        :moneyFormat="true"
                    />
                </div>
                <div class="flex-1">
                    <x-ui.input
                        type="number"
                        step="0.01"
                        min="0"
                        label="GST Amount"
                        name="gst_amount"
                        id="expense-gst-amount"
                        value="{{ $expense->gst_amount ?? '' }}"
                        :moneyFormat="true"
                    />
                </div>
            </div>

            <x-ui.file-upload label="Receipt Document" name="receipt_document_file" id="expense-receipt-file" />

            <div
                class="sm-ai-status-toast pointer-events-none fixed left-4 right-4 top-4 z-[140] mx-auto max-w-md -translate-y-full opacity-0 transition-all duration-300 ease-out"
                data-ai-widget
                data-ai-auto-file="#expense-receipt-file"
                data-ai-configured="{{ filled(config('services.openai.api_key')) ? 'true' : 'false' }}"
                data-ai-url="{{ route('admin.ai.expenses.extract.stream') }}"
                data-ai-csrf-url="{{ route('admin.ai.csrf-token') }}"
                data-ai-token="{{ csrf_token() }}"
                data-ai-stream="true"
                data-ai-file="#expense-receipt-file"
                data-ai-fill-scope="#expense-form"
                data-ai-fill-fields="supplier,description,invoice_id,paid_on,total_amount,gst_amount"
                aria-hidden="true"
            >
                <div data-ai-status class="overflow-hidden rounded-xl border border-sky-200 bg-white text-sm font-medium shadow-lg" role="status" aria-live="polite">
                    <div class="sm-ai-status-row relative flex min-h-14 items-center gap-3 px-4 py-3">
                        <span class="sm-ai-status-icon" aria-hidden="true">
                            <i data-ai-processing-icon class="fa-solid fa-wand-magic-sparkles"></i>
                            <i data-ai-complete-icon class="fa-solid fa-circle-check"></i>
                            <i data-ai-error-icon class="fa-solid fa-circle-exclamation"></i>
                        </span>
                        <span class="sm-ai-starfield" aria-hidden="true">
                            <i data-ai-star class="fa-solid fa-star sm-ai-star sm-ai-star-1"></i>
                            <i data-ai-star class="fa-solid fa-star sm-ai-star sm-ai-star-2"></i>
                            <i data-ai-star class="fa-solid fa-star sm-ai-star sm-ai-star-3"></i>
                            <i data-ai-star class="fa-solid fa-star sm-ai-star sm-ai-star-4"></i>
                            <i data-ai-star class="fa-solid fa-star sm-ai-star sm-ai-star-5"></i>
                            <i data-ai-star class="fa-solid fa-star sm-ai-star sm-ai-star-6"></i>
                        </span>
                        <span class="sm-ai-status-copy">
                            <span data-ai-status-text class="block">AI is reading the PDF…</span>
                            <span data-ai-status-detail class="mt-0.5 block text-xs font-normal text-slate-500" hidden>Filling blank fields; you can keep editing.</span>
                        </span>
                    </div>
                    <div data-ai-progress-track class="sm-ai-progress-track" role="progressbar" aria-label="Receipt processing" aria-valuetext="Processing" aria-hidden="true">
                        <span class="sm-ai-progress-bar"></span>
                    </div>
                </div>
            </div>

            <details class="sm-expense-receipt-details mb-6 rounded-xl border border-slate-200 bg-white p-4">
                <summary class="flex cursor-pointer list-none items-center justify-start gap-3 font-semibold">
                    <i data-expense-preview-chevron class="fa-solid fa-chevron-right text-sm text-slate-500 transition-transform" aria-hidden="true"></i>
                    <span>Receipt Preview</span>
                </summary>
                @if(isset($expense) && $expense->receipt_document_path && $documentExists)
                    <div class="mt-3 flex justify-end gap-2">
                            <x-ui.button
                                href="{{ route('admin.expense.document.view', $expense) }}"
                                target="_blank"
                                color="outline"
                                class="px-2"
                                title="View document"
                                aria-label="View document"
                            >
                                <i class="fa-solid fa-eye"></i>
                            </x-ui.button>
                            <x-ui.button
                                href="{{ route('admin.expense.document.download', $expense) }}"
                                color="outline"
                                class="px-2"
                                title="Download document"
                                aria-label="Download document"
                            >
                                <i class="fa-solid fa-download"></i>
                            </x-ui.button>
                            <x-ui.button
                                type="button"
                                color="danger-outline"
                                class="px-2"
                                title="Remove attachment"
                                aria-label="Remove attachment"
                                x-data
                                x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Remove attachment?', 'Are you sure you want to remove this attachment?', '{{ route('admin.expense.document.remove', $expense) }}')"
                            >
                                <i class="fa-solid fa-trash-can"></i>
                            </x-ui.button>
                    </div>
                @endif
                <div class="mt-3">
                <div id="expense-receipt-preview-wrap" class="{{ $documentViewUrl ? '' : 'hidden' }} border border-gray-300 rounded-lg overflow-hidden bg-gray-50">
                    <div id="expense-receipt-preview-empty" class="text-sm text-gray-500 p-4 {{ $documentViewUrl ? 'hidden' : '' }}">
                        No receipt selected yet.
                    </div>
                    <div id="expense-receipt-preview-loading" class="hidden p-4 border-b border-gray-200 bg-white">
                        <div class="inline-flex items-center gap-2 text-sm text-gray-600">
                            <i class="fa-solid fa-circle-notch animate-spin text-primary-color"></i>
                            <span>Loading preview...</span>
                        </div>
                        <div class="mt-2 h-1.5 w-full rounded-full bg-gray-200 overflow-hidden">
                            <div class="h-full w-2/3 bg-primary-color/70 animate-pulse rounded-full"></div>
                        </div>
                    </div>
                    <img id="expense-receipt-preview-image" class="hidden w-full max-h-128 object-contain bg-white" alt="Receipt preview" src="" />
                    <iframe id="expense-receipt-preview-frame" class="hidden w-full h-128 bg-white" title="Receipt preview"></iframe>
                </div>
                <div id="expense-receipt-preview-note" class="mt-2 hidden text-xs text-gray-500" aria-live="polite"></div>
                </div>
            </details>

            <x-finance.expense-allocation :expense="$expense ?? null" />

            <x-ui.editor-actions>
                @isset($expense)
                    <x-ui.button data-editor-delete type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete expense?', 'Are you sure you want to delete this expense?', '{{ route('admin.expense.destroy', $expense) }}')">Delete</x-ui.button>
                @endisset
                <x-ui.button type="submit" id="expense-save-button">
                    <span id="expense-save-label">Save</span>
                    <span id="expense-save-loading" class="hidden items-center gap-2">
                        <i class="fa-solid fa-circle-notch animate-spin"></i>
                        <span id="expense-save-loading-text">Saving...</span>
                    </span>
                </x-ui.button>
            </x-ui.editor-actions>
        </form>
    </x-container>
</x-layout>

<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    (function () {
        const totalInput = document.getElementById('expense-total-amount');
        const gstInput = document.getElementById('expense-gst-amount');
        const receiptInput = document.getElementById('expense-receipt-file');
        const expenseForm = document.getElementById('expense-form');
        const saveButton = document.getElementById('expense-save-button');
        const saveLabel = document.getElementById('expense-save-label');
        const saveLoading = document.getElementById('expense-save-loading');
        const saveLoadingText = document.getElementById('expense-save-loading-text');

        const previewWrap = document.getElementById('expense-receipt-preview-wrap');
        const previewEmpty = document.getElementById('expense-receipt-preview-empty');
        const previewLoading = document.getElementById('expense-receipt-preview-loading');
        const previewImage = document.getElementById('expense-receipt-preview-image');
        const previewFrame = document.getElementById('expense-receipt-preview-frame');
        const previewNote = document.getElementById('expense-receipt-preview-note');
        const defaultPreviewEmptyText = 'No receipt selected yet.';
        const missingPreviewNote = 'Receipt preview unavailable: the attachment file was not found.';
        const validationErrorsPresent = @json($errors->any());
        const droppedReceipt = @json(!isset($expense)) ? new URLSearchParams(window.location.search).get('receipt_draft') : null;
        const receiptDraftKey = droppedReceipt && /^[a-f0-9-]{36}$/i.test(droppedReceipt)
            ? `expense-drop:${droppedReceipt}`
            : `expense-receipt-draft:${window.location.pathname}:${receiptInput ? receiptInput.id : 'receipt'}`;
        const receiptDraftDbName = 'sm-file-drafts';
        const receiptDraftStoreName = 'drafts';
        let existingPreviewUrl = null;
        existingPreviewUrl = @js($documentViewUrl);
        let existingDocumentName = '';
        existingDocumentName = @js($documentName);
        const maxUploadMeta = document.querySelector('meta[name="max-upload-size"]');
        const maxUploadBytes = maxUploadMeta ? Number(maxUploadMeta.getAttribute('content')) : 0;
        let currentObjectUrl = null;
        let previewLoadToken = 0;

        const openReceiptDraftDb = () => {
            if (!window.indexedDB) {
                return Promise.resolve(null);
            }

            return new Promise((resolve, reject) => {
                const request = window.indexedDB.open(receiptDraftDbName, 1);

                request.onupgradeneeded = () => {
                    const db = request.result;
                    if (!db.objectStoreNames.contains(receiptDraftStoreName)) {
                        db.createObjectStore(receiptDraftStoreName);
                    }
                };

                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error || new Error('Unable to open draft storage.'));
            });
        };

        const saveReceiptDraft = async (file) => {
            if (!file) {
                return;
            }

            const db = await openReceiptDraftDb().catch(() => null);
            if (!db) {
                return;
            }

            try {
                await new Promise((resolve, reject) => {
                    const tx = db.transaction(receiptDraftStoreName, 'readwrite');
                    const store = tx.objectStore(receiptDraftStoreName);
                    store.put({
                        blob: file,
                        name: file.name,
                        type: file.type,
                        lastModified: file.lastModified,
                    }, receiptDraftKey);
                    tx.oncomplete = () => resolve();
                    tx.onerror = () => reject(tx.error || new Error('Unable to save draft file.'));
                    tx.onabort = () => reject(tx.error || new Error('Unable to save draft file.'));
                });
            } catch (error) {
                // Draft persistence is best-effort.
            } finally {
                db.close();
            }
        };

        const loadReceiptDraft = async () => {
            const db = await openReceiptDraftDb().catch(() => null);
            if (!db) {
                return null;
            }

            try {
                return await new Promise((resolve, reject) => {
                    const tx = db.transaction(receiptDraftStoreName, 'readonly');
                    const store = tx.objectStore(receiptDraftStoreName);
                    const request = store.get(receiptDraftKey);
                    request.onsuccess = () => resolve(request.result ?? null);
                    request.onerror = () => reject(request.error || new Error('Unable to load draft file.'));
                    tx.onerror = () => reject(tx.error || new Error('Unable to load draft file.'));
                    tx.onabort = () => reject(tx.error || new Error('Unable to load draft file.'));
                });
            } catch (error) {
                return null;
            } finally {
                db.close();
            }
        };

        const clearReceiptDraft = async () => {
            const db = await openReceiptDraftDb().catch(() => null);
            if (!db) {
                return;
            }

            try {
                await new Promise((resolve, reject) => {
                    const tx = db.transaction(receiptDraftStoreName, 'readwrite');
                    const store = tx.objectStore(receiptDraftStoreName);
                    store.delete(receiptDraftKey);
                    tx.oncomplete = () => resolve();
                    tx.onerror = () => reject(tx.error || new Error('Unable to clear draft file.'));
                    tx.onabort = () => reject(tx.error || new Error('Unable to clear draft file.'));
                });
            } catch (error) {
                // Draft persistence is best-effort.
            } finally {
                db.close();
            }
        };

        const restoreReceiptDraft = async () => {
            if ((!validationErrorsPresent && !droppedReceipt) || !receiptInput || receiptInput.disabled || receiptInput.readOnly) {
                return false;
            }

            const draft = await loadReceiptDraft();
            const draftBlob = draft && typeof draft === 'object' ? (draft.blob || draft) : null;
            if (!(draftBlob instanceof Blob)) {
                return false;
            }

            const draftName = typeof draft?.name === 'string' && draft.name !== ''
                ? draft.name
                : (receiptInput.getAttribute('name') || 'attachment');
            const draftType = typeof draft?.type === 'string' ? draft.type : draftBlob.type;
            const draftLastModified = typeof draft?.lastModified === 'number' ? draft.lastModified : Date.now();
            const restoredFile = draftBlob instanceof File
                ? draftBlob
                : new File([draftBlob], draftName, {
                    type: draftType || '',
                    lastModified: draftLastModified,
                });

            const transfer = new DataTransfer();
            transfer.items.add(restoredFile);
            receiptInput.files = transfer.files;
            receiptInput.dispatchEvent(new Event('change', { bubbles: true }));

            return true;
        };

        const initialiseReceiptPreview = async () => {
            if (!validationErrorsPresent && !droppedReceipt) {
                await clearReceiptDraft();
            }

            const restored = await restoreReceiptDraft();
            if (restored) {
                return;
            }

            if (existingPreviewUrl) {
                showPreviewFromUrl(existingPreviewUrl, '', existingDocumentName);
                return;
            }

            if (previewWrap && previewEmpty) {
                previewWrap.classList.add('hidden');
                previewEmpty.textContent = defaultPreviewEmptyText;
                previewEmpty.classList.remove('hidden');
                if (previewNote) {
                    previewNote.textContent = '';
                    previewNote.classList.add('hidden');
                }
            }
            if (droppedReceipt) {
                setPreviewNote('The dropped receipt could not be restored. Please attach it again before saving.');
            }
        };

        const updateGstFromTotal = () => {
            if (!totalInput || !gstInput) {
                return;
            }

            const total = parseFloat(totalInput.value);
            if (!Number.isFinite(total) || total < 0) {
                gstInput.value = '';
                gstInput.dispatchEvent(new Event('input', { bubbles: true }));
                return;
            }

            totalInput.value = total.toFixed(2);
            const gst = Math.round((total / 11) * 100) / 100;
            gstInput.value = gst.toFixed(2);
            gstInput.dispatchEvent(new Event('input', { bubbles: true }));
        };

        const resetPreviewVisibility = () => {
            if (!previewWrap || !previewEmpty || !previewImage || !previewFrame || !previewLoading || !previewNote) {
                return;
            }

            previewWrap.classList.remove('hidden');
            previewEmpty.classList.add('hidden');
            previewImage.classList.add('hidden');
            previewFrame.classList.add('hidden');
            previewLoading.classList.add('hidden');
            previewNote.classList.add('hidden');
        };

        const showPreviewLoading = () => {
            if (!previewLoading) {
                return;
            }

            previewLoading.classList.remove('hidden');
        };

        const hidePreviewLoading = () => {
            if (!previewLoading) {
                return;
            }

            previewLoading.classList.add('hidden');
        };

        const revokeObjectUrl = () => {
            if (!currentObjectUrl) {
                return;
            }

            URL.revokeObjectURL(currentObjectUrl);
            currentObjectUrl = null;
        };

        const clearPreview = () => {
            revokeObjectUrl();

            if (!previewWrap || !previewEmpty || !previewImage || !previewFrame || !previewNote) {
                return;
            }

            previewImage.src = '';
            previewFrame.src = 'about:blank';
            previewImage.classList.add('hidden');
            previewFrame.classList.add('hidden');
            previewEmpty.textContent = defaultPreviewEmptyText;
            previewNote.classList.add('hidden');
            hidePreviewLoading();
            previewWrap.classList.add('hidden');
            previewEmpty.classList.remove('hidden');
        };

        const appendPdfViewerHints = (url) => {
            if (!url) {
                return url;
            }

            const hashIndex = url.indexOf('#');
            const baseUrl = hashIndex === -1 ? url : url.slice(0, hashIndex);
            const existingFragment = hashIndex === -1 ? '' : url.slice(hashIndex + 1);
            const hints = '';
            const fragment = [existingFragment, hints].filter((item) => item !== '').join('&');

            return fragment === '' ? baseUrl : `${baseUrl}#${fragment}`;
        };

        const setPreviewNote = (message) => {
            if (!previewNote) {
                return;
            }

            if (!message) {
                previewNote.textContent = '';
                previewNote.classList.add('hidden');
                return;
            }

            previewNote.textContent = message;
            previewNote.classList.remove('hidden');
        };

        const canVerifyRemotePreview = (url) => typeof url === 'string'
            && url !== ''
            && !url.startsWith('blob:')
            && !url.startsWith('data:');

        const verifyPreviewUrl = async (url, token) => {
            if (!canVerifyRemotePreview(url)) {
                return true;
            }

            try {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    method: 'HEAD',
                });

                return token === previewLoadToken && response.ok;
            } catch (error) {
                return false;
            }
        };

        const resizeSafariPdfPreview = () => {
            if (!previewFrame) {
                return;
            }

            try {
                console.log('resizeSafariPdfPreview');
                const frameDocument = previewFrame.contentDocument || (previewFrame.contentWindow ? previewFrame.contentWindow.document : null);
                if (!frameDocument) {
                    console.log('resizeSafariPdfPreview: no frame document available');
                    return;
                }

                const targets = Array.from(frameDocument.querySelectorAll('embed, img'));
                if (targets.length === 0) {
                    console.log('resizeSafariPdfPreview: no embeds or images available');
                    return;
                }

                targets.forEach((target) => {
                    console.log('resizeSafariPdfPreview', target);
                    target.style.width = '100%';
                    target.style.maxWidth = '100%';
                    target.style.display = 'block';
                });
            } catch (error) {
                // Some browsers do not expose the embedded PDF document. That is fine.
                console.log('resizeSafariPdfPreview', error);
            }
        };

        const showPreviewUnavailable = (message) => {
            hidePreviewLoading();
            setPreviewNote('');
            previewImage.classList.add('hidden');
            previewFrame.classList.add('hidden');
            previewWrap.classList.remove('hidden');
            previewEmpty.textContent = message;
            previewEmpty.classList.remove('hidden');
        };

        const showPreviewFromUrl = async (url, mimeType, filename) => {
            if (!url || !previewImage || !previewFrame) {
                return;
            }

            const token = ++previewLoadToken;
            resetPreviewVisibility();
            showPreviewLoading();
            previewImage.src = '';
            previewFrame.src = 'about:blank';

            const fileNameLower = (filename || '').toLowerCase();
            const isImage = (mimeType && mimeType.startsWith('image/'))
                || fileNameLower.endsWith('.jpg')
                || fileNameLower.endsWith('.jpeg')
                || fileNameLower.endsWith('.png')
                || fileNameLower.endsWith('.gif')
                || fileNameLower.endsWith('.webp');
            const isPdf = (mimeType && mimeType === 'application/pdf')
                || fileNameLower.endsWith('.pdf');

            if (isImage) {
                previewImage.onload = () => {
                    if (token !== previewLoadToken) {
                        return;
                    }

                    hidePreviewLoading();
                    previewImage.classList.remove('hidden');
                };
                previewImage.onerror = () => {
                    if (token !== previewLoadToken) {
                        return;
                    }

                    clearPreview();
                };
                previewImage.src = url;
                return;
            }

            if (isPdf && !await verifyPreviewUrl(url, token)) {
                if (token === previewLoadToken) {
                    showPreviewUnavailable(missingPreviewNote);
                }

                return;
            }

            previewFrame.onload = () => {
                if (token !== previewLoadToken) {
                    return;
                }

                requestAnimationFrame(() => {
                    if (token !== previewLoadToken) {
                        return;
                    }

                    if (isPdf) {
                        resizeSafariPdfPreview();
                    }

                    hidePreviewLoading();
                    previewFrame.classList.remove('hidden');
                });
            };
            previewFrame.src = isPdf ? appendPdfViewerHints(url) : url;
        };

        if (totalInput && gstInput) {
            totalInput.addEventListener('blur', updateGstFromTotal);
            totalInput.addEventListener('change', updateGstFromTotal);
        }

        if (receiptInput) {
            receiptInput.addEventListener('change', (event) => {
                const file = event.target.files && event.target.files[0] ? event.target.files[0] : null;
                if (!file) {
                    if (existingPreviewUrl) {
                        showPreviewFromUrl(existingPreviewUrl, '', existingDocumentName);
                    } else {
                        clearPreview();
                    }
                    return;
                }

                if (Number.isFinite(maxUploadBytes) && maxUploadBytes > 0 && file.size > maxUploadBytes) {
                    if (existingPreviewUrl) {
                        showPreviewFromUrl(existingPreviewUrl, '', existingDocumentName);
                    } else {
                        clearPreview();
                    }
                    return;
                }

                revokeObjectUrl();
                currentObjectUrl = URL.createObjectURL(file);
                const fileUrl = currentObjectUrl;
                showPreviewFromUrl(fileUrl, file.type, file.name);
            });
        }

        if (receiptInput) {
            const persistCurrentReceiptDraft = async () => {
                const file = receiptInput.files && receiptInput.files[0] ? receiptInput.files[0] : null;
                if (!file) {
                    await clearReceiptDraft();
                    return;
                }

                await saveReceiptDraft(file);
            };

            const attachReceiptDraftPersistence = () => {
                receiptInput.addEventListener('change', () => {
                    persistCurrentReceiptDraft().catch(() => {});
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', attachReceiptDraftPersistence, { once: true });
            } else {
                attachReceiptDraftPersistence();
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                initialiseReceiptPreview().catch(() => {});
            }, { once: true });
        } else {
            initialiseReceiptPreview().catch(() => {});
        }

        if (expenseForm && saveButton && saveLabel && saveLoading && saveLoadingText) {
            const resetSaveButton = () => {
                saveButton.disabled = false;
                saveLabel.classList.remove('hidden');
                saveLoading.classList.add('hidden');
                saveLoading.classList.remove('inline-flex');
            };

            let saving = false;
            const invalidControls = new Map();
            const clearSaveErrors = () => {
                expenseForm.querySelectorAll('[data-validation-error]').forEach((error) => {
                    error.textContent = '';
                    error.hidden = true;
                });
                invalidControls.forEach((original, control) => {
                    original.visual.classList.remove('border-red-600', 'ring-red-600', 'ring-1');
                    for (const [attribute, value] of Object.entries(original.attributes)) {
                        if (value === null) control.removeAttribute(attribute);
                        else control.setAttribute(attribute, value);
                    }
                });
                invalidControls.clear();
            };
            const showSaveErrors = (messages, errors = {}) => {
                for (const [name, value] of Object.entries(errors)) {
                    const fieldName = name.replace(/\.([^.[\]]+)/g, '[$1]');
                    const controls = Array.from(expenseForm.elements).filter((control) => control.name === fieldName);
                    const wrapper = controls[0]?.closest('[data-validation-field]')
                        || Array.from(expenseForm.querySelectorAll('[data-validation-field]')).find((field) => field.dataset.validationField === name);
                    const error = wrapper?.querySelector('[data-validation-error]');
                    if (!error) continue;
                    error.textContent = (Array.isArray(value) ? value : [value]).filter((message) => typeof message === 'string').join(' ');
                    error.hidden = false;
                    for (const control of controls) {
                        const visual = control.type === 'file' ? wrapper.querySelector('label[for]') || control : control;
                        invalidControls.set(control, {
                            visual,
                            attributes: {
                                'aria-invalid': control.getAttribute('aria-invalid'),
                                'aria-describedby': control.getAttribute('aria-describedby'),
                            },
                        });
                        control.setAttribute('aria-invalid', 'true');
                        control.setAttribute('aria-describedby', [control.getAttribute('aria-describedby'), error.id].filter(Boolean).join(' '));
                        visual.classList.add('border-red-600', 'ring-red-600', 'ring-1');
                    }
                }
                SM.alert('The expense could not be saved', messages.join('\n'), 'error');
            };

            expenseForm.addEventListener('submit', async (event) => {
                if (saving) {
                    event.preventDefault();
                    return;
                }
                clearSaveErrors();

                const hasNewUpload = !!(receiptInput && receiptInput.files && receiptInput.files.length > 0);
                const upload = hasNewUpload ? receiptInput.files[0] : null;

                // Recheck here because files dragged from iCloud/Files on iPad
                // can report their final size after the initial change event.
                if (upload && Number.isFinite(maxUploadBytes) && maxUploadBytes > 0 && upload.size > maxUploadBytes) {
                    event.preventDefault();
                    receiptInput.setCustomValidity(`File is too large. Maximum upload size is ${window.SM && typeof window.SM.bytesToString === 'function' ? window.SM.bytesToString(maxUploadBytes) : `${Math.floor(maxUploadBytes / 1024 / 1024)} MB`}.`);
                    receiptInput.reportValidity();
                    showSaveErrors([receiptInput.validationMessage], { receipt_document_file: [receiptInput.validationMessage] });
                    return;
                }

                if (hasNewUpload) {
                    event.preventDefault();
                }

                saveButton.disabled = true;
                saveLabel.classList.add('hidden');
                saveLoading.classList.remove('hidden');
                saveLoading.classList.add('inline-flex');
                saveLoadingText.textContent = hasNewUpload ? 'Uploading...' : 'Saving...';

                if (!hasNewUpload) {
                    return;
                }

                saving = true;
                receiptInput.dispatchEvent(new CustomEvent('sm:file-upload-state', { detail: { uploading: true } }));
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                let csrfToken = csrfMeta?.content || '';
                const csrfRefreshUrl = document.querySelector('[data-ai-csrf-url]')?.dataset.aiCsrfUrl || '';

                const updateCsrfToken = (token) => {
                    csrfToken = token;
                    if (csrfMeta) csrfMeta.content = token;
                    expenseForm.querySelectorAll('input[name="_token"]').forEach((field) => { field.value = token; });
                    document.querySelectorAll('[data-ai-token]').forEach((element) => { element.dataset.aiToken = token; });
                };

                const refreshCsrfToken = async () => {
                    if (!csrfRefreshUrl) throw new Error('Your session has expired. Reload the page and try again.');

                    const tokenResponse = await fetch(csrfRefreshUrl, {
                        method: 'GET',
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        cache: 'no-store',
                    });
                    const tokenPayload = await tokenResponse.json().catch(() => ({}));
                    if (!tokenResponse.ok || typeof tokenPayload.token !== 'string' || tokenPayload.token === '') {
                        throw new Error(tokenResponse.redirected || tokenResponse.status === 401
                            ? 'Your session has expired. Reload the page and try again.'
                            : 'Could not refresh your session. Reload the page and try again.');
                    }

                    updateCsrfToken(tokenPayload.token);
                    return tokenPayload.token;
                };

                try {
                    const formData = new FormData(expenseForm);
                    // Safari can preview an IndexedDB-restored File but fail to encode
                    // it in multipart FormData. Materialize bytes before every upload.
                    let uploadBytes;
                    try {
                        uploadBytes = await upload.arrayBuffer();
                    } catch (error) {
                        showSaveErrors(['The receipt could not be read. Please attach it again.'], {
                            receipt_document_file: ['The receipt could not be read. Please attach it again.'],
                        });
                        return;
                    }
                    const materializedName = /^cid:/i.test(upload.name || '')
                        ? upload.name.replace(/^cid:/i, '').replace(/[<>]/g, '') || 'attachment.pdf'
                        : upload.name;
                    const materializedUpload = new File([uploadBytes], materializedName, {
                        type: upload.type || 'application/octet-stream',
                        lastModified: upload.lastModified || Date.now(),
                    });
                    formData.set(receiptInput.name, materializedUpload, materializedName);

                    const sendExpense = (token) => fetch(expenseForm.action, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                    });
                    let response = await sendExpense(csrfToken);
                    if (response.status === 419) {
                        const refreshedToken = await refreshCsrfToken();
                        formData.set('_token', refreshedToken);
                        response = await sendExpense(refreshedToken);
                    }
                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        const messages = payload.errors && typeof payload.errors === 'object'
                            ? Object.values(payload.errors).flat().filter((message) => typeof message === 'string' && message.trim())
                            : [];
                        const fallbackMessage = response.status === 419
                            ? 'Your session has expired. Reload the page and try again.'
                            : payload.message || 'Unable to save the expense. Please try again.';
                        showSaveErrors(messages.length ? messages : [fallbackMessage], payload.errors || {});
                        return;
                    }

                    await clearReceiptDraft();
                    window.location.assign(payload.redirect || expenseForm.action);
                } catch (error) {
                    showSaveErrors([error instanceof Error ? error.message : 'Unable to save the expense. Please try again.']);
                } finally {
                    saving = false;
                    resetSaveButton();
                    receiptInput.dispatchEvent(new CustomEvent('sm:file-upload-state', { detail: { uploading: false } }));
                }
            });
        }
    })();
</script>
