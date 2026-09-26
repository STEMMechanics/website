@php
    $personalNote = $currentStoreSelection['personal_note'] ?? [];
    $hasPersonalNote = \App\Services\NewsletterNoteContent::hasText([
        'body' => old('personal_note.body', $personalNote['body'] ?? ''),
        'format' => old('personal_note.format', $personalNote['format'] ?? 'text'),
    ]);
@endphp
<div class="relative mx-auto mb-8 w-full" data-personal-note-editor>
    @if($hasPersonalNote)
        <div class="relative">
            <x-ui.button type="button" variant="plain" class="absolute right-3 top-3 z-10 flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm" onclick="SMNewsletterOpenEditor('newsletter-note-editor')" aria-label="Edit newsletter introduction" title="Edit newsletter introduction"><i class="fa-solid fa-pencil" aria-hidden="true"></i></x-ui.button>
            @include('emails.partials.newsletter-personal-note', ['personalNote' => $personalNote])
        </div>
    @else
        <x-ui.button type="button" color="outline" class="w-full border-dashed" onclick="SMNewsletterOpenEditor('newsletter-note-editor')" aria-label="Add a newsletter introduction"><i class="fa-solid fa-plus mr-2" aria-hidden="true"></i>Add a newsletter introduction <span class="ml-2 font-normal text-slate-500">Optional</span></x-ui.button>
    @endif
