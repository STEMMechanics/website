@props([
    'inputId',
    'pageUpload' => false,
    'inputName' => null,
    'inputRef',
    'accept' => null,
    'multiple' => true,
    'count',
    'itemLabel' => 'media item',
    'emptyText' => 'Drop files here',
    'description' => null,
    'supportedTypes' => null,
    'localButtonText' => 'Select Local Files',
    'existingButtonText' => 'Browse Existing Media',
    'onFiles',
    'onBrowseExisting',
    'disabled' => 'false',
    'clearAfterChange' => true,
    'submitText' => 'Add Media',
    'submittingText' => 'Uploading...',
    'showSubmit' => true,
])

@php
    $clearAfterChange = filter_var($clearAfterChange, FILTER_VALIDATE_BOOLEAN);
    $showSubmit = filter_var($showSubmit, FILTER_VALIDATE_BOOLEAN);
    $countExpression = '('.$count.')';
    $selectedExpression = $countExpression
        .' ? '.$countExpression
        .' + '.json_encode(' '.$itemLabel)
        .' + ('.$countExpression.' === 1 ? "" : "s")'
        .' + '.json_encode(' selected')
        .' : '.json_encode($emptyText);
@endphp

<div
    @if($pageUpload)
    data-workshop-page-upload
    x-on:workshop-upload.window="if ($event.detail.id === @js($inputId) && !({{ $disabled }})) $refs.{{ $inputRef }}.click()"
    x-on:workshop-browse.window="if ($event.detail.id === @js($inputId) && !({{ $disabled }})) {{ $onBrowseExisting }}()"
    x-on:dragenter.window="if (Array.from($event.dataTransfer?.types || []).includes('Files')) { $event.preventDefault(); if (!({{ $disabled }})) pageUploadDragDepth++; }"
    x-on:dragover.window="if (Array.from($event.dataTransfer?.types || []).includes('Files')) { $event.preventDefault(); $event.dataTransfer.dropEffect = ({{ $disabled }}) ? 'none' : 'copy'; }"
    x-on:dragleave.window="pageUploadDragDepth = Math.max(0, pageUploadDragDepth - 1)"
    x-on:drop.window="if (Array.from($event.dataTransfer?.types || []).includes('Files')) { const handled = $event.defaultPrevented; $event.preventDefault(); pageUploadDragDepth = 0; if (!handled && !({{ $disabled }})) {{ $onFiles }}($event.dataTransfer.files); }"
    x-on:blur.window="pageUploadDragDepth = 0"
    @endif
>
    @if($pageUpload)
        <template x-teleport="body"><div x-show="pageUploadDragDepth > 0" x-cloak class="sm-page-drop-overlay" aria-hidden="true"><div><i class="fa-solid fa-cloud-arrow-up text-5xl" aria-hidden="true"></i><strong class="mt-5 block text-2xl">Drop files to upload</strong><span class="mt-2 block">Files will be attached to this workshop.</span></div></div></template>
    @endif
    <input
        id="{{ $inputId }}"
        @if($inputName) name="{{ $inputName }}" @endif
        type="file"
        @if($accept) accept="{{ $accept }}" @endif
        @if($multiple) multiple @endif
        class="sr-only"
        x-ref="{{ $inputRef }}"
        x-on:change="{{ $onFiles }}($event.target.files){{ $clearAfterChange ? '; $event.target.value = \'\'' : '' }}"
        x-bind:disabled="{{ $disabled }}"
    >

    @unless($pageUpload)
    <div
        {{ $attributes->class(['mt-1 rounded-lg border-2 border-dashed border-gray-300 bg-white px-4 py-5 text-sm transition']) }}
        x-on:dragover.prevent="$el.classList.add('ring-2', 'ring-primary-color', 'border-primary-color')"
        x-on:dragleave.prevent="$el.classList.remove('ring-2', 'ring-primary-color', 'border-primary-color')"
        x-on:drop.prevent="$el.classList.remove('ring-2', 'ring-primary-color', 'border-primary-color'); {{ $onFiles }}($event.dataTransfer.files)"
    >
        <div class="flex flex-col items-center justify-center gap-3 text-center">
            <div>
                <div
                    class="font-medium text-gray-800"
                    x-text="{{ $selectedExpression }}"
                ></div>
                @if($description)
                    <div class="mt-1 text-xs text-gray-500">{{ $description }}</div>
                @endif
            </div>

            <div class="flex flex-wrap justify-center gap-2">
                <x-ui.button
                    type="button"
                    color="primary-outline-sm"
                    x-on:click.prevent="$refs.{{ $inputRef }}.click()"
                    x-bind:disabled="{{ $disabled }}"
                >{{ $localButtonText }}</x-ui.button>
                <x-ui.button
                    type="button"
                    color="primary-outline-sm"
                    x-on:click.prevent="{{ $onBrowseExisting }}()"
                    x-bind:disabled="{{ $disabled }}"
                >{{ $existingButtonText }}</x-ui.button>
            </div>

            @if($supportedTypes)
                <div class="text-xs text-gray-500">{{ $supportedTypes }}</div>
            @endif
        </div>
    </div>

    @endunless

    {{ $slot }}

    @if($showSubmit || isset($actions))
    <div class="mt-4 flex flex-wrap justify-end gap-2">
        @isset($actions)
            {{ $actions }}
        @endisset
        @if($showSubmit)
        <x-ui.button
            type="submit"
            x-bind:disabled="({{ $disabled }}) || (({{ $count }}) <= 0)"
        >
            <span x-show="!({{ $disabled }})">{{ $submitText }}</span>
            <span x-show="{{ $disabled }}" x-cloak>{{ $submittingText }}</span>
        </x-ui.button>
        @endif
    </div>
    @endif
</div>
