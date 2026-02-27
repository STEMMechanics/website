<x-layout>
    <x-mast backRoute="admin.expense.index" backTitle="Expenses">{{ isset($expense) ? 'Edit' : 'Record' }} Expense</x-mast>

    @php
        $defaultPaidOn = isset($expense) && $expense->paid_on
            ? $expense->paid_on->format('Y-m-d')
            : now()->format('Y-m-d');
        $documentViewUrl = isset($expense) && $expense->receipt_document_path
            ? route('admin.expense.document.view', $expense)
            : null;
        $documentName = isset($expense) ? (string) ($expense->receipt_document_name ?? '') : '';
    @endphp

    <x-container class="mt-4">
        <form id="expense-form" method="POST" enctype="multipart/form-data" action="{{ route('admin.expense.' . (isset($expense) ? 'update' : 'store'), $expense ?? []) }}">
            @isset($expense)
                @method('PUT')
            @endisset
            @csrf

            <x-ui.input
                label="Supplier"
                name="supplier"
                value="{{ $expense->supplier ?? '' }}"
                :suggestions="$supplierSuggestions ?? []"
                info="Start typing to choose an existing supplier or enter a new one."
            />
            <x-ui.input label="Description" name="description" value="{{ $expense->description ?? '' }}" />
            <x-ui.input
                label="Invoice / Receipt ID"
                name="invoice_id"
                value="{{ $expense->invoice_id ?? '' }}"
                info="Optional supplier invoice or receipt reference used in BAS exports and document naming."
            />
            <x-ui.input type="date" label="Expense Date" name="paid_on" id="expense-paid-on" value="{{ $defaultPaidOn }}" />

            <div class="flex gap-8">
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

            <div class="mb-6">
                <div class="font-semibold mb-2">Receipt Preview</div>
                <div id="expense-receipt-preview-empty" class="text-sm text-gray-500 {{ $documentViewUrl ? 'hidden' : '' }}">
                    No receipt selected yet.
                </div>
                <div id="expense-receipt-preview-wrap" class="{{ $documentViewUrl ? '' : 'hidden' }} border border-gray-300 rounded-lg overflow-hidden bg-gray-50">
                    <div id="expense-receipt-preview-loading" class="hidden p-4 border-b border-gray-200 bg-white">
                        <div class="inline-flex items-center gap-2 text-sm text-gray-600">
                            <i class="fa-solid fa-circle-notch animate-spin text-primary-color"></i>
                            <span>Loading preview...</span>
                        </div>
                        <div class="mt-2 h-1.5 w-full rounded-full bg-gray-200 overflow-hidden">
                            <div class="h-full w-2/3 bg-primary-color/70 animate-pulse rounded-full"></div>
                        </div>
                    </div>
                    <img id="expense-receipt-preview-image" class="hidden w-full max-h-[32rem] object-contain bg-white" alt="Receipt preview" src="" />
                    <iframe id="expense-receipt-preview-frame" class="hidden w-full h-[32rem] bg-white" title="Receipt preview"></iframe>
                </div>
            </div>

            @if(isset($expense) && $expense->receipt_document_path)
                <div class="mb-6 text-sm">
                    <div class="font-semibold">Current document</div>
                    <div class="flex gap-3 mt-1">
                        <a class="text-primary-color hover:underline" href="{{ route('admin.expense.document.view', $expense) }}" target="_blank">View</a>
                        <a class="text-primary-color hover:underline" href="{{ route('admin.expense.document.download', $expense) }}">Download</a>
                        <a href="#" class="text-red-600 hover:underline" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Remove attachment?', 'Are you sure you want to remove this attachment?', '{{ route('admin.expense.document.remove', $expense) }}')">Remove existing attachment</a>
                    </div>
                </div>
            @endif

            <div class="flex justify-end mt-8 gap-4">
                @isset($expense)
                    <x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete expense?', 'Are you sure you want to delete this expense?', '{{ route('admin.expense.destroy', $expense) }}')">Delete</x-ui.button>
                @endisset
                <x-ui.button type="submit" id="expense-save-button">
                    <span id="expense-save-label">Save</span>
                    <span id="expense-save-loading" class="hidden items-center gap-2">
                        <i class="fa-solid fa-circle-notch animate-spin"></i>
                        <span id="expense-save-loading-text">Saving...</span>
                    </span>
                </x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>

<script>
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
        let existingPreviewUrl = null;
        existingPreviewUrl = @js($documentViewUrl);
        let existingDocumentName = '';
        existingDocumentName = @js($documentName);
        const maxUploadMeta = document.querySelector('meta[name="max-upload-size"]');
        const maxUploadBytes = maxUploadMeta ? Number(maxUploadMeta.getAttribute('content')) : 0;
        let currentObjectUrl = null;

        const updateGstFromTotal = () => {
            if (!totalInput || !gstInput) {
                return;
            }

            const total = parseFloat(totalInput.value);
            if (!Number.isFinite(total) || total < 0) {
                gstInput.value = '';
                return;
            }

            totalInput.value = total.toFixed(2);
            const gst = Math.round((total / 11) * 100) / 100;
            gstInput.value = gst.toFixed(2);
        };

        const resetPreviewVisibility = () => {
            if (!previewWrap || !previewEmpty || !previewImage || !previewFrame || !previewLoading) {
                return;
            }

            previewWrap.classList.remove('hidden');
            previewEmpty.classList.add('hidden');
            previewImage.classList.add('hidden');
            previewFrame.classList.add('hidden');
            previewLoading.classList.add('hidden');
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

            if (!previewWrap || !previewEmpty || !previewImage || !previewFrame) {
                return;
            }

            previewImage.src = '';
            previewFrame.src = 'about:blank';
            previewImage.classList.add('hidden');
            previewFrame.classList.add('hidden');
            hidePreviewLoading();
            previewWrap.classList.add('hidden');
            previewEmpty.classList.remove('hidden');
        };

        const showPreviewFromUrl = (url, mimeType, filename) => {
            if (!url || !previewImage || !previewFrame) {
                return;
            }

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

            if (isImage) {
                previewImage.onload = () => {
                    hidePreviewLoading();
                    previewImage.classList.remove('hidden');
                };
                previewImage.onerror = () => {
                    clearPreview();
                };
                previewImage.src = url;
                return;
            }

            previewFrame.onload = () => {
                hidePreviewLoading();
                previewFrame.classList.remove('hidden');
            };
            previewFrame.src = url;
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

        if (existingPreviewUrl) {
            showPreviewFromUrl(existingPreviewUrl, '', existingDocumentName);
        } else if (previewWrap && previewEmpty) {
            previewWrap.classList.add('hidden');
            previewEmpty.classList.remove('hidden');
        }

        if (expenseForm && saveButton && saveLabel && saveLoading && saveLoadingText) {
            expenseForm.addEventListener('submit', () => {
                const hasNewUpload = !!(receiptInput && receiptInput.files && receiptInput.files.length > 0);
                saveButton.disabled = true;
                saveLabel.classList.add('hidden');
                saveLoading.classList.remove('hidden');
                saveLoading.classList.add('inline-flex');
                saveLoadingText.textContent = hasNewUpload ? 'Uploading...' : 'Saving...';
            });
        }
    })();
</script>
