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

            <x-ui.faq-editor :faqs="old('faqs', $faqs ?? [])" />

            <x-ui.editor-actions>
                <x-ui.button color="outline" href="{{ route('stemcraft.index') }}" target="_blank">View Page</x-ui.button>
                <x-ui.button type="submit">Save STEMCraft Content</x-ui.button>
            </x-ui.editor-actions>
        </form>
    </x-container>
</x-layout>
