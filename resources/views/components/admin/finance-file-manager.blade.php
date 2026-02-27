@props([
    'label' => 'Private Files',
    'info' => null,
    'fieldName' => 'private_file_ids',
    'uploadName' => 'finance_private_upload',
    'uploadId' => null,
    'contextType',
    'contextId' => '',
    'files' => [],
])

@php
    $managerId = 'finance-file-manager-'.substr(md5($fieldName.'-'.$contextType.'-'.$contextId), 0, 12);
    $resolvedUploadId = (string) ($uploadId ?: $managerId.'-upload');
    $resolvedFieldId = $managerId.'-ids';
    $resolvedListId = $managerId.'-list';
    $resolvedEmptyId = $managerId.'-empty';
    $resolvedErrorId = $managerId.'-error';
    $seedFiles = collect($files)->map(function ($file) {
        if (is_array($file)) {
            return [
                'id' => (int) ($file['id'] ?? 0),
                'name' => (string) ($file['name'] ?? ''),
                'mime_type' => (string) ($file['mime_type'] ?? ''),
                'size' => (int) ($file['size'] ?? 0),
                'view_url' => (string) ($file['view_url'] ?? ''),
                'download_url' => (string) ($file['download_url'] ?? ''),
            ];
        }

        return [
            'id' => (int) ($file->id ?? 0),
            'name' => (string) ($file->original_name ?? ''),
            'mime_type' => (string) ($file->mime_type ?? ''),
            'size' => (int) ($file->size ?? 0),
            'view_url' => route('admin.finance-file.view', $file),
            'download_url' => route('admin.finance-file.download', $file),
        ];
    })->filter(fn (array $file) => $file['id'] > 0)->values()->all();
@endphp

<div class="mb-6">
    <x-ui.file-upload label="{{ $label }}" name="{{ $uploadName }}" id="{{ $resolvedUploadId }}" />

    <input type="hidden" name="{{ $fieldName }}" id="{{ $resolvedFieldId }}" value="{{ old($fieldName, implode(',', array_map(fn ($file) => (string) $file['id'], $seedFiles))) }}" />

    <div class="font-semibold mb-2">{{ $label }}</div>
    <div id="{{ $resolvedEmptyId }}" class="text-sm text-gray-500 {{ count($seedFiles) === 0 ? '' : 'hidden' }}">No files attached.</div>
    <ul id="{{ $resolvedListId }}" class="space-y-2 {{ count($seedFiles) === 0 ? 'hidden' : '' }}"></ul>
    <div id="{{ $resolvedErrorId }}" class="text-xs text-red-600 mt-2 hidden"></div>

    @if(is_string($info) && $info !== '')
        <div class="text-xs text-gray-500 ml-2 mt-1">{{ $info }}</div>
    @endif
</div>

