<x-layout>
    <x-mast>Site Options</x-mast>

    <x-container>
        <div
            id="site-options-app"
            data-csrf="{{ csrf_token() }}"
            data-reset-all-url="{{ route('admin.site_option.reset-defaults') }}"
            data-update-url-template="{{ route('admin.site_option.update', ['siteOption' => '__ID__']) }}"
            data-reset-url-template="{{ route('admin.site_option.reset-default', ['siteOption' => '__ID__']) }}"
        >
            <x-ui.toolbar>
                <x-slot:left>
                    <x-ui.button href="{{ route('admin.site_option.create') }}">Create</x-ui.button>
                    <x-ui.button type="button" color="outline" class="ml-2" id="site-options-reset-all-button">Reset All Defaults</x-ui.button>
                </x-slot:left>
                <x-slot:right>
                    <x-ui.search name="search" label="Search" />
                </x-slot:right>
            </x-ui.toolbar>

            @if($siteOptions->isEmpty())
            <x-none-found item="site options" search="{{ request()->get('search') }}" />
            @else
            <x-ui.table>
                <x-slot:header>
                    <th>Name</th>
                    <th>Value</th>
                    <th>Actions</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach($siteOptions as $siteOption)
                    @php
                    $isSecret = \App\Models\SiteOption::isSecret((string) $siteOption->name);
                    $valueRaw = (string) ($siteOption->value ?? '');
                    $valuePlain = str_replace(["\r\n", "\r", "\n"], ' | ', $valueRaw);
                    $valuePreview = $isSecret && $valueRaw !== '' ? '••••••••' : \Illuminate\Support\Str::limit($valuePlain, 220);
                    $hasDefault = \App\Models\SiteOption::hasDefault((string) $siteOption->name);
                    $defaultValue = $hasDefault ? (string) (\App\Models\SiteOption::defaultValue((string) $siteOption->name) ?? '') : '';
                    $defaultDescription = $hasDefault ? (string) (\App\Models\SiteOption::defaultDescription((string) $siteOption->name) ?? '') : '';
                    $valueRawEncoded = base64_encode($isSecret ? '' : $valueRaw);
                    $defaultValueEncoded = base64_encode($isSecret ? '' : $defaultValue);
                    $inputType = \App\Models\SiteOption::inputType((string) $siteOption->name);
                    @endphp
                    <tr
                        id="site-option-row-{{ $siteOption->id }}"
                        data-option-id="{{ $siteOption->id }}"
                        data-option-name="{{ $siteOption->name }}"
                        data-option-value-base64="{{ $valueRawEncoded }}"
                        data-option-default-base64="{{ $defaultValueEncoded }}"
                        data-option-input-type="{{ $inputType }}"
                        data-option-is-secret="{{ $isSecret ? '1' : '0' }}"
                        data-option-has-value="{{ $valueRaw !== '' ? '1' : '0' }}"
                    >
                        <td class="whitespace-nowrap!">
                            <div>{{ $siteOption->name }}</div>
                            @if($defaultDescription !== '')
                                <div class="mt-1 whitespace-normal text-xs text-gray-500">{{ $defaultDescription }}</div>
                            @endif
                        </td>
                        <td class="text-left">
                            <div
                                id="site-option-value-{{ $siteOption->id }}"
                                class="site-option-value-preview"
                                title="{{ $isSecret && $valueRaw !== '' ? 'Stored secret value' : $valueRaw }}"
                            >
                                {{ $valuePreview !== '' ? $valuePreview : '-' }}
                            </div>
                        </td>
                        <td>
                            <div class="flex justify-center gap-3">
                                <button
                                    type="button"
                                    class="hover:text-primary-color"
                                    title="Edit value"
                                    data-edit-option="{{ $siteOption->id }}"
                                >
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                @if($hasDefault)
                                    <button
                                        type="button"
                                        class="hover:text-amber-600"
                                        title="Restore default"
                                        data-reset-option="{{ $siteOption->id }}"
                                    >
                                        <i class="fa-solid fa-rotate-left"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>

            {{ $siteOptions->appends(request()->query())->links() }}
            @endif

            <div
                id="site-option-modal"
                class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4"
                aria-hidden="true"
            >
                <div class="w-full max-w-3xl rounded-xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                        <div>
                            <div class="text-lg font-semibold">Edit Site Option</div>
                            <div id="site-option-modal-name" class="mt-1 text-sm text-gray-600"></div>
                        </div>
                        <button type="button" class="text-gray-500 transition hover:text-gray-900" id="site-option-modal-close" title="Close">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>
                    <div class="p-5">
                        <label for="site-option-modal-value" class="mb-2 block text-sm font-medium text-gray-700">Value</label>
                        <textarea
                            id="site-option-modal-value"
                            class="min-h-[20rem] w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0"
                        ></textarea>
                        <input
                            id="site-option-modal-number"
                            type="number"
                            min="1"
                            max="240"
                            step="1"
                            class="hidden w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0"
                        />
                        <select
                            id="site-option-modal-boolean"
                            class="hidden w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0"
                        >
                            <option value="1">Enabled</option>
                            <option value="0">Disabled</option>
                        </select>
                        <input
                            id="site-option-modal-secret"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Leave blank to keep the stored value"
                            class="hidden w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-0"
                        />
                        <p id="site-option-modal-secret-help" class="mt-2 hidden text-xs text-gray-500">Secret values are not sent to the browser. Enter a new value only when you want to replace the stored secret.</p>
                        <div id="site-option-modal-media-field" class="hidden">
                            <x-ui.media
                                label="Image"
                                name="site-option-modal-media"
                                allow_uploads="true"
                                info="Select or upload an image. Existing public paths and full URLs are preserved if already set."
                            />
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 border-t border-gray-200 px-5 py-4">
                        <x-ui.button type="button" color="outline" id="site-option-modal-cancel">Cancel</x-ui.button>
                        <x-ui.button type="button" id="site-option-modal-save">Save</x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </x-container>