</div>
<x-admin.newsletter-editor-dialog id="newsletter-note-editor" title="Newsletter introduction" width="w-[min(56rem,calc(100%-2rem))]">
    <x-admin.ai-status-toast id="newsletter-note-ai-toast" message="Preparing your newsletter introduction…" detail="Your introduction is being drafted from this edition’s recent activity." progress-label="Newsletter introduction generation" />
    <p class="mb-5 text-sm text-slate-500">Add a short introduction to this edition. The AI draft can use recent workshops, upcoming events, store updates, attendance and configured holiday dates.</p>
    <div class="mb-4" data-ai-widget x-data="{ noteHtml: @js(\App\Services\NewsletterNoteContent::html(['body' => old('personal_note.body', $personalNote['body'] ?? ''), 'format' => old('personal_note.format', $personalNote['format'] ?? 'text')])) }" x-on:mini-editor-link="SMNewsletterOpenNoteLink($event.detail)" x-on:sm-newsletter-ai-draft.window="if ($event.detail?.mode === 'append' && $event.detail?.html) { noteHtml = noteHtml.trim() ? noteHtml + '<p><br></p>' + $event.detail.html : $event.detail.html } else if ($event.detail?.html) { noteHtml = $event.detail.html }">
        <label class="mb-1 block text-sm">Introduction</label>
        <x-ui.mini-editor x-model="noteHtml" :custom-links="true" :max-characters="4000">
            <x-slot:toolbarActions>
                <x-ui.button type="button" variant="plain" class="inline-flex h-8 w-8 min-w-8 items-center justify-center rounded text-slate-600 hover:bg-sky-100 hover:text-sky-800" data-admin-ai data-ai-widget-target="#newsletter-note-ai-toast" data-ai-processing-message="Writing your newsletter introduction…" data-ai-lock-content="#newsletter-note-editor .tiptap" data-ai-lock-controls="#newsletter-note-editor [data-mini-editor-toolbar] button, #newsletter-note-editor button[form=newsletter-content-form]" data-ai-action="newsletter-message" data-ai-mode="replace" data-ai-url="{{ route('admin.ai.newsletter.message') }}" data-ai-token="{{ csrf_token() }}" data-ai-scope="#newsletter-content-form" data-ai-fields="personal_note[body]" :disabled="blank(config('services.openai.api_key'))" aria-label="Replace introduction with AI" title="Replace introduction with AI">
                    <span class="relative inline-flex h-5 w-5 items-center justify-center" aria-hidden="true"><i class="fa-solid fa-wand-magic-sparkles"></i><i class="fa-solid fa-arrows-rotate absolute -bottom-1 -right-1 rounded-full bg-white p-px text-[9px]"></i></span>
                </x-ui.button>
                <x-ui.button type="button" variant="plain" class="inline-flex h-8 w-8 min-w-8 items-center justify-center rounded text-slate-600 hover:bg-sky-100 hover:text-sky-800" data-admin-ai data-ai-widget-target="#newsletter-note-ai-toast" data-ai-processing-message="Adding to your newsletter introduction…" data-ai-lock-content="#newsletter-note-editor .tiptap" data-ai-lock-controls="#newsletter-note-editor [data-mini-editor-toolbar] button, #newsletter-note-editor button[form=newsletter-content-form]" data-ai-action="newsletter-message" data-ai-mode="append" data-ai-url="{{ route('admin.ai.newsletter.message') }}" data-ai-token="{{ csrf_token() }}" data-ai-scope="#newsletter-content-form" data-ai-fields="personal_note[body]" :disabled="blank(config('services.openai.api_key'))" aria-label="Append AI text" title="Append AI text">
                    <span class="relative inline-flex h-5 w-5 items-center justify-center" aria-hidden="true"><i class="fa-solid fa-wand-magic-sparkles"></i><i class="fa-solid fa-plus absolute -bottom-1 -right-1 rounded-full bg-white p-px text-[9px]"></i></span>
                </x-ui.button>
            </x-slot:toolbarActions>
        </x-ui.mini-editor>
        <input form="newsletter-content-form" type="hidden" name="personal_note[body]" x-model="noteHtml">
        <input form="newsletter-content-form" type="hidden" name="personal_note[format]" value="html">
        @error('personal_note.body')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <input form="newsletter-content-form" type="hidden" id="newsletter-note-image" name="personal_note[image_name]" value="{{ old('personal_note.image_name', $personalNote['image_name'] ?? '') }}" oninput="SMNewsletterNotePhotoPreview()">
    <div class="relative mb-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
        <p class="mb-3 text-sm font-medium text-slate-700">Photo (optional)</p>
        <x-ui.button type="button" variant="plain" id="newsletter-note-image-remove" class="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded text-slate-500 hover:bg-red-50 hover:text-red-700" onclick="document.getElementById('newsletter-note-image').value = ''; SMNewsletterNotePhotoPreview()" aria-label="Remove photo" title="Remove photo" :hidden="!filled($personalNote['image_url'] ?? null)"><i class="fa-solid fa-trash" aria-hidden="true"></i></x-ui.button>
        <img id="newsletter-note-image-preview" @if($personalNote['image_url'] ?? null) src="{{ $personalNote['image_url'] }}" @else hidden @endif alt="Selected photo" class="mx-auto mb-3 h-24 w-24 rounded-xl object-cover">
        <div class="flex justify-center">
            <x-ui.button type="button" variant="plain" class="border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 shadow-none hover:bg-slate-100 hover:text-slate-800" onclick="SMNewsletterChooseNotePhoto()"><i class="fa-solid fa-arrow-up-from-bracket mr-2" aria-hidden="true"></i>Choose or upload photo</x-ui.button>
        </div>
        <p class="mt-3 text-xs text-slate-500">A portrait or a photo of what you’ve been making fits nicely. It appears on the left of your message, with the same rounded corners as the header image.</p>
        @error('personal_note.image_name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="flex justify-end gap-3">
        <x-ui.button type="button" color="outline" onclick="SMNewsletterCloseEditor(this.closest('dialog'))">Cancel</x-ui.button>
        <x-ui.button type="submit" form="newsletter-content-form">Save introduction</x-ui.button>
    </div>
</x-admin.newsletter-editor-dialog>

<x-admin.newsletter-editor-dialog id="newsletter-note-link-editor" title="Add a link">
    <div x-data="{ search: '', links: @js($newsletterLinkOptions) }">
        <x-ui.input label="Find a store item or workshop" x-model="search" placeholder="Search by name…" />
        <div class="mb-5 max-h-44 overflow-y-auto rounded-lg border border-slate-200">
            <template x-for="link in links.filter(link => (link.title + ' ' + link.type).toLowerCase().includes(search.toLowerCase())).slice(0, 30)" :key="link.url">
                <button type="button" class="block w-full border-b border-slate-100 px-3 py-2 text-left text-sm hover:bg-sky-50" x-on:click="document.getElementById('newsletter-note-link-url').value = link.url; if (!document.getElementById('newsletter-note-link-label').value) document.getElementById('newsletter-note-link-label').value = link.title">
                    <span class="block font-medium text-slate-900" x-text="link.title"></span><span class="text-xs text-slate-500" x-text="link.type"></span>
                </button>
            </template>
            <p class="p-3 text-sm text-slate-500" x-show="!links.some(link => (link.title + ' ' + link.type).toLowerCase().includes(search.toLowerCase()))">No matches. You can paste a link below.</p>
        </div>
    </div>
    <x-ui.input id="newsletter-note-link-url" label="Website or link address" type="text" placeholder="https://example.com" />
    <x-ui.input id="newsletter-note-link-label" label="Link text" info="Used when inserting a new link. Selected text keeps its wording." />
    <p id="newsletter-note-link-error" class="mb-3 text-sm text-red-600" hidden>Enter a valid website address or email link.</p>
    <div class="flex flex-wrap justify-end gap-3">
        <x-ui.button type="button" color="outline" onclick="SMNewsletterApplyNoteLink(true)">Remove link</x-ui.button>
        <x-ui.button type="button" color="outline" onclick="SMNewsletterCloseEditor(this.closest('dialog'))">Cancel</x-ui.button>
        <x-ui.button type="button" onclick="SMNewsletterApplyNoteLink()">Apply link</x-ui.button>
    </div>
</x-admin.newsletter-editor-dialog>
