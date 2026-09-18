@php
    $personalNote = $currentStoreSelection['personal_note'] ?? [];
    $hasPersonalNote = ($personalNote['enabled'] ?? false) && filled($personalNote['body'] ?? null);
@endphp
<div class="relative mx-auto mb-8 w-full" data-personal-note-editor>
    @if($hasPersonalNote)
        <div class="relative">
            <x-ui.button type="button" variant="plain" class="absolute right-3 top-3 z-10 flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm" onclick="SMNewsletterOpenEditor('newsletter-note-editor')" aria-label="Edit personal note" title="Edit personal note"><i class="fa-solid fa-pencil" aria-hidden="true"></i></x-ui.button>
            @include('emails.partials.newsletter-personal-note', ['personalNote' => $personalNote])
        </div>
    @else
        <x-ui.button type="button" color="outline" class="w-full border-dashed" onclick="SMNewsletterOpenEditor('newsletter-note-editor'); document.getElementById('newsletter-note-enabled').value = '1'" aria-label="Add a personal note"><i class="fa-solid fa-plus mr-2" aria-hidden="true"></i>Add a personal note <span class="ml-2 font-normal text-slate-500">Optional</span></x-ui.button>
    @endif
</div>
<x-admin.newsletter-editor-dialog id="newsletter-note-editor" title="A note from you">
    <p class="mb-5 text-sm text-slate-500">Add a paragraph or two between the header and the items. A photo is optional; text alone works well here too.</p>
    <x-ui.select form="newsletter-content-form" id="newsletter-note-enabled" name="personal_note[enabled]" label="Include in this newsletter">
        <option value="1" @selected(old('personal_note.enabled', $personalNote['enabled'] ?? false))>Yes</option>
        <option value="0" @selected(!old('personal_note.enabled', $personalNote['enabled'] ?? false))>No — keep as a draft</option>
    </x-ui.select>
    <div class="mb-4" x-data="{ noteHtml: @js(\App\Services\NewsletterNoteContent::html(['body' => old('personal_note.body', $personalNote['body'] ?? ''), 'format' => old('personal_note.format', $personalNote['format'] ?? 'text')])) }" x-on:mini-editor-link="SMNewsletterOpenNoteLink($event.detail)">
        <label class="mb-1 block text-sm">Your message</label>
        <x-ui.mini-editor x-model="noteHtml" :custom-links="true" />
        <input form="newsletter-content-form" type="hidden" name="personal_note[body]" x-model="noteHtml">
        <input form="newsletter-content-form" type="hidden" name="personal_note[format]" value="html">
        <p class="mt-2 text-xs text-slate-500">Use the link button to find a store item or workshop, or paste a website address. Up to 4,000 characters.</p>
        @error('personal_note.body')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <input form="newsletter-content-form" type="hidden" id="newsletter-note-image" name="personal_note[image_name]" value="{{ old('personal_note.image_name', $personalNote['image_name'] ?? '') }}" oninput="SMNewsletterNotePhotoPreview()">
    <div class="mb-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
        <p class="mb-3 text-sm font-medium text-slate-700">Photo (optional)</p>
        <img id="newsletter-note-image-preview" @if($personalNote['image_url'] ?? null) src="{{ $personalNote['image_url'] }}" @else hidden @endif alt="Selected photo" class="mb-3 h-24 w-24 rounded-xl object-cover">
        <div class="flex flex-wrap gap-3">
            <x-ui.button type="button" color="outline" onclick="SMNewsletterChooseNotePhoto()">Choose or upload photo</x-ui.button>
            <x-ui.button type="button" color="outline" onclick="document.getElementById('newsletter-note-image').value = ''; SMNewsletterNotePhotoPreview()">Remove photo</x-ui.button>
        </div>
        <p class="mt-3 text-xs text-slate-500">A portrait or a photo of what you’ve been making fits nicely. It appears on the left of your message, with the same rounded corners as the header image.</p>
        @error('personal_note.image_name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="flex justify-end gap-3">
        <x-ui.button type="button" color="outline" onclick="SMNewsletterCloseEditor(this.closest('dialog'))">Cancel</x-ui.button>
        <x-ui.button type="submit" form="newsletter-content-form">Save note</x-ui.button>
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