</x-layout>

<style>
    .site-option-value-preview {
        overflow: hidden;
        text-overflow: ellipsis;
    }
</style>

<script>
    (() => {
        const root = document.getElementById('site-options-app');
        if (!root) {
            return;
        }

        const csrf = String(root.dataset.csrf || '');
        const updateUrlTemplate = String(root.dataset.updateUrlTemplate || '');
        const resetUrlTemplate = String(root.dataset.resetUrlTemplate || '');
        const resetAllUrl = String(root.dataset.resetAllUrl || '');

        const modal = document.getElementById('site-option-modal');
        const modalName = document.getElementById('site-option-modal-name');
        const modalValue = document.getElementById('site-option-modal-value');
        const modalNumber = document.getElementById('site-option-modal-number');
        const modalBoolean = document.getElementById('site-option-modal-boolean');
        const modalSecret = document.getElementById('site-option-modal-secret');
        const modalSecretHelp = document.getElementById('site-option-modal-secret-help');
        const modalMediaField = document.getElementById('site-option-modal-media-field');
        const modalMedia = document.getElementById('site-option-modal-media');
        const modalClose = document.getElementById('site-option-modal-close');
        const modalCancel = document.getElementById('site-option-modal-cancel');
        const modalSave = document.getElementById('site-option-modal-save');
        const resetAllButton = document.getElementById('site-options-reset-all-button');
        let currentOptionId = null;

        const previewText = (value) => {
            const compact = String(value || '').replace(/\r\n|\r|\n/g, ' | ').trim();
            if (compact === '') {
                return '-';
            }
            return compact.length > 220 ? compact.slice(0, 217) + '...' : compact;
        };

        const decodeBase64Utf8 = (value) => {
            if (!value) {
                return '';
            }

            try {
                const binary = atob(String(value));
                const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0));
                return new TextDecoder().decode(bytes);
            } catch (error) {
                return '';
            }
        };

        const encodeBase64Utf8 = (value) => {
            try {
                const bytes = new TextEncoder().encode(String(value || ''));
                let binary = '';
                bytes.forEach((byte) => {
                    binary += String.fromCharCode(byte);
                });
                return btoa(binary);
            } catch (error) {
                return '';
            }
        };

        const fetchJson = async (url, options = {}) => {
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                    ...(options.headers || {}),
                },
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || payload.success === false) {
                const error = new Error(payload.message || 'Request failed.');
                error.payload = payload;
                error.status = response.status;
                throw error;
            }
            return payload;
        };

        const escapeHtml = (value) => {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        const rowForId = (id) => document.getElementById('site-option-row-' + id);

        const updateRow = (option) => {
            if (!option || !option.id) {
                return;
            }

            const row = rowForId(option.id);
            if (!row) {
                return;
            }

            const isSecret = option.is_secret === true || row.dataset.optionIsSecret === '1';
            row.dataset.optionIsSecret = isSecret ? '1' : '0';
            row.dataset.optionHasValue = option.has_value === true ? '1' : (String(option.value || '').trim() === '' ? '0' : '1');
            row.dataset.optionValueBase64 = isSecret ? '' : encodeBase64Utf8(String(option.value || ''));
            const valueCell = document.getElementById('site-option-value-' + option.id);
            if (!valueCell) {
                return;
            }

            const hasValue = row.dataset.optionHasValue === '1';
            valueCell.title = isSecret && hasValue ? 'Stored secret value' : String(option.value || '');
            valueCell.textContent = isSecret && hasValue ? '••••••••' : previewText(option.value);
            valueCell.classList.toggle('is-empty', !hasValue);
        };

        const openModal = (id) => {
            const row = rowForId(id);
            if (!row || !modal || !modalName || !modalValue || !modalNumber || !modalBoolean || !modalSecret || !modalSecretHelp || !modalMediaField || !modalMedia) {
                return;
            }

            currentOptionId = id;
            modalName.textContent = String(row.dataset.optionName || '');
            const inputType = String(row.dataset.optionInputType || 'textarea');
            const decodedValue = decodeBase64Utf8(row.dataset.optionValueBase64 || '');
            modalValue.value = decodedValue;
            modalNumber.value = decodedValue;
            modalBoolean.value = decodedValue === '0' ? '0' : '1';
            modalValue.classList.toggle('hidden', inputType !== 'textarea');
            modalNumber.classList.toggle('hidden', inputType !== 'number');
            modalBoolean.classList.toggle('hidden', inputType !== 'boolean');
            modalSecret.value = '';
            modalSecret.classList.toggle('hidden', inputType !== 'secret');
            modalSecretHelp.classList.toggle('hidden', inputType !== 'secret');
            modalMediaField.classList.toggle('hidden', inputType !== 'media');
            modalMedia.value = decodedValue;
            if (inputType === 'media' && typeof resetMediaPreview === 'function') {
                resetMediaPreview('site-option-modal-media');
            }
            if (inputType === 'media' && decodedValue !== '' && (/^https?:\/\//i.test(decodedValue) || decodedValue.startsWith('/')) && typeof setMediaPreviewSource === 'function') {
                setMediaPreviewSource('site-option-modal-media', decodedValue);
                if (typeof syncMediaClearButton === 'function') {
                    syncMediaClearButton('site-option-modal-media');
                }
            } else if (inputType === 'media' && typeof updateMedia === 'function') {
                updateMedia('site-option-modal-media', decodedValue);
            }
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            modal.setAttribute('aria-hidden', 'false');
            setTimeout(() => {
                if (inputType === 'number') {
                    modalNumber.focus();
                    return;
                }
                if (inputType === 'boolean') {
                    modalBoolean.focus();
                    return;
                }
                if (inputType === 'secret') {
                    modalSecret.focus();
                    return;
                }
                if (inputType === 'media') {
                    modalMedia.focus();
                    return;
                }
                modalValue.focus();
            }, 0);
        };

        const closeModal = () => {
            if (!modal || !modalValue || !modalNumber || !modalBoolean || !modalSecret || !modalSecretHelp || !modalMediaField || !modalMedia) {
                return;
            }

            currentOptionId = null;
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            modal.setAttribute('aria-hidden', 'true');
            modalValue.value = '';
            modalNumber.value = '';
            modalBoolean.value = '1';
            modalSecret.value = '';
            modalValue.classList.remove('hidden');
            modalNumber.classList.add('hidden');
            modalBoolean.classList.add('hidden');
            modalSecret.classList.add('hidden');
            modalSecretHelp.classList.add('hidden');
            modalMedia.value = '';
            modalMediaField.classList.add('hidden');
            if (typeof resetMediaPreview === 'function') {
                resetMediaPreview('site-option-modal-media');
            }
        };

        const confirmDialog = (title, html, confirmButtonText, confirmButtonColor) => {
            if (!window.SM || typeof window.SM.notice !== 'function') {
                return Promise.resolve({ isConfirmed: false, isDismissed: true });
            }

            return window.SM.notice(title, html, {
                type: 'warning',
                showCancelButton: true,
                confirmButtonText,
                confirmButtonColor,
                cancelButtonText: 'Cancel',
            });
        };

        root.addEventListener('click', async (event) => {
            const editButton = event.target.closest('[data-edit-option]');
            if (editButton) {
                openModal(Number(editButton.getAttribute('data-edit-option')));
                return;
            }

            const resetButton = event.target.closest('[data-reset-option]');
            if (resetButton) {
                const optionId = Number(resetButton.getAttribute('data-reset-option'));
                const row = rowForId(optionId);
                if (!row) {
                    return;
                }

                const result = await confirmDialog(
                    'Restore default value?',
                    'This will replace the current value for <strong>' + String(row.dataset.optionName || '') + '</strong> with its default value.',
                    'Restore Default',
                    '#b45309'
                );

                if (!result || result.isConfirmed !== true) {
                    return;
                }

                try {
                    const payload = await fetchJson(resetUrlTemplate.replace('__ID__', String(optionId)), {
                        method: 'POST',
                    });
                    updateRow(payload.option || null);
                    if (window.SM && typeof window.SM.alert === 'function') {
                        window.SM.alert('Default restored', 'The site option was restored to its default value.', 'success');
                    }
                } catch (error) {
                    if (window.SM && typeof window.SM.notice === 'function') {
                        window.SM.notice('Restore failed', error.message || 'Could not restore the default value.', 'danger');
                    }
                }
            }

        });

        if (modalSave) {
            modalSave.addEventListener('click', async () => {
                if (!currentOptionId || !modalValue || !modalNumber || !modalBoolean || !modalSecret || !modalMedia) {
                    return;
                }

                const row = rowForId(currentOptionId);
                const inputType = String(row?.dataset.optionInputType || 'textarea');
                let valueToSave = modalValue.value;
                if (inputType === 'number') {
                    valueToSave = modalNumber.value;
                }
                if (inputType === 'boolean') {
                    valueToSave = modalBoolean.value;
                }
                if (inputType === 'secret') {
                    valueToSave = modalSecret.value;
                }
                if (inputType === 'media') {
                    valueToSave = modalMedia.value;
                }

                try {
                    modalSave.disabled = true;
                    const payload = await fetchJson(updateUrlTemplate.replace('__ID__', String(currentOptionId)), {
                        method: 'PUT',
                        body: JSON.stringify({
                            value: valueToSave,
                        }),
                    });
                    updateRow(payload.option || null);
                    closeModal();
                    if (window.SM && typeof window.SM.alert === 'function') {
                        window.SM.alert('Saved', 'The site option was updated.', 'success');
                    }
                } catch (error) {
                    if (window.SM && typeof window.SM.notice === 'function') {
                        window.SM.notice('Save failed', error.message || 'Could not save the site option.', 'danger');
                    }
                } finally {
                    modalSave.disabled = false;
                }
            });
        }

        if (resetAllButton) {
            resetAllButton.addEventListener('click', async () => {
                const result = await confirmDialog(
                    'Reset all defaults?',
                    'This will restore all default site options to their default values and create any missing default options.',
                    'Reset All Defaults',
                    '#b45309'
                );

                if (!result || result.isConfirmed !== true) {
                    return;
                }

                try {
                    resetAllButton.disabled = true;
                    await fetchJson(resetAllUrl, {
                        method: 'POST',
                    });
                    root.querySelectorAll('tr[data-option-id]').forEach((row) => {
                        const defaultValue = decodeBase64Utf8(row.dataset.optionDefaultBase64 || '');
                        updateRow({
                            id: Number(row.dataset.optionId),
                            value: defaultValue,
                            is_secret: row.dataset.optionIsSecret === '1',
                            has_value: defaultValue !== '',
                        });
                    });
                    if (window.SM && typeof window.SM.alert === 'function') {
                        window.SM.alert('Defaults restored', 'Default site options were restored. Reload the page if you need to see newly created options.', 'success');
                    }
                } catch (error) {
                    if (window.SM && typeof window.SM.notice === 'function') {
                        window.SM.notice('Reset failed', error.message || 'Could not reset the default site options.', 'danger');
                    }
                } finally {
                    resetAllButton.disabled = false;
                }
            });
        }

        if (modalClose) {
            modalClose.addEventListener('click', closeModal);
        }
        if (modalCancel) {
            modalCancel.addEventListener('click', closeModal);
        }
        if (modal) {
            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });
        }
        window.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                closeModal();
            }
        });
    })();
</script>
