@php
    $cardClasses = 'rounded-2xl border border-gray-200 bg-white p-6 shadow-sm';
@endphp

<x-layout>
    <x-mast backRoute="stemcraft.index" backTitle="View STEMCraft">STEMCraft Content</x-mast>

    <x-container class="mt-4">
        <form method="POST" action="{{ route('admin.stemcraft-content.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <section class="{{ $cardClasses }}">
                <div class="mb-5">
                    <h2 class="text-xl font-bold text-gray-900">Monthly Challenge</h2>
                    <p class="mt-1 text-sm text-gray-600">Update the current challenge shown on the STEMCraft landing page.</p>
                </div>

                <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <div>
                        <x-ui.input
                            label="Challenge Title"
                            name="monthly_challenge[title]"
                            value="{{ old('monthly_challenge.title', $monthlyChallenge['title'] ?? '') }}"
                            error="{{ $errors->first('monthly_challenge.title') }}"
                        />
                        <x-ui.input
                            type="textarea"
                            label="Description"
                            name="monthly_challenge[description]"
                            rows="4"
                            value="{{ old('monthly_challenge.description', $monthlyChallenge['description'] ?? '') }}"
                            error="{{ $errors->first('monthly_challenge.description') }}"
                            info="Supports basic Markdown such as **bold**, links and bullet lists."
                        />
                        <x-ui.input
                            type="textarea"
                            label="Prompt"
                            name="monthly_challenge[prompt]"
                            rows="3"
                            value="{{ old('monthly_challenge.prompt', $monthlyChallenge['prompt'] ?? '') }}"
                            error="{{ $errors->first('monthly_challenge.prompt') }}"
                            info="Supports basic Markdown for short instructions or dot points."
                        />
                    </div>

                    <div>
                        <x-ui.media
                            label="Challenge Image"
                            name="monthly_challenge[image]"
                            value="{{ old('monthly_challenge.image', $monthlyChallenge['image'] ?? '') }}"
                            allow_uploads="true"
                        />
                        <x-ui.input
                            label="Image Alt Text"
                            name="monthly_challenge[image_alt]"
                            value="{{ old('monthly_challenge.image_alt', $monthlyChallenge['image_alt'] ?? '') }}"
                            error="{{ $errors->first('monthly_challenge.image_alt') }}"
                        />
                    </div>
                </div>
            </section>

            <section class="{{ $cardClasses }}">
                <div class="mb-5">
                    <h2 class="text-xl font-bold text-gray-900">Community Builds</h2>
                    <p class="mt-1 text-sm text-gray-600">Update the text and images for the three build cards on the STEMCraft landing page.</p>
                </div>

                <div class="grid gap-6">
                    @foreach([1, 2, 3] as $index)
                        @php
                            $build = $communityBuilds[$index] ?? [];
                            $titleName = "builds[{$index}][title]";
                            $descriptionName = "builds[{$index}][description]";
                            $imageName = "builds[{$index}][image]";
                            $imageAltName = "builds[{$index}][image_alt]";
                            $titleValue = old("builds.{$index}.title", $build['title'] ?? '');
                            $descriptionValue = old("builds.{$index}.description", $build['description'] ?? '');
                            $imageValue = old("builds.{$index}.image", $build['image'] ?? '');
                            $imageAltValue = old("builds.{$index}.image_alt", $build['image_alt'] ?? '');
                        @endphp
                        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5">
                            <h3 class="mb-4 text-lg font-semibold text-gray-900">Build Card {{ $index }} Options</h3>

                            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
                                <div>
                                    <x-ui.input
                                        :label="'Build Card '.$index.' Title'"
                                        :name="$titleName"
                                        :value="$titleValue"
                                        :error="$errors->first('builds.'.$index.'.title')"
                                    />
                                    <x-ui.input
                                        type="textarea"
                                        :label="'Build Card '.$index.' Description'"
                                        :name="$descriptionName"
                                        rows="4"
                                        :value="$descriptionValue"
                                        :error="$errors->first('builds.'.$index.'.description')"
                                    />
                                </div>

                                <div>
                                    <x-ui.media
                                        :label="'Build Card '.$index.' Image'"
                                        :name="$imageName"
                                        :value="$imageValue"
                                        allow_uploads="true"
                                    />
                                    <x-ui.input
                                        :label="'Build Card '.$index.' Image Alt Text'"
                                        :name="$imageAltName"
                                        :value="$imageAltValue"
                                        :error="$errors->first('builds.'.$index.'.image_alt')"
                                    />
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section
                class="{{ $cardClasses }}"
                x-data="{
                    faqs: @js(old('faqs', $faqs ?? [])),
                    newFaq() {
                        return {
                            question: '',
                            answer: '',
                            show_on_index: false,
                        };
                    },
                    addFaq() {
                        this.faqs.push(this.newFaq());
                    },
                    removeFaq(index) {
                        if (this.faqs.length <= 1) {
                            return;
                        }

                        this.faqs.splice(index, 1);
                    },
                    moveFaq(index, direction) {
                        const target = index + direction;

                        if (target < 0 || target >= this.faqs.length) {
                            return;
                        }

                        const item = this.faqs.splice(index, 1)[0];
                        this.faqs.splice(target, 0, item);
                    },
                }"
            >
                <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">FAQs</h2>
                        <p class="mt-1 text-sm text-gray-600">Edit the STEMCraft FAQ page and choose which questions appear on the landing page.</p>
                    </div>

                    <x-ui.button type="button" color="outline" x-on:click="addFaq()">Add FAQ</x-ui.button>
                </div>

                @error('faqs')
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
                @enderror

                <div class="space-y-5">
                    <template x-for="(faq, index) in faqs" :key="index">
                        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5">
                            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <h3 class="text-lg font-semibold text-gray-900" x-text="`FAQ ${index + 1}`"></h3>

                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-50"
                                        x-on:click="moveFaq(index, -1)"
                                        x-bind:disabled="index === 0"
                                    >
                                        Move up
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-50"
                                        x-on:click="moveFaq(index, 1)"
                                        x-bind:disabled="index === faqs.length - 1"
                                    >
                                        Move down
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md border border-red-300 bg-white px-3 py-1.5 text-sm font-semibold text-red-700 shadow-sm transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
                                        x-on:click="removeFaq(index)"
                                        x-bind:disabled="faqs.length <= 1"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>

                            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_14rem]">
                                <div class="space-y-4">
                                    <label class="block">
                                        <span class="mb-1 block pl-1 text-sm text-gray-700">Question</span>
                                        <input
                                            type="text"
                                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm transition focus:border-indigo-300 focus:outline-none focus:ring-0"
                                            x-model="faq.question"
                                            x-bind:name="`faqs[${index}][question]`"
                                            required
                                        >
                                    </label>

                                    <label class="block">
                                        <span class="mb-1 block pl-1 text-sm text-gray-700">Answer</span>
                                        <textarea
                                            class="block min-h-28 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm transition focus:border-indigo-300 focus:outline-none focus:ring-0"
                                            x-model="faq.answer"
                                            x-bind:name="`faqs[${index}][answer]`"
                                            required
                                        ></textarea>
                                    </label>
                                </div>

                                <label class="flex h-fit items-start gap-3 rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700">
                                    <input type="hidden" x-bind:name="`faqs[${index}][show_on_index]`" value="0">
                                    <input
                                        type="checkbox"
                                        value="1"
                                        class="mt-0.5 h-4 w-4 rounded border-gray-300 text-sky-600 focus:ring-sky-500"
                                        x-model="faq.show_on_index"
                                        x-bind:name="`faqs[${index}][show_on_index]`"
                                    >
                                    <span>
                                        <span class="block font-medium text-gray-900">Show on landing page</span>
                                        <span class="mt-1 block text-xs leading-5 text-gray-500">Unchecked items still appear on the full FAQ page.</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </template>
                </div>
            </section>

            <div class="flex justify-end gap-4">
                <x-ui.button color="outline" href="{{ route('stemcraft.index') }}" target="_blank">View Page</x-ui.button>
                <x-ui.button type="submit">Save STEMCraft Content</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
