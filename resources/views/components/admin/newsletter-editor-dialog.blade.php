@props(['id', 'title', 'width' => 'w-[min(38rem,calc(100%-2rem))]'])
<dialog id="{{ $id }}" class="m-auto max-h-[85vh] {{ $width }} overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl backdrop:bg-slate-900/50" oncancel="event.preventDefault(); window.SMNewsletterCloseEditor(this)">
    <div class="mb-5 flex items-center justify-between gap-4">
        <div class="flex min-w-0 flex-wrap items-center gap-2">
            <h2 class="text-xl font-bold text-slate-900">{{ $title }}</h2>
            @isset($titleActions){{ $titleActions }}@endisset
        </div>
        <x-ui.button type="button" variant="plain" aria-label="Close editor" onclick="window.SMNewsletterCloseEditor(this.closest('dialog'))"><i class="fa-solid fa-xmark" aria-hidden="true"></i></x-ui.button>
    </div>
    {{ $slot }}
</dialog>
