@props(['faqs' => []])

<section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6"
    x-data="{
        faqs: @js(array_values($faqs)),
        nextKey: 0,
        openKey: null,
        init() {
            this.faqs = this.faqs.map(faq => ({ ...faq, _key: this.nextKey++, show_on_index: [true, 1, '1'].includes(faq.show_on_index) }));
        },
        addFaq() {
            const faq = { _key: this.nextKey++, question: '', answer: '', show_on_index: false };
            this.faqs.push(faq);
            this.openKey = faq._key;
            this.$nextTick(() => this.$refs.rows.lastElementChild?.querySelector('input[type=text]')?.focus());
        },
        moveFaq(index, direction) {
            const target = index + direction;
            if (target >= 0 && target < this.faqs.length) {
                const item = this.faqs.splice(index, 1)[0];
                this.faqs.splice(target, 0, item);
            }
        },
        removeFaq(key) {
            if (this.faqs.length <= 1) return;
            SM.confirm('Remove FAQ?', 'This question will be removed when you save the content.', 'Remove', confirmed => {
                if (!confirmed) return;
                this.faqs = this.faqs.filter(faq => faq._key !== key);
                if (this.openKey === key) this.openKey = null;
            });
        }
    }">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="flex items-center gap-2">
                <h2 class="text-xl font-bold text-gray-900">FAQs</h2>
                <x-ui.badge color="gray" x-text="`${faqs.length} ${faqs.length === 1 ? 'question' : 'questions'}`" />
            </div>
            <p class="mt-1 text-sm text-gray-600">Edit questions, change their order and choose which appear on the landing page.</p>
        </div>
        <x-ui.button type="button" color="primary-outline" x-on:click="addFaq()"><i class="fa-solid fa-plus mr-2" aria-hidden="true"></i>Add FAQ</x-ui.button>
    </div>

    @if($errors->has('faqs') || $errors->has('faqs.*'))
        <div role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            @foreach($errors->getMessages() as $field => $messages)
                @if($field === 'faqs' || str_starts_with($field, 'faqs.'))
                    @foreach($messages as $message)<p>{{ $message }}</p>@endforeach
                @endif
            @endforeach
        </div>
    @endif

    <div x-ref="rows" class="divide-y divide-gray-200 overflow-hidden rounded-xl border border-gray-200">
        <template x-for="(faq, index) in faqs" :key="faq._key">
            <div class="bg-white" x-on:invalid.capture.prevent="openKey = faq._key; $nextTick(() => $event.target.focus())">
                <div class="flex flex-wrap items-center gap-3 p-4 hover:bg-gray-50">
                    <x-ui.button variant="plain" type="button" class="flex min-w-0 flex-1 items-start gap-3 text-left focus-visible:outline-primary-color"
                        x-on:click="openKey = openKey === faq._key ? null : faq._key"
                        x-bind:aria-expanded="openKey === faq._key"
                        x-bind:aria-controls="`faq-editor-${faq._key}`">
                        <i class="fa-solid mt-1 w-3 shrink-0 text-slate-500" x-bind:class="openKey === faq._key ? 'fa-chevron-down' : 'fa-chevron-right'" aria-hidden="true"></i>
                        <span class="min-w-0">
                            <span class="block wrap-break-word text-sm font-semibold text-gray-900" x-text="faq.question || 'Untitled question'"></span>
                            <span class="mt-1 block truncate text-xs text-gray-500" x-text="faq.answer || 'Add an answer'"></span>
                        </span>
                    </x-ui.button>
                    <div class="flex w-full items-center justify-between gap-3 sm:w-auto">
                        <x-ui.badge color="gray" x-text="faq.show_on_index ? 'Landing page + FAQ' : 'FAQ page only'" />
                        <div class="flex shrink-0 items-center gap-1">
                            <x-ui.button type="button" color="primary-outline" class="h-8! w-8! p-0!" aria-label="Move FAQ up" title="Move up" x-on:click="moveFaq(index, -1)" x-bind:disabled="index === 0"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></x-ui.button>
                            <x-ui.button type="button" color="primary-outline" class="h-8! w-8! p-0!" aria-label="Move FAQ down" title="Move down" x-on:click="moveFaq(index, 1)" x-bind:disabled="index === faqs.length - 1"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i></x-ui.button>
                            <x-ui.button type="button" color="danger-outline" class="h-8! w-8! p-0!" aria-label="Remove FAQ" title="Remove" x-on:click="removeFaq(faq._key)" x-bind:disabled="faqs.length <= 1"><i class="fa-solid fa-trash" aria-hidden="true"></i></x-ui.button>
                        </div>
                    </div>
                </div>
                <div x-show="openKey === faq._key" x-cloak x-bind:id="`faq-editor-${faq._key}`" class="space-y-4 border-t border-gray-200 bg-gray-50 p-4 sm:p-5">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Question</span>
                        <x-ui.input-control x-model="faq.question" x-bind:name="`faqs[${index}][question]`" required />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Answer</span>
                        <x-ui.textarea-control rows="5" x-model="faq.answer" x-bind:name="`faqs[${index}][answer]`" required />
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="hidden" x-bind:name="`faqs[${index}][show_on_index]`" value="0">
                        <x-ui.checkbox bare small value="1" x-model="faq.show_on_index" x-bind:name="`faqs[${index}][show_on_index]`" />
                        <span>Also show on the landing page</span>
                    </label>
                    <p class="text-xs text-gray-500">Changes are applied when you save the content below.</p>
                </div>
            </div>
        </template>
    </div>
</section>
