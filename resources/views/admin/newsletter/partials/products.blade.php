<section data-newsletter-panel="store" class="mb-8">
    <form id="newsletter-content-form" method="POST" action="{{ route('admin.subscription.store-promotion.update') }}" class="space-y-8">
        @csrf
        @method('PUT')
        @foreach(collect($storePromotion->sections ?? []) as $sectionIndex => $section)
            @php
                $previewSection = collect($currentStoreSelection['sections'] ?? [])->firstWhere('key', $section['key']);
                $previewProducts = collect($previewSection['products'] ?? []);
                $productIds = collect($section['product_ids'] ?? [])->map(fn ($id) => (int) $id)->values();
                $lockedIds = collect($section['locked_product_ids'] ?? [])->map(fn ($id) => (int) $id)->values();
                $categorySlugs = collect($section['category_slugs'] ?? []);
                $showSectionHeading = ($section['theme'] ?? 'managed') !== 'disabled' && ($contentOrder !== 'store' || ! $loop->first);
                $sectionTheme = $storeThemes->firstWhere('id', $section['theme_id'] ?? null);
                $matchDescription = match ($sectionTheme?->match_type) {
                    'created_within' => 'Added in the last '.($sectionTheme->match_days ?: 7).' days.',
                    'updated_within' => 'Updated in the last '.($sectionTheme->match_days ?: 7).' days.',
                    'restocked_within' => 'Restocked in the last '.($sectionTheme->match_days ?: 7).' days.',
                    'featured' => 'Featured products only.',
                    default => 'All available products in this section’s categories.',
                };
            @endphp
            <section data-newsletter-section="{{ $sectionIndex }}" @class(['relative transition-opacity', 'pt-14' => ! $showSectionHeading])>
                <div data-theme-loading hidden class="absolute right-4 top-4 z-10 rounded-full bg-white px-3 py-2 text-sm font-semibold text-primary-color shadow"><i class="fa-solid fa-rotate fa-spin mr-2"></i>Updating</div>
                <input type="hidden" name="sections[{{ $sectionIndex }}][key]" value="{{ $section['key'] }}">
                <input data-section-title="{{ $sectionIndex }}" type="hidden" name="sections[{{ $sectionIndex }}][title]" value="{{ $section['title'] }}">
                <input data-section-intro="{{ $sectionIndex }}" type="hidden" name="sections[{{ $sectionIndex }}][intro]" value="{{ $section['intro'] }}">
                @foreach($categorySlugs as $categorySlug)
                    <input type="hidden" name="sections[{{ $sectionIndex }}][category_slugs][]" value="{{ $categorySlug }}">
                @endforeach
                <div class="absolute right-0 top-0 z-10 flex gap-2" aria-label="Section editing controls">
                    @if($showSectionHeading)
                    <x-ui.button variant="plain" type="button" class="h-10 w-10 rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm" aria-label="Edit section heading" title="Edit section heading" onclick="window.SMNewsletterOpenCustom(this.form, {{ $sectionIndex }}, this.form.querySelector('[data-newsletter-section=&quot;{{ $sectionIndex }}&quot;] select'))"><i class="fa-solid fa-pencil" aria-hidden="true"></i></x-ui.button>
                    @endif
                    <x-ui.button variant="plain" type="button" class="h-10 w-10 rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm" aria-label="Choose section theme" title="Choose section theme" onclick="SMNewsletterOpenEditor('newsletter-section-{{ $sectionIndex }}')"><i class="fa-solid fa-sliders" aria-hidden="true"></i></x-ui.button>
                    <x-ui.button variant="plain" type="button" class="h-10 w-10 rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm" aria-label="Refresh section products" title="Refresh unlocked products" onclick="window.SMNewsletterUpdateSection(this.form, {{ $sectionIndex }}, 'refresh_section', '{{ $sectionIndex }}', this)"><i class="fa-solid fa-rotate" aria-hidden="true"></i></x-ui.button>
                </div>
                <x-admin.newsletter-editor-dialog id="newsletter-section-{{ $sectionIndex }}" title="Choose section theme">
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
                            @if($showSectionHeading && ($section['theme'] ?? 'managed') === 'custom')
                                <x-ui.button variant="plain" type="button" class="hover:underline" onclick="window.SMNewsletterOpenCustom(this.form, {{ $sectionIndex }}, this.form.querySelector('[data-newsletter-section=&quot;{{ $sectionIndex }}&quot;] select'))">Edit custom heading</x-ui.button>
                            @endif
                        </div>
                    </div>
                </div>
                    <p class="mt-5 text-sm text-slate-500">{{ $matchDescription }} Available products matching this theme: {{ $matchingProductCounts[$sectionIndex] }}. Locked products stay selected when refreshing.</p>
                    <div class="mt-6 flex justify-end"><x-ui.button type="button" color="outline" onclick="SMNewsletterCloseEditor(this.closest('dialog'))">Close</x-ui.button></div>
                </x-admin.newsletter-editor-dialog>
                @if(($section['theme'] ?? 'managed') === 'disabled')
                    <div class="mt-4 rounded-lg border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500">This section will not be included in the next newsletter.</div>
                @elseif($showSectionHeading)
                    <div class="pt-14 text-left sm:pt-0">
                        <h3 class="min-h-12 text-2xl font-black tracking-tight text-slate-900 sm:pr-36">{{ $section['title'] }}</h3>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">{{ $section['intro'] }}</p>
                    </div>
                @endif

                @unless(($section['theme'] ?? 'managed') === 'disabled')
                @if($previewProducts->count() < 3)
                    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                        {{ 3 - $previewProducts->count() }} {{ \Illuminate\Support\Str::plural('slot', 3 - $previewProducts->count()) }} still empty. Choose products below, change the theme, or fill from other available products in the same categories. Review the heading if you add products outside the theme.
                        <x-ui.button type="button" color="outline" class="mt-2" onclick="window.SMNewsletterUpdateSection(this.form, {{ $sectionIndex }}, 'fill_empty_slots', '{{ $sectionIndex }}', this)">Fill empty slots</x-ui.button>
                    </div>
                @endif
                <div class="mt-5 space-y-4">
                    @for($slot = 0; $slot < 3; $slot++)
                        @php
                            $selectedProduct = $previewProducts->firstWhere('id', $productIds->get($slot));
                        @endphp
                        <article class="newsletter-editable-card relative" data-newsletter-product="{{ $sectionIndex }}:{{ $slot }}">
                            <div class="absolute right-3 top-3 z-10 flex gap-2">
                                <x-ui.button variant="plain" type="button" class="h-10 w-10 rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm" onclick="SMNewsletterOpenEditor('newsletter-product-{{ $sectionIndex }}-{{ $slot }}')" aria-label="Choose product {{ $slot + 1 }}" title="Choose a different product"><i class="fa-solid fa-pencil" aria-hidden="true"></i></x-ui.button>
                                <x-ui.button variant="plain" type="button" class="h-10 w-10 rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm" onclick="window.SMNewsletterUpdateSection(this.form, {{ $sectionIndex }}, 'refresh_product', '{{ $sectionIndex }}:{{ $slot }}', this)" title="Refresh this product" aria-label="Refresh this product"><i class="fa-solid fa-rotate" aria-hidden="true"></i></x-ui.button>
                                @if($selectedProduct)
                                    <label class="relative flex h-10 w-10 cursor-pointer items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 has-checked:border-primary-color has-checked:bg-primary-color has-checked:text-white" title="Lock this product">
                                        <x-ui.checkbox bare small name="sections[{{ $sectionIndex }}][locked_product_ids][]" value="{{ $selectedProduct->id }}" :checked="$lockedIds->contains((int) $selectedProduct->id)" class="sr-only" />
                                        <i class="fa-solid fa-lock" aria-hidden="true"></i><span class="sr-only">Lock this product</span>
                                    </label>
                                @endif
                            </div>
                            @if($selectedProduct)
                                @include('emails.partials.newsletter-product-card', ['product' => $selectedProduct])
                            @else
                                <div class="mb-6 rounded-xl border border-dashed border-slate-300 px-8 pb-8 pt-16 text-center text-sm text-slate-500">No product selected. Choose or refresh this slot.</div>
                            @endif
                            <x-admin.newsletter-editor-dialog id="newsletter-product-{{ $sectionIndex }}-{{ $slot }}" title="Choose product {{ $slot + 1 }}">
                                <div class="min-h-64">
                                    <x-ui.input name="sections[{{ $sectionIndex }}][product_titles][]" label="Product" :value="$selectedProduct?->title ?? ''" :suggestions="$storeProductsBySection[$sectionIndex]->pluck('title')->all()" showSuggestionsOnFocus="true" />
                                    <p class="text-xs text-slate-500">Choose an available product from this section’s categories.</p>
                                </div>
                                <div class="flex justify-end gap-3">
                                    <x-ui.button type="button" color="outline" onclick="SMNewsletterCloseEditor(this.closest('dialog'))">Cancel</x-ui.button>
                                    <x-ui.button type="button" onclick="window.SMNewsletterUpdateSection(this.form, {{ $sectionIndex }}, 'save_section', '{{ $sectionIndex }}', this)">Use product</x-ui.button>
                                </div>
                            </x-admin.newsletter-editor-dialog>
                        </article>
                    @endfor
                </div>

                @endunless
            </section>
        @endforeach


    </form>
    @if(collect($currentStoreSelection['sections'] ?? [])->isNotEmpty())
        <p class="my-7 text-center"><a href="{{ route('shop.index') }}" class="inline-block rounded-3xl bg-green-600 px-8 py-4 text-lg font-extrabold text-white">Browse the Store</a></p>
    @endif

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
</section>