<script>
    (function () {
        const uploadInput = document.getElementById(@js($resolvedUploadId));
        const hiddenInput = document.getElementById(@js($resolvedFieldId));
        const list = document.getElementById(@js($resolvedListId));
        const emptyState = document.getElementById(@js($resolvedEmptyId));
        const errorEl = document.getElementById(@js($resolvedErrorId));
        const contextType = @js((string) $contextType);
        const contextId = @js((string) $contextId);
        const uploadUrl = @js(route('admin.finance-file.upload'));
        const impactUrlTemplate = @js(route('admin.finance-file.impact', ['financeFile' => '__ID__']));
        const associationUrlTemplate = @js(route('admin.finance-file.association', ['financeFile' => '__ID__']));
        const csrfToken = @js(csrf_token());
        let files = @js($seedFiles);

        if (!uploadInput || !hiddenInput || !list || !emptyState || !errorEl) {
            return;
        }

        const toBytes = (value) => {
            if (window.SM && typeof window.SM.bytesToString === 'function') {
                return window.SM.bytesToString(value);
            }

            if (!Number.isFinite(value) || value <= 0) {
                return '0 B';
            }

            if (value < 1024) {
                return `${value} B`;
            }

            const kb = value / 1024;
            if (kb < 1024) {
                return `${kb.toFixed(1)} KB`;
            }

            const mb = kb / 1024;
            return `${mb.toFixed(1)} MB`;
        };

        const setError = (message) => {
            const text = String(message || '').trim();
            if (text === '') {
                errorEl.classList.add('hidden');
                errorEl.textContent = '';
                return;
            }

            errorEl.textContent = text;
            errorEl.classList.remove('hidden');
        };

        const syncHiddenValue = () => {
            hiddenInput.value = files.map((file) => String(file.id)).join(',');
        };

        const render = () => {
            syncHiddenValue();
            list.innerHTML = '';

            if (files.length === 0) {
                list.classList.add('hidden');
                emptyState.classList.remove('hidden');
                return;
            }

            list.classList.remove('hidden');
            emptyState.classList.add('hidden');

            files.forEach((file) => {
                const item = document.createElement('li');
                item.className = 'flex items-center justify-between gap-3 border border-gray-200 rounded-lg px-3 py-2 bg-white';
                item.innerHTML = `
                    <div class="min-w-0">
                        <div class="text-sm text-gray-900 truncate">${file.name}</div>
                        <div class="text-xs text-gray-500">${file.mime_type || 'File'} - ${toBytes(Number(file.size || 0))}</div>
                    </div>
                    <span class="inline-flex items-center gap-3">
                        <a class="hover:text-primary-color" title="View file" href="${file.view_url}" target="_blank"><i class="fa-solid fa-eye"></i></a>
                        <a class="hover:text-primary-color" title="Download file" href="${file.download_url}"><i class="fa-solid fa-download"></i></a>
                        <button type="button" class="hover:text-red-600" title="Remove file" data-remove-id="${file.id}">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </span>
                `;

                list.appendChild(item);
            });
        };

        const fetchJson = async (url, options = {}) => {
            const response = await fetch(url, options);
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || payload.success === false) {
                const message = payload.message || 'Request failed.';
                throw new Error(message);
            }
            return payload;
        };

        const escapeHtml = (value) => String(value || '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

        const chooseRemovalAction = async ({ fileName, hasOthers, othersCount, others }) => {
            if (typeof Swal !== 'undefined' && Swal && typeof Swal.fire === 'function') {
                const linkedItemsHtml = hasOthers
                    ? `<ul class="mt-2 list-disc list-inside">${others.map((entry) => `<li>${escapeHtml(entry.label)}</li>`).join('')}</ul>`
                    : '';
                const result = await Swal.fire({
                    position: 'top',
                    icon: 'warning',
                    iconColor: hasOthers ? '#b45309' : '#b91c1c',
                    title: hasOthers ? 'Remove linked file' : 'Remove file',
                    html: `
                        <div class="text-left">
                            <p><strong>${escapeHtml(fileName)}</strong> is linked to this ${escapeHtml(contextType)}${hasOthers ? ` and ${othersCount} other record(s)` : ''}.</p>
                            <p class="mt-2">Choose what to do:</p>
                            ${linkedItemsHtml}
                        </div>
                    `,
                    showCancelButton: true,
                    showDenyButton: true,
                    confirmButtonText: hasOthers ? 'Unlink Here' : 'Delete File',
                    confirmButtonColor: hasOthers ? '#2563eb' : '#b91c1c',
                    denyButtonText: hasOthers ? 'Delete Everywhere' : 'Unlink Here',
                    denyButtonColor: hasOthers ? '#b91c1c' : '#2563eb',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true,
                });

                if (result.isConfirmed) {
                    return hasOthers ? 'unlink' : 'delete';
                }
                if (result.isDenied) {
                    return hasOthers ? 'delete' : 'unlink';
                }
                return null;
            }

            return null;
        };

        const removeFileWithPrompt = async (fileId) => {
            const targetFile = files.find((entry) => Number(entry.id) === Number(fileId));
            const fileName = targetFile ? String(targetFile.name || 'file') : 'file';

            if (!contextId) {
                const action = await chooseRemovalAction({
                    fileName,
                    hasOthers: false,
                    othersCount: 0,
                    others: [],
                });
                if (action !== 'delete') {
                    return;
                }

                const associationUrl = associationUrlTemplate.replace('__ID__', String(fileId));
                await fetchJson(associationUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        context_type: contextType,
                        context_id: '0',
                        action: 'delete',
                    }),
                });

                files = files.filter((file) => Number(file.id) !== Number(fileId));
                render();
                return;
            }

            const impactUrl = impactUrlTemplate.replace('__ID__', String(fileId));
            const impactQuery = new URLSearchParams({
                context_type: contextType,
                context_id: String(contextId || ''),
            });

            const impact = await fetchJson(`${impactUrl}?${impactQuery.toString()}`);
            const others = Array.isArray(impact.other_associations) ? impact.other_associations : [];
            const hasOthers = others.length > 0;
            const action = await chooseRemovalAction({
                fileName,
                hasOthers,
                othersCount: others.length,
                others,
            });
            if (!action) {
                return;
            }

            const associationUrl = associationUrlTemplate.replace('__ID__', String(fileId));
            await fetchJson(associationUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    context_type: contextType,
                    context_id: String(contextId || ''),
                    action,
                }),
            });

            files = files.filter((file) => Number(file.id) !== Number(fileId));
            render();
        };

        uploadInput.addEventListener('change', async (event) => {
            const file = event.target.files && event.target.files[0] ? event.target.files[0] : null;
            if (!file) {
                return;
            }

            setError('');
            const formData = new FormData();
            formData.append('file', file);

            try {
                const payload = await fetchJson(uploadUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: formData,
                });

                const uploaded = payload.file || null;
                if (uploaded && Number(uploaded.id) > 0) {
                    files = files.filter((entry) => Number(entry.id) !== Number(uploaded.id));
                    files.push(uploaded);
                    render();
                }
            } catch (error) {
                setError(error?.message || 'Upload failed.');
            } finally {
                uploadInput.value = '';
                uploadInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        list.addEventListener('click', async (event) => {
            const button = event.target.closest('button[data-remove-id]');
            if (!button) {
                return;
            }

            const fileId = Number(button.getAttribute('data-remove-id') || 0);
            if (!Number.isFinite(fileId) || fileId <= 0) {
                return;
            }

            setError('');
            button.disabled = true;
            try {
                await removeFileWithPrompt(fileId);
            } catch (error) {
                setError(error?.message || 'Could not remove file.');
            } finally {
                button.disabled = false;
            }
        });

        render();
    })();
</script>
