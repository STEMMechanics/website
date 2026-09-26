<x-layout>
@php
    $newsletterTabs = [
        ['title' => 'Newsletter', 'route' => route('admin.newsletter.index'), 'active' => request()->routeIs('admin.newsletter.index')],
        ['title' => 'Themes', 'route' => route('admin.subscription.theme.index'), 'active' => request()->routeIs('admin.subscription.theme.*')],
    ];
@endphp

    <x-mast :tabs="$newsletterTabs" description="Prepare the next newsletter, choose products and send a preview.">Newsletter
        <x-slot:actions>
                    <form class="w-full" method="POST" action="{{ route('admin.subscription.send-all-now') }}" x-data x-on:submit.prevent="SM.confirm('Queue newsletter?', 'Queue newsletter for all confirmed subscriptions now?', 'Queue Newsletter', (isConfirmed) => { if (isConfirmed) { $el.submit(); } })">
                        @csrf
                        <x-ui.button color="mast" type="submit">Send All Now</x-ui.button>
                    </form>
        </x-slot:actions>
    </x-mast>
    <x-container class="py-5 sm:py-8" inner-class="max-w-[1100px]">
        <div data-list-preserve="newsletter-settings">

        @php
            $contentOrder = old('content_order', $storePromotion->content_order) === 'workshops' ? 'workshops' : 'store';
        @endphp
        @if(session('message'))
            <div hidden data-newsletter-flash data-title="{{ session('message-title') }}" data-message="{{ session('message') }}" data-type="{{ session('message-type') }}"></div>
        @endif
        <div class="mb-4"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Subject</p><p class="font-semibold text-slate-900">{{ $storePromotion->subject }}</p></div>
        <p class="mb-4 text-sm text-slate-500">Next release: {{ $newsletterReleaseAt->format('l j F, g:ia') }}.</p>
        @php
            $heroProduct = collect($currentStoreSelection['sections'] ?? [])->flatMap(fn ($section) => collect($section['products'] ?? []))->first();
            $heroWorkshop = $newsletterWorkshops->first();
            $defaultHeroImage = $contentOrder === 'store' && $heroProduct ? $heroProduct->primaryImageUrl('lg') : $heroWorkshop?->hero?->url;
        @endphp

        <x-admin.newsletter-editor-dialog id="newsletter-header-editor" title="Edit newsletter header">
            <x-slot:titleActions>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button type="button" variant="plain" class="flex h-8 w-8 items-center justify-center rounded text-slate-600 hover:bg-sky-100 hover:text-sky-800" data-admin-ai data-ai-widget-target="#newsletter-header-ai-toast" data-ai-processing-message="Updating newsletter text…" data-ai-action="newsletter-header" data-ai-url="{{ route('admin.ai.newsletter.header') }}" data-ai-token="{{ csrf_token() }}" data-ai-scope="#newsletter-content-form" data-ai-fields="content_order" data-ai-lock-fields="subject,hero_header,hero_cta" data-ai-lock-controls="#newsletter-header-editor [data-newsletter-refresh], #newsletter-header-editor [name=content_order], #newsletter-header-editor button[type=submit]" :disabled="blank(config('services.openai.api_key'))" aria-label="Replace header text with AI" title="Replace header text with AI"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></x-ui.button>
                </div>
            </x-slot:titleActions>
            <x-admin.ai-status-toast id="newsletter-header-ai-toast" message="Preparing the newsletter header…" detail="The subject, heading, and introduction are locked while AI updates them." progress-label="Newsletter header generation" />
            <div data-newsletter-presentation data-header-copy-options="{{ json_encode($headerCopyOptions) }}">
                <div class="mb-4 grid grid-cols-[minmax(0,1fr)_2.75rem] items-start gap-x-2 gap-y-1">
                    <label for="newsletter-subject" class="col-span-2 block text-sm font-medium text-gray-900">Subject</label>
                    <x-ui.input-control id="newsletter-subject" form="newsletter-content-form" name="subject" class="h-11" :value="old('subject', $storePromotion->subject)" />
                    <x-ui.button type="button" variant="plain" data-newsletter-refresh="subject" class="flex size-11 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-primary-color" title="Refresh subject" aria-label="Refresh subject"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i></x-ui.button>
                    @error('subject')<p class="col-span-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <x-ui.select form="newsletter-content-form" name="content_order" label="Content order">
                    <option value="store" @selected($contentOrder === 'store')>Store sections, then workshops</option>
                    <option value="workshops" @selected($contentOrder === 'workshops')>Workshops, then store sections</option>
                </x-ui.select>
                <div class="mb-4 grid grid-cols-[minmax(0,1fr)_2.75rem] items-start gap-x-2 gap-y-1">
                    <label for="newsletter-hero_header" class="col-span-2 block text-sm font-medium text-gray-900">Hero heading</label>
                    <x-ui.input-control id="newsletter-hero_header" form="newsletter-content-form" name="hero_header" class="h-11" :value="old('hero_header', $storePromotion->hero_header)" />
                    <x-ui.button type="button" variant="plain" data-newsletter-refresh="hero_header" class="flex size-11 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-primary-color" title="Refresh hero heading" aria-label="Refresh hero heading"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i></x-ui.button>
                    @error('hero_header')<p class="col-span-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="mb-4 grid grid-cols-[minmax(0,1fr)_2.75rem] items-start gap-x-2 gap-y-1">
                    <label for="newsletter-hero_cta" class="col-span-2 block text-sm font-medium text-gray-900">Hero introduction</label>
                    <x-ui.textarea-control id="newsletter-hero_cta" form="newsletter-content-form" name="hero_cta" rows="4">{{ old('hero_cta', $storePromotion->hero_cta) }}</x-ui.textarea-control>
                    <x-ui.button type="button" variant="plain" data-newsletter-refresh="hero_cta" class="flex size-11 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-primary-color" title="Refresh hero introduction" aria-label="Refresh hero introduction"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i></x-ui.button>
                    @error('hero_cta')<p class="col-span-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <p class="mb-4 text-xs text-gray-500">The wand replaces all three fields with a draft based on this newsletter’s selected workshops and store items. The refresh arrows suggest one field at a time.</p>
                <input form="newsletter-content-form" type="hidden" id="newsletter-header-image" name="hero_image_name" value="{{ old('hero_image_name', $storePromotion->hero_image_name ?? '') }}" oninput="SMNewsletterPhotoPreview('header')">
                <div class="relative rounded-xl border border-slate-200 p-4">
                    <p class="mb-3 text-sm font-medium">Header image</p>
                    <x-ui.button type="button" variant="plain" id="newsletter-header-image-remove" class="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded text-slate-500 hover:bg-slate-100 hover:text-slate-800" onclick="document.getElementById('newsletter-header-image').value = ''; SMNewsletterPhotoPreview('header')" aria-label="Reset header image to default" title="Reset header image to default" :hidden="!filled(old('hero_image_name', $storePromotion->hero_image_name ?? ''))"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></x-ui.button>
                    <img id="newsletter-header-image-preview" data-default-src="{{ $defaultHeroImage ?? '' }}" @if($currentStoreSelection['hero_image_url'] ?? null) src="{{ $currentStoreSelection['hero_image_url'] }}" @else hidden @endif alt="Selected header image" class="mb-3 h-32 w-full rounded-lg object-cover">
                    <div class="flex justify-center">
                        <x-ui.button type="button" variant="plain" class="border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 shadow-none hover:bg-slate-100 hover:text-slate-800" onclick="SMNewsletterChoosePhoto('header')"><i class="fa-solid fa-arrow-up-from-bracket mr-2" aria-hidden="true"></i>Choose header image</x-ui.button>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">The default image is chosen from the newsletter’s products or workshops. Reset removes your override when you save.</p>
                    @error('hero_image_name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="mt-5 flex justify-end gap-3">
                    <x-ui.button type="button" color="outline" onclick="SMNewsletterCloseEditor(this.closest('dialog'))">Cancel</x-ui.button>
                    <x-ui.button type="submit" form="newsletter-content-form">Save header</x-ui.button>
                </div>
            </div>
        </x-admin.newsletter-editor-dialog>
        @php
            $heroImage = $currentStoreSelection['hero_image_url'] ?? $defaultHeroImage;
        @endphp
        <div data-newsletter-canvas class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-8">
            <header class="relative mb-8 overflow-hidden rounded-xl bg-slate-900 p-6 text-white sm:p-9">
                <x-ui.button type="button" variant="plain" class="absolute right-3 top-3 flex h-10 w-10 items-center justify-center rounded-full bg-white text-slate-900 shadow" onclick="SMNewsletterOpenEditor('newsletter-header-editor')" aria-label="Edit newsletter header" title="Edit newsletter header"><i class="fa-solid fa-pencil" aria-hidden="true"></i></x-ui.button>
                <div class="grid gap-8 sm:grid-cols-[minmax(0,1fr)_minmax(0,0.7fr)]">
                <div>
                <img src="{{ asset('/logo-dark.png') }}" alt="STEMMechanics" class="mb-6 h-9 w-auto">
                <h1 class="max-w-2xl text-3xl font-extrabold tracking-tight text-white sm:text-4xl">{{ $storePromotion->hero_header }}</h1>
                <p class="mt-4 max-w-2xl text-base leading-7 text-slate-300">{{ $storePromotion->hero_cta }}</p>
                </div>
                @if($heroImage)
                    <img src="{{ url($heroImage) }}" alt="" class="hidden h-56 w-full rounded-xl object-cover sm:block">
                @endif
                </div>
            </header>
            @include('admin.newsletter.partials.personal-note')
            <div data-newsletter-content class="@container mx-auto max-w-[780px]">
                @foreach($contentOrder === 'workshops' ? ['workshops', 'products'] : ['products', 'workshops'] as $panel)
                    @include('admin.newsletter.partials.'.$panel)
                @endforeach
            </div>
        </div>

        <div class="mt-6 mb-6 rounded-lg border border-gray-200 bg-white p-4">
            <form method="POST" action="{{ route('admin.subscription.send-test-now') }}" class="flex flex-col gap-4 md:flex-row items-center">
                @csrf
                <div class="w-full md:max-w-lg">
                    <x-ui.input
                        class="mb-0"
                        label="Send test newsletter to email"
                        name="test_email"
                        type="email"
                        value="{{ old('test_email') }}"
                        info="Queues the existing newsletter to this address without creating or updating a subscription."
                    />
                </div>
                <x-ui.button type="submit" color="outline" class="mb-3 w-full sm:w-auto">Send Test Email</x-ui.button>
            </form>
        </div>

        <div class="mb-6 flex justify-end">
            <x-ui.button type="submit" form="newsletter-content-form">Save newsletter</x-ui.button>
        </div>

        </div>
    </x-container>
</x-layout>
<style nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    [data-newsletter-canvas] table { border-collapse: separate; }
    [data-personal-note-editor] .newsletter-note-text { padding-right: 56px !important; }
    @media (max-width: 703px) {
        [data-newsletter-canvas] .newsletter-note-photo,
        [data-newsletter-canvas] .newsletter-note-text { display: block !important; width: 100% !important; }
        [data-newsletter-canvas] .newsletter-note-photo { padding: 0 0 20px !important; }
    }
    @media (max-width: 640px) {
        [data-newsletter-canvas] .mobile-hide { display: none !important; }
        [data-newsletter-canvas] .newsletter-workshop-card__content-cell { width: 100% !important; }
    }
</style>

<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    window.SMNewsletterOpenEditor = function (id) {
        const dialog = document.getElementById(id);
        dialog._originalFields = [...dialog.querySelectorAll('input, textarea, select')].map(field => ({field, value: field.value, checked: field.checked}));
        dialog.showModal();
        dialog.querySelector('input:not([type="hidden"]), textarea, select')?.focus();
    };
    window.SMNewsletterCloseEditor = function (dialog) {
        (dialog._originalFields || []).forEach(({field, value, checked}) => {
            field.value = value;
            if (field.type === 'checkbox') field.checked = checked;
            field.dispatchEvent(new Event('input', {bubbles: true}));
        });
        dialog.close();
    };

    window.SMNewsletterSetContentOrder = function (order) {
        const container = document.querySelector('[data-newsletter-content]');
        if (!container) return;
        const panels = order === 'workshops' ? ['workshops', 'store'] : ['store', 'workshops'];
        panels.forEach(name => {
            const panel = container.querySelector(`[data-newsletter-panel="${name}"]`);
            if (panel) container.appendChild(panel);
        });
    };

    window.SMNewsletterOpenNoteLink = function (detail) {
        window.SMNewsletterNoteLink = detail;
        document.getElementById('newsletter-note-link-url').value = detail.href || '';
        document.getElementById('newsletter-note-link-label').value = detail.label || '';
        document.getElementById('newsletter-note-link-error').hidden = true;
        SMNewsletterOpenEditor('newsletter-note-link-editor');
    };
    window.SMNewsletterApplyNoteLink = function (remove = false) {
        let href = document.getElementById('newsletter-note-link-url').value.trim();
        if (!remove) {
            try {
                if (!/^[a-z][a-z0-9+.-]*:/i.test(href) && !href.startsWith('/')) href = 'https://' + href;
                const url = new URL(href, window.location.href);
                if (!['http:', 'https:', 'mailto:'].includes(url.protocol)) throw new Error('Unsupported link');
                href = url.href;
            } catch (_) {
                document.getElementById('newsletter-note-link-error').hidden = false;
                return;
            }
        }
        document.getElementById('newsletter-note-link-editor').close();
        window.SMNewsletterNoteLink?.apply(remove ? '' : href, document.getElementById('newsletter-note-link-label').value.trim());
        window.SMNewsletterNoteLink = null;
    };

    window.SMNewsletterPhotoPreview = function (target) {
        const name = document.getElementById(`newsletter-${target}-image`).value;
        const preview = document.getElementById(`newsletter-${target}-image-preview`);
        const removeButton = document.getElementById(`newsletter-${target}-image-remove`);
        if (removeButton) removeButton.hidden = !name;
        preview.hidden = true;
        if (!name) {
            const defaultSource = target === 'header' ? preview.dataset.defaultSrc : '';
            if (defaultSource) {
                preview.src = defaultSource;
                preview.hidden = false;
            } else {
                preview.removeAttribute('src');
            }
            return;
        }
        SM.mediaDetails(name, details => {
            if (document.getElementById(`newsletter-${target}-image`).value === name && details?.thumbnail) {
                preview.src = details.thumbnail;
                preview.hidden = false;
            }
        });
    };
    window.SMNewsletterChoosePhoto = async function (target) {
        const dialog = document.getElementById(`newsletter-${target}-editor`);
        const input = document.getElementById(`newsletter-${target}-image`);
        dialog.close();
        try {
            await SMMediaPicker.open(input.value, {require_mime_type: 'image/*', allow_multiple: false, allow_uploads: true, public_usable_only: true, passwordless_only: true}, value => {
                input.value = value || '';
                SMNewsletterPhotoPreview(target);
            });
        } finally {
            dialog.showModal();
        }
    };
    window.SMNewsletterNotePhotoPreview = () => SMNewsletterPhotoPreview('note');
    window.SMNewsletterChooseNotePhoto = () => SMNewsletterChoosePhoto('note');

    window.SMNewsletterCustomState = null;

    window.SMNewsletterOpenCustom = function (form, sectionIndex, select) {
        select.closest('dialog')?.close();
        const dialog = document.getElementById('newsletter-custom-theme-dialog');
        const title = form.querySelector(`[data-section-title="${sectionIndex}"]`);
        const intro = form.querySelector(`[data-section-intro="${sectionIndex}"]`);
        window.SMNewsletterCustomState = { form, sectionIndex, select, previousValue: select.dataset.currentValue || '' };
        document.getElementById('newsletter-custom-title').value = title?.value || '';
        document.getElementById('newsletter-custom-intro').value = intro?.value || '';
        dialog.showModal();
        setTimeout(() => document.getElementById('newsletter-custom-title').focus(), 0);
    };

    window.SMNewsletterCloseCustom = function () {
        const state = window.SMNewsletterCustomState;
        if (state?.select) state.select.value = state.previousValue;
        document.getElementById('newsletter-custom-theme-dialog')?.close();
        window.SMNewsletterCustomState = null;
    };

    window.SMNewsletterSaveCustom = function () {
        const state = window.SMNewsletterCustomState;
        if (!state) return;
        const titleValue = document.getElementById('newsletter-custom-title').value.trim();
        const introValue = document.getElementById('newsletter-custom-intro').value.trim();
        if (!titleValue) {
            SM.alert('Heading required', 'Enter a heading for the custom newsletter section.', 'warning');
            return;
        }

        state.form.querySelector(`[data-section-title="${state.sectionIndex}"]`).value = titleValue;
        state.form.querySelector(`[data-section-intro="${state.sectionIndex}"]`).value = introValue;
        state.form.querySelector(`[data-theme-mode="${state.sectionIndex}"]`).value = 'custom';
        state.form.querySelector(`[data-theme-id="${state.sectionIndex}"]`).value = '';
        document.getElementById('newsletter-custom-theme-dialog').close();
        const { form, sectionIndex, select } = state;
        window.SMNewsletterCustomState = null;
        window.SMNewsletterApplyTheme(form, sectionIndex, select);
    };

    window.SMNewsletterApplyTheme = async function (form, sectionIndex, select) {
        return window.SMNewsletterUpdateSection(form, sectionIndex, 'apply_theme', String(sectionIndex), select);
    };

    window.SMNewsletterUpdateSection = async function (form, sectionIndex, actionName, actionValue, control) {
        const section = form.querySelector(`[data-newsletter-section="${sectionIndex}"]`);
        section?.classList.add('opacity-50', 'pointer-events-none');
        if (section?.querySelector('[data-theme-loading]')) section.querySelector('[data-theme-loading]').hidden = false;
        control.disabled = true;

        try {
            const formData = new FormData(form);
            formData.set(actionName, String(actionValue));
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'text/html',
                    'X-Newsletter-Ajax': '1',
                },
            });
            if (!response.ok) {
                throw new Error(`Theme update failed (${response.status})`);
            }

            const documentResult = new DOMParser().parseFromString(await response.text(), 'text/html');
            const updatedSection = documentResult.querySelector(`[data-newsletter-section="${sectionIndex}"]`);
            if (!updatedSection || !section) {
                throw new Error('The updated newsletter section was not returned.');
            }

            const replacement = document.importNode(updatedSection, true);
            section.replaceWith(replacement);
            window.Alpine?.initTree?.(replacement);

            const flash = documentResult.querySelector('[data-newsletter-flash]');
            if (flash) {
                SM.alert(flash.dataset.title || 'Newsletter updated', flash.dataset.message || 'The section was updated.', flash.dataset.type || 'success');
            }
        } catch (error) {
            section?.classList.remove('opacity-50', 'pointer-events-none');
            if (section?.querySelector('[data-theme-loading]')) section.querySelector('[data-theme-loading]').hidden = true;
            control.disabled = false;
            SM.alert('Newsletter update failed', error.message || 'Please try again.', 'danger');
        }
    };
</script>
