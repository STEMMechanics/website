@props(['id', 'title', 'kind' => 'filters'])
<dialog id="{{ $id }}" data-list-dialog class="sm-list-dialog sm-list-dialog-{{ $kind }}" aria-labelledby="{{ $id }}-title">
    <div class="sm-list-dialog-header">
        <h2 tabindex="-1" autofocus id="{{ $id }}-title" class="text-lg font-bold text-slate-900">{{ $title }}</h2>
        <div class="flex shrink-0 items-center gap-1">
            {{ $headerActions ?? '' }}
            <x-ui.button variant="plain" data-close-dialog aria-label="Close {{ $title }}" class="sm-dialog-close h-11 w-11 rounded-lg p-0! text-slate-500"><i class="fa-solid fa-xmark" aria-hidden="true"></i></x-ui.button>
        </div>
    </div>
    {{ $slot }}
</dialog>
