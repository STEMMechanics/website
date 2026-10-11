@props(['id', 'title', 'width' => 'w-[min(38rem,calc(100%-2rem))]'])
<dialog x-data id="{{ $id }}" aria-labelledby="{{ $id }}-title" class="m-auto max-h-[85vh] {{ $width }} overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl backdrop:bg-slate-900/50" x-on:cancel.prevent="window.SMNewsletterCloseEditor($el)">
    <div class="mb-5 flex items-center justify-between gap-4">
        <div class="flex min-w-0 flex-wrap items-center gap-2">
            <h2 id="{{ $id }}-title" class="text-xl font-bold text-slate-900">{{ $title }}</h2>
            @isset($titleActions){{ $titleActions }}@endisset
        </div>
        <x-ui.button x-data type="button" variant="plain" aria-label="Close editor" x-on:click="window.SMNewsletterCloseEditor($el.closest('dialog'))"><i class="fa-solid fa-xmark" aria-hidden="true"></i></x-ui.button>
    </div>
    {{ $slot }}
</dialog>
