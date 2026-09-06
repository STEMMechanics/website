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
    <x-container class="py-5 sm:py-8">
        <div data-list-preserve="newsletter-settings">

        <div class="mb-6 rounded-lg border border-gray-200 bg-white p-5">
            @if(session('message'))
                <div hidden data-newsletter-flash data-title="{{ session('message-title') }}" data-message="{{ session('message') }}" data-type="{{ session('message-type') }}"></div>
            @endif
            <h2 class="text-lg font-semibold text-gray-900">Next newsletter store picks</h2>
            <p class="mt-1 text-sm text-gray-600">Two sections of up to three products. Refreshing replaces only unlocked products. Locking a product also locks that section’s category until the newsletter is released.</p>

            <form method="POST" action="{{ route('admin.subscription.store-promotion.update') }}" class="mt-5 space-y-6 border-t border-gray-200 pt-5">
                @csrf
                @method('PUT')
                <div class="grid gap-4 lg:grid-cols-2">
                    <x-ui.input name="subject" label="Subject" :value="$storePromotion->subject" />
                    <x-ui.select name="content_order" label="Content order">
                        <option value="store" @selected($storePromotion->content_order === 'store')>Store sections, then workshops</option>
                        <option value="workshops" @selected($storePromotion->content_order === 'workshops')>Workshops, then store sections</option>
                    </x-ui.select>
                    <x-ui.input name="hero_header" label="Hero heading" :value="$storePromotion->hero_header" />
                    <x-ui.input name="hero_cta" label="Hero introduction" :value="$storePromotion->hero_cta" />
                </div>
                @foreach(collect($storePromotion->sections ?? []) as $sectionIndex => $section)
                    @php
                        $previewSection = collect($currentStoreSelection['sections'] ?? [])->firstWhere('key', $section['key']);
                        $previewProducts = collect($previewSection['products'] ?? []);
                        $productIds = collect($section['product_ids'] ?? [])->map(fn ($id) => (int) $id)->values();
                        $lockedIds = collect($section['locked_product_ids'] ?? [])->map(fn ($id) => (int) $id)->values();
                        $categorySlugs = collect($section['category_slugs'] ?? []);
                    @endphp
                    <section data-newsletter-section="{{ $sectionIndex }}" class="relative rounded-xl border border-gray-200 bg-gray-50 p-4 transition-opacity">
                        <div data-theme-loading hidden class="absolute right-4 top-4 z-10 rounded-full bg-white px-3 py-2 text-sm font-semibold text-primary-color shadow"><i class="fa-solid fa-rotate fa-spin mr-2"></i>Updating</div>
                        <input type="hidden" name="sections[{{ $sectionIndex }}][key]" value="{{ $section['key'] }}">
                        <input data-section-title="{{ $sectionIndex }}" type="hidden" name="sections[{{ $sectionIndex }}][title]" value="{{ $section['title'] }}">
                        <input data-section-intro="{{ $sectionIndex }}" type="hidden" name="sections[{{ $sectionIndex }}][intro]" value="{{ $section['intro'] }}">
                        @foreach($categorySlugs as $categorySlug)
                            <input type="hidden" name="sections[{{ $sectionIndex }}][category_slugs][]" value="{{ $categorySlug }}">
                        @endforeach
                        <div class="max-w-xl">
                            <div>
                                <input data-theme-mode="{{ $sectionIndex }}" type="hidden" name="sections[{{ $sectionIndex }}][theme]" value="{{ $section['theme'] ?? 'managed' }}">
                                <input data-theme-id="{{ $sectionIndex }}" type="hidden" name="sections[{{ $sectionIndex }}][theme_id]" value="{{ $section['theme_id'] ?? '' }}">
                                <x-ui.select label="Section theme" data-current-value="{{ ($section['theme'] ?? 'managed') === 'managed' ? 'theme:'.($section['theme_id'] ?? '') : ($section['theme'] ?? 'managed') }}" onchange="if (this.value === 'custom') { window.SMNewsletterOpenCustom(this.form, {{ $sectionIndex }}, this); } else { const mode = this.value === 'disabled' ? 'disabled' : 'managed'; this.form.querySelector('[data-theme-mode=&quot;{{ $sectionIndex }}&quot;]').value = mode; this.form.querySelector('[data-theme-id=&quot;{{ $sectionIndex }}&quot;]').value = mode === 'managed' ? this.value.replace('theme:', '') : ''; window.SMNewsletterApplyTheme(this.form, {{ $sectionIndex }}, this); }">
                                    @foreach($storeThemes as $theme)
                                        <option value="theme:{{ $theme->id }}" @selected(($section['theme'] ?? 'managed') === 'managed' && (int) ($section['theme_id'] ?? 0) === $theme->id)>{{ $theme->name }}</option>
                                    @endforeach
                                    <option value="custom" @selected(($section['theme'] ?? 'managed') === 'custom')>Custom…</option>
                                    <option value="disabled" @selected(($section['theme'] ?? 'managed') === 'disabled')>Do not include this section</option>
                                </x-ui.select>
                                <div class="-mt-2 flex gap-4 text-xs font-medium text-primary-color">
                                    <a href="{{ route('admin.subscription.theme.index') }}" class="hover:underline">Manage newsletter themes</a>
                                    @if(($section['theme'] ?? 'managed') === 'custom')
                                        <x-ui.button variant="plain" type="button" class="hover:underline" onclick="window.SMNewsletterOpenCustom(this.form, {{ $sectionIndex }}, this.form.querySelector('[data-newsletter-section=&quot;{{ $sectionIndex }}&quot;] select'))">Edit custom heading</x-ui.button>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @if(($section['theme'] ?? 'managed') === 'disabled')
                            <div class="mt-4 rounded-lg border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500">This section will not be included in the next newsletter.</div>
                        @else
                            <div class="mt-2 text-left">
                                <h3 class="text-2xl font-black tracking-tight text-slate-900">{{ $section['title'] }}</h3>
                                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">{{ $section['intro'] }}</p>
                            </div>
                        @endif

                        @unless(($section['theme'] ?? 'managed') === 'disabled')
                        <div class="mt-5 space-y-4">
                            @for($slot = 0; $slot < 3; $slot++)
                                @php
                                    $selectedProduct = $previewProducts->get($slot);
                                @endphp
                                <article class="overflow-hidden rounded-xl border border-slate-200 border-l-4 border-l-green-600 bg-white shadow-sm md:flex">
                                    @if($selectedProduct)
                                        <img src="{{ $selectedProduct->primaryImageUrl() }}" alt="{{ $selectedProduct->title }}" class="h-48 w-full object-cover md:h-auto md:w-52">
                                        <div class="flex min-w-0 flex-1 flex-col p-5">
                                            <div class="text-xl font-extrabold text-slate-900">{{ $selectedProduct->title }}</div>
                                            <div class="mt-2 text-sm leading-6 text-slate-500">{{ \Illuminate\Support\Str::limit($selectedProduct->short_description ?: $selectedProduct->subtitle, 105) }}</div>
                                            <div class="mt-auto flex items-end justify-between gap-3 border-t border-slate-200 pt-4">
                                                <div class="text-lg font-black text-slate-900">{{ $selectedProduct->priceRangeLabel() }}</div>
                                                <span class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-extrabold text-white">View product</span>
                                            </div>
                                        </div>
                                    @else
                                        <div class="flex h-40 flex-1 items-center justify-center text-sm text-gray-500">No product selected</div>
                                    @endif
                                    <div class="flex gap-2 border-t border-gray-200 p-3 md:w-72 md:flex-col md:border-l md:border-t-0">
                                        <x-ui.input name="sections[{{ $sectionIndex }}][product_titles][]" label="Product {{ $slot + 1 }}" :value="$selectedProduct?->title ?? ''" :suggestions="$storeProducts->pluck('title')->all()" showSuggestionsOnFocus="true" class="mb-0 flex-1" />
                                        <div class="flex gap-2">
                                            <x-ui.button variant="plain" type="button" onclick="window.SMNewsletterUpdateSection(this.form, {{ $sectionIndex }}, 'refresh_product', '{{ $sectionIndex }}:{{ $slot }}', this)" class="flex h-10 w-10 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 hover:border-primary-color hover:text-primary-color" title="Refresh this product" aria-label="Refresh this product"><i class="fa-solid fa-rotate"></i></x-ui.button>
                                        @if($selectedProduct)
                                            <label class="relative flex h-10 w-10 cursor-pointer items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-500 has-checked:border-primary-color has-checked:bg-primary-color has-checked:text-white" title="Lock this product">
                                                <x-ui.checkbox bare small name="sections[{{ $sectionIndex }}][locked_product_ids][]" value="{{ $selectedProduct->id }}" :checked="$lockedIds->contains((int) $selectedProduct->id)" class="sr-only" />
                                                <i class="fa-solid fa-lock"></i><span class="sr-only">Lock this product</span>
                                            </label>
                                        @endif
                                        </div>
                                    </div>
                                </article>
                            @endfor
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-ui.button type="button" onclick="window.SMNewsletterUpdateSection(this.form, {{ $sectionIndex }}, 'refresh_section', '{{ $sectionIndex }}', this)" color="outline"><i class="fa-solid fa-rotate mr-2"></i>Refresh all unlocked products</x-ui.button>
                        </div>
                        @endunless
                    </section>
                @endforeach

                <div>
                    <x-ui.button type="submit">Save Next Newsletter</x-ui.button>
                </div>
            </form>

            <dialog id="newsletter-custom-theme-dialog" class="m-auto w-[min(42rem,calc(100%-2rem))] rounded-xl bg-white p-0 shadow-2xl backdrop:bg-black/50" oncancel="event.preventDefault(); window.SMNewsletterCloseCustom();">
                <form method="dialog" class="p-6" onsubmit="return false;">
                    <div class="flex items-start justify-between gap-4">
                        <div><h3 class="text-xl font-bold text-gray-900">Custom newsletter section</h3><p class="mt-1 text-sm text-gray-500">Set the heading and introduction, then choose each product directly.</p></div>
                        <x-ui.button variant="plain" type="button" class="text-gray-500 hover:text-gray-900" onclick="window.SMNewsletterCloseCustom()" aria-label="Close"><i class="fa-solid fa-xmark text-xl"></i></x-ui.button>
                    </div>
                    <div class="mt-5">
                        <label for="newsletter-custom-title" class="mb-1 block text-sm font-medium text-gray-700">Heading</label>
                        <x-ui.input-control id="newsletter-custom-title" type="text" maxlength="120" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-300 focus:ring-indigo-300" />
                    </div>
                    <div class="mt-4">
                        <label for="newsletter-custom-intro" class="mb-1 block text-sm font-medium text-gray-700">Introduction</label>
                        <x-ui.textarea-control id="newsletter-custom-intro" rows="4" maxlength="400" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-300 focus:ring-indigo-300"></x-ui.textarea-control>
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <x-ui.button type="button" color="outline" onclick="window.SMNewsletterCloseCustom()">Cancel</x-ui.button>
                        <x-ui.button type="button" onclick="window.SMNewsletterSaveCustom()">Use Custom Section</x-ui.button>
                    </div>
                </form>
            </dialog>
        </div>

        <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
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

        </div>
    </x-container>
</x-layout>

<script>
    window.SMNewsletterCustomState = null;

    window.SMNewsletterOpenCustom = function (form, sectionIndex, select) {
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
