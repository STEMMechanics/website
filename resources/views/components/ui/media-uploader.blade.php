@props([
    'inputId',
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
    'submitText' => 'Add Media',
    'submittingText' => 'Uploading...',
])

@php
    $countExpression = '('.$count.')';
    $selectedExpression = $countExpression
        .' ? '.$countExpression
        .' + '.json_encode(' '.$itemLabel)
        .' + ('.$countExpression.' === 1 ? "" : "s")'
        .' + '.json_encode(' selected')
        .' : '.json_encode($emptyText);
@endphp

<div>
    <input
        id="{{ $inputId }}"
        @if($inputName) name="{{ $inputName }}" @endif
        type="file"
        @if($accept) accept="{{ $accept }}" @endif
        @if($multiple) multiple @endif
        class="sr-only"
        x-ref="{{ $inputRef }}"
        x-on:change="{{ $onFiles }}($event.target.files); $event.target.value = ''"
        x-bind:disabled="{{ $disabled }}"
    >

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

    {{ $slot }}

    <div class="mt-4 flex justify-end">
        <x-ui.button
            type="submit"
            x-bind:disabled="({{ $disabled }}) || (({{ $count }}) <= 0)"
        >
            <span x-show="!({{ $disabled }})">{{ $submitText }}</span>
            <span x-show="{{ $disabled }}" x-cloak>{{ $submittingText }}</span>
        </x-ui.button>
    </div>
</div>
