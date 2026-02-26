@props([
    'name',
    'label' => '',
    'id' => null,
    'placeholder' => '',
    'readonly' => false,
    'disabled' => false,
    'info' => null,
    'error' => null,
    'fieldClasses' => '',
    'noLabel' => false,
    'floating' => false,
    'noWrapper' => false,
    'inline' => false,
])

@php
    if ($error === null) {
        $error = $errors->first($name);
    }

    $hasError = $error !== '';
    $readonly = filter_var($readonly, FILTER_VALIDATE_BOOLEAN);
    $disabled = filter_var($disabled, FILTER_VALIDATE_BOOLEAN);
    $inputId = (string) ($id ?: $name);
    $fileUiUid = substr(md5($name.'-'.$inputId), 0, 12);
    $dropzoneId = 'file-dropzone-'.$fileUiUid;
    $fileNameId = 'file-name-'.$fileUiUid;
    $fileMetaId = 'file-meta-'.$fileUiUid;
    $fileStateId = 'file-state-'.$fileUiUid;
    $fileClearId = 'file-clear-'.$fileUiUid;
    $maxUploadSize = \App\Helpers::bytesToString(\App\Helpers::getMaxUploadSize());
    $maxUploadBytes = (int) \App\Helpers::getMaxUploadSize();
    $placeholderText = $placeholder !== '' ? $placeholder : 'Drop a file here or click to browse';
@endphp

