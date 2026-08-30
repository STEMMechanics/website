<x-layout>
    <x-mast>Email Subscriptions</x-mast>

    <x-container>
        <x-ui.toolbar>
            <x-slot:left>
                <div class="flex flex-col sm:flex-row items-center gap-2">
                    <x-ui.button href="{{ route('admin.subscription.create') }}" class="w-full sm:w-auto">Register</x-ui.button>
                    <form class="w-full" method="POST" action="{{ route('admin.subscription.send-all-now') }}" x-data x-on:submit.prevent="SM.confirm('Queue newsletter?', 'Queue newsletter for all confirmed subscriptions now?', 'Queue Newsletter', (isConfirmed) => { if (isConfirmed) { $el.submit(); } })">
                        @csrf
                        <x-ui.button type="submit" color="outline" class="w-full sm:w-auto">Send All Now</x-ui.button>
                    </form>
                </div>
            </x-slot:left>
            <x-slot:right>
                <x-ui.search name="search" label="Search" />
            </x-slot:right>
        </x-ui.toolbar>

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
                                    <a href="{{ route('admin.subscription.theme.index') }}" class="hover:underline">Manage store themes</a>
                                    @if(($section['theme'] ?? 'managed') === 'custom')
                                        <button type="button" class="hover:underline" onclick="window.SMNewsletterOpenCustom(this.form, {{ $sectionIndex }}, this.form.querySelector('[data-newsletter-section=&quot;{{ $sectionIndex }}&quot;] select'))">Edit custom heading</button>
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
                                            <button type="button" onclick="window.SMNewsletterUpdateSection(this.form, {{ $sectionIndex }}, 'refresh_product', '{{ $sectionIndex }}:{{ $slot }}', this)" class="flex h-10 w-10 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 hover:border-primary-color hover:text-primary-color" title="Refresh this product" aria-label="Refresh this product"><i class="fa-solid fa-rotate"></i></button>
                                        @if($selectedProduct)
                                            <label class="relative flex h-10 w-10 cursor-pointer items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-500 has-[:checked]:border-primary-color has-[:checked]:bg-primary-color has-[:checked]:text-white" title="Lock this product">
                                                <input type="checkbox" name="sections[{{ $sectionIndex }}][locked_product_ids][]" value="{{ $selectedProduct->id }}" @checked($lockedIds->contains((int) $selectedProduct->id)) class="sr-only">
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
                        <button type="button" class="text-gray-500 hover:text-gray-900" onclick="window.SMNewsletterCloseCustom()" aria-label="Close"><i class="fa-solid fa-xmark text-xl"></i></button>
                    </div>
                    <div class="mt-5">
                        <label for="newsletter-custom-title" class="mb-1 block text-sm font-medium text-gray-700">Heading</label>
                        <input id="newsletter-custom-title" type="text" maxlength="120" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-300 focus:ring-indigo-300">
                    </div>
                    <div class="mt-4">
                        <label for="newsletter-custom-intro" class="mb-1 block text-sm font-medium text-gray-700">Introduction</label>
                        <textarea id="newsletter-custom-intro" rows="4" maxlength="400" class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-300 focus:ring-indigo-300"></textarea>
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

        @if($subscriptions->isEmpty())
            <x-none-found item="subscriptions" search="{{ request()->get('search') }}" />
        @else
            <div class="space-y-4 md:hidden">
                @foreach ($subscriptions as $subscription)
                    @php
                        $latestNewsletter = $latestNewsletterByEmail->get(strtolower(trim((string) $subscription->email)));
                        $newsletterStatus = (string) ($latestNewsletter->status ?? '');
                        $statusTimestamp = $newsletterStatus === \App\Models\SentEmail::STATUS_SENT
                            ? ($latestNewsletter->sent_at ?? $latestNewsletter->created_at)
                            : ($newsletterStatus === \App\Models\SentEmail::STATUS_FAILED
                                ? ($latestNewsletter->failed_at ?? $latestNewsletter->created_at)
                                : $latestNewsletter?->created_at);
                        $statusLabel = $newsletterStatus === \App\Models\SentEmail::STATUS_FAILED
                            ? 'Failed'
                            : ($newsletterStatus === \App\Models\SentEmail::STATUS_SENT ? 'Sent' : 'Queued');
                        $statusTone = \App\Models\SentEmail::statusBadgeToneFor($newsletterStatus);
                    @endphp

                    <article class="rounded-2xl border border-gray-200 bg-white p-3 shadow-sm">
                        <div class="min-w-0">
                            <div class="break-all text-sm font-semibold leading-5 text-gray-900">{{ $subscription->email }}</div>
                            <div class="mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs text-gray-500">
                                <span>{{ $subscription->confirmed ? 'Registered '.\Carbon\Carbon::parse($subscription->confirmed)->format('j M Y') : 'Not confirmed yet' }}</span>
                                @if($subscription->confirmed)
                                    <span class="text-gray-300">•</span>
                                    <span class="font-medium text-gray-700">{{ \Carbon\Carbon::parse($subscription->confirmed)->format('g:i a') }}</span>
                                @endif
                            </div>
                            <div class="mt-2 flex flex-row items-center gap-2">
                                <div class="text-xs font-semibold text-gray-500">Last newsletter: </div>
                                @if($latestNewsletter === null)
                                    <div class="mt-0.5 text-xs text-gray-700">No newsletter sent yet</div>
                                @else
                                    @if($statusTimestamp)
                                        <div class="mt-0.5 text-xs text-gray-500">{{ $statusTimestamp->format('M j Y, g:i a') }}</div>
                                    @endif
                                    <x-ui.badge :color="$statusTone" size="xs">{{ $statusLabel }}</x-ui.badge>
                                    @if($newsletterStatus === \App\Models\SentEmail::STATUS_FAILED && ! empty($latestNewsletter->error_message))
                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ \Illuminate\Support\Str::limit($latestNewsletter->error_message, 120) }}
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>
                        <div class="mt-2.5 grid grid-cols-3 gap-1.5 sm:grid-cols-3">
                            @if($subscription->confirmed)
                                <form method="POST" action="{{ route('admin.subscription.send-now', $subscription) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-md border border-primary-color bg-white px-2 py-2.5 text-xs font-semibold text-primary-color transition hover:bg-primary-color hover:text-white" title="Send newsletter now">
                                        <i class="fa-solid fa-paper-plane"></i>
                                    </button>
                                </form>
                            @else
                                <button type="button" class="inline-flex w-full cursor-not-allowed items-center justify-center rounded-md border border-gray-200 bg-gray-100 px-2 py-2.5 text-xs font-semibold text-gray-400" disabled title="Confirm subscription before sending">
                                    <i class="fa-solid fa-paper-plane"></i>
                                </button>
                            @endif

                            <a href="{{ route('admin.subscription.edit', $subscription) }}" class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 bg-white px-2 py-2.5 text-xs font-semibold text-gray-700 transition hover:border-primary-color hover:text-primary-color" title="Edit">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>

                            <a href="#" class="inline-flex w-full items-center justify-center rounded-md border border-red-200 bg-red-50 px-2 py-2.5 text-xs font-semibold text-red-700 transition hover:bg-red-600 hover:text-white" title="Delete" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete subscription?', 'Are you sure you want to delete this subscription? This action cannot be undone', '{{ route('admin.subscription.destroy', $subscription) }}')">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="hidden md:block">
            <x-ui.table>
                <x-slot:header>
                    <th>Email</th>
                    <th>Registered On</th>
                    <th>Last Newsletter</th>
                    <th>Action</th>
                </x-slot:header>
                <x-slot:body>
                    @foreach ($subscriptions as $subscription)
                        @php
                            $latestNewsletter = $latestNewsletterByEmail->get(strtolower(trim((string) $subscription->email)));
                            $newsletterStatus = (string) ($latestNewsletter->status ?? '');
                            $statusTimestamp = $newsletterStatus === \App\Models\SentEmail::STATUS_SENT
                                ? ($latestNewsletter->sent_at ?? $latestNewsletter->created_at)
                                : ($newsletterStatus === \App\Models\SentEmail::STATUS_FAILED
                                    ? ($latestNewsletter->failed_at ?? $latestNewsletter->created_at)
                                    : $latestNewsletter?->created_at);
                            $statusLabel = $newsletterStatus === \App\Models\SentEmail::STATUS_FAILED
                                ? 'Failed'
                                : ($newsletterStatus === \App\Models\SentEmail::STATUS_SENT ? 'Sent' : 'Queued');
                            $statusClass = $newsletterStatus === \App\Models\SentEmail::STATUS_FAILED
                                ? 'text-red-700 bg-red-100 border-red-200'
                                : ($newsletterStatus === \App\Models\SentEmail::STATUS_SENT
                                    ? 'text-green-700 bg-green-100 border-green-200'
                                    : 'text-amber-700 bg-amber-100 border-amber-200');
                        @endphp
                        <tr>
                            <td>
                                <div class="whitespace-normal">{{ $subscription->email }}</div>
                            </td>
                            <td>
                                {{ $subscription->confirmed ? \Carbon\Carbon::parse($subscription->confirmed)->format('M j Y, g:i a') : '-' }}
                            </td>
                            <td>
                                @if($latestNewsletter === null)
                                    -
                                @else
                                    <x-ui.badge :color="$statusTone" size="xs">{{ $statusLabel }}</x-ui.badge>
                                    @if($statusTimestamp)
                                        <div class="mt-1 text-xs text-gray-500">{{ $statusTimestamp->format('M j Y, g:i a') }}</div>
                                    @endif
                                    @if($newsletterStatus === \App\Models\SentEmail::STATUS_FAILED && ! empty($latestNewsletter->error_message))
                                        <div class="mt-1 max-w-xs truncate text-xs text-gray-500" title="{{ $latestNewsletter->error_message }}">
                                            {{ \Illuminate\Support\Str::limit($latestNewsletter->error_message, 80) }}
                                        </div>
                                    @endif
                                @endif
                            </td>
                            <td>
                                <div class="flex justify-center gap-3">
                                    @if($subscription->confirmed)
                                        <form method="POST" action="{{ route('admin.subscription.send-now', $subscription) }}">
                                            @csrf
                                            <button type="submit" class="hover:text-primary-color" title="Send newsletter now">
                                                <i class="fa-solid fa-paper-plane"></i>
                                            </button>
                                        </form>
                                    @else
                                        <span class="cursor-not-allowed text-gray-300" title="Confirm subscription before sending">
                                            <i class="fa-solid fa-paper-plane"></i>
                                        </span>
                                    @endif
                                    <a href="{{ route('admin.subscription.edit', $subscription) }}" class="hover:text-primary-color" title="Edit">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <a href="#" class="hover:text-red-600" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete subscription?', 'Are you sure you want to delete this subscription? This action cannot be undone', '{{ route('admin.subscription.destroy', $subscription) }}')">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-ui.table>
            </div>

            {{ $subscriptions->appends(request()->query())->links() }}
        @endif
    </x-container>
@push('scripts')
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
@endpush
</x-layout>