@if(!$noWrapper)
<div class="{{ twMerge(['mb-4'], $attributes->get('class'), ($inline ? 'w-full' : '')) }}">
@endif
    @if(!$noLabel && !$floating)
        <label for="{{ $inputId }}" class="block text-sm pl-1">{{ $label }}</label>
    @endif

    <div class="{{ twMerge(['relative mt-1'], $fieldClasses, ($noWrapper ? $attributes->get('class') : '')) }}">
        <input
            type="file"
            name="{{ $name }}"
            id="{{ $inputId }}"
            class="sr-only peer"
            @disabled($disabled || $readonly)
            {{ $attributes->except(['class']) }}
        />

        <label
            for="{{ $inputId }}"
            id="{{ $dropzoneId }}"
            class="{{ twMerge([
                'group flex w-full cursor-pointer items-center justify-between gap-4 rounded-lg border-2 border-dashed bg-white px-4 py-4 text-left text-sm transition',
                $hasError ? 'border-red-600' : 'border-gray-300',
                ($disabled || $readonly) ? 'cursor-not-allowed bg-gray-100 text-gray-500' : 'hover:border-primary-color hover:bg-sky-50',
            ]) }}"
        >
            <div class="min-w-0 grow">
                <div id="{{ $fileNameId }}" class="truncate font-medium text-gray-800">{{ $placeholderText }}</div>
                <div id="{{ $fileMetaId }}" class="mt-1 text-xs text-gray-500">Max upload size: {{ $maxUploadSize }}</div>
            </div>
            <span class="inline-flex shrink-0 items-center rounded-md border border-primary-color px-3 py-1.5 text-xs font-semibold text-primary-color transition group-hover:bg-primary-color group-hover:text-white">Browse</span>
        </label>

        <div class="mt-2 flex items-center justify-between gap-3">
            <button type="button" id="{{ $fileClearId }}" class="hidden text-xs font-medium text-gray-500 hover:text-danger-color">
                Clear file
            </button>
            <div id="{{ $fileStateId }}" class="hidden text-xs text-primary-color">
                <span class="inline-flex items-center gap-2 rounded-full bg-primary-color/10 px-2.5 py-1 font-medium">
                    <i class="fa-solid fa-circle-notch animate-spin"></i>
                    <span>Uploading...</span>
                </span>
            </div>
        </div>

        @if($info)
            <div class="mt-1 text-xs text-gray-500">{{ $info }}</div>
        @endif

        @if($hasError)
            <div class="mt-1 text-xs text-red-600">{{ $error }}</div>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const input = document.getElementById(@js($inputId));
            const dropzone = document.getElementById(@js($dropzoneId));
            const nameElement = document.getElementById(@js($fileNameId));
            const metaElement = document.getElementById(@js($fileMetaId));
            const stateElement = document.getElementById(@js($fileStateId));
            const clearElement = document.getElementById(@js($fileClearId));
            const placeholderText = @js($placeholderText);
            const defaultMetaText = @js('Max upload size: '.$maxUploadSize);
            const maxUploadBytes = Number(@js($maxUploadBytes));

            if (!input || !dropzone || !nameElement || !metaElement || !stateElement || !clearElement) {
                return;
            }

            const resetUi = (metaText = defaultMetaText, hasError = false) => {
                nameElement.textContent = placeholderText;
                metaElement.textContent = metaText;
                metaElement.classList.toggle('text-red-600', hasError);
                metaElement.classList.toggle('text-gray-500', !hasError);
                stateElement.classList.add('hidden');
                clearElement.classList.add('hidden');
                dropzone.classList.remove('ring-2', 'ring-primary-color', 'border-primary-color');
            };

            const assignFile = (file) => {
                if (!file) {
                    input.value = '';
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    return;
                }

                const transfer = new DataTransfer();
                transfer.items.add(file);
                input.files = transfer.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            };

            const clearFile = (triggerChange = true) => {
                input.value = '';
                input.setCustomValidity('');
                resetUi();
                if (triggerChange) {
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            };

            input.addEventListener('change', () => {
                const file = input.files && input.files[0] ? input.files[0] : null;
                if (!file) {
                    input.setCustomValidity('');
                    resetUi();
                    return;
                }

                if (Number.isFinite(maxUploadBytes) && maxUploadBytes > 0 && file.size > maxUploadBytes) {
                    const errorMessage = `File is too large. Maximum upload size is ${@js($maxUploadSize)}.`;
                    input.setCustomValidity(errorMessage);
                    input.reportValidity();
                    resetUi(errorMessage, true);
                    input.value = '';
                    return;
                }

                const sizeText = (window.SM && typeof window.SM.bytesToString === 'function')
                    ? window.SM.bytesToString(file.size)
                    : `${Math.round(file.size / 1024)} KB`;

                input.setCustomValidity('');
                nameElement.textContent = file.name;
                metaElement.textContent = `${file.type || 'File'} - ${sizeText}`;
                metaElement.classList.remove('text-red-600');
                metaElement.classList.add('text-gray-500');
                stateElement.classList.add('hidden');
                clearElement.classList.remove('hidden');
            });

            const preventDefaults = (event) => {
                event.preventDefault();
                event.stopPropagation();
            };

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((eventName) => {
                dropzone.addEventListener(eventName, preventDefaults);
            });

            ['dragenter', 'dragover'].forEach((eventName) => {
                dropzone.addEventListener(eventName, () => {
                    if (input.disabled) {
                        return;
                    }
                    dropzone.classList.add('ring-2', 'ring-primary-color', 'border-primary-color');
                });
            });

            ['dragleave', 'drop'].forEach((eventName) => {
                dropzone.addEventListener(eventName, () => {
                    dropzone.classList.remove('ring-2', 'ring-primary-color', 'border-primary-color');
                });
            });

            dropzone.addEventListener('drop', (event) => {
                if (input.disabled) {
                    return;
                }

                const droppedFile = event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0]
                    ? event.dataTransfer.files[0]
                    : null;

                assignFile(droppedFile);
            });

            clearElement.addEventListener('click', () => {
                clearFile(true);
            });

            const parentForm = input.closest('form');
            if (!parentForm) {
                return;
            }

            parentForm.addEventListener('submit', () => {
                if (!input.files || input.files.length === 0) {
                    return;
                }
                stateElement.classList.remove('hidden');
            });
        });
    </script>
@if(!$noWrapper)
</div>
@endif
