@php
    $media = $group['media']->values();
    $fields = collect(\App\Services\MediaDuplicateService::MERGEABLE_METADATA_FIELDS)
        ->reject(fn (string $field): bool => $field === 'status')
        ->values();
    $labels = [
        'title' => 'Title',
        'user_id' => 'Owner',
        'visibility' => 'Visibility',
        'tags' => 'Tags',
        'caption' => 'Caption',
        'consent_notes' => 'Consent notes',
        'photographed_at' => 'Photographed',
        'created_at' => 'Uploaded',
        'password' => 'Password',
    ];
    $normalizedValue = function ($medium, string $field): string {
        $value = $medium->{$field};
        if ($field === 'visibility') {
            return trim((string) ($value ?: 'private'));
        }

        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : trim((string) $value);
    };
    $displayValue = function ($medium, string $field) use ($normalizedValue): string {
        if ($field === 'user_id') {
            return $medium->user?->getName() ?: ($medium->user?->email ?: 'None');
        }
        if (in_array($field, ['photographed_at', 'created_at'], true)) {
            return $medium->{$field}?->format('j M Y, g:i a') ?? 'Not set';
        }
        if ($field === 'password') {
            return trim((string) $medium->password) !== '' ? 'Set' : 'Not set';
        }

        $value = $normalizedValue($medium, $field);
        return $value !== '' ? ($field === 'visibility' ? ucfirst($value) : $value) : 'Not set';
    };
    $differentFields = $fields->filter(
        fn (string $field): bool => $media->map(fn ($medium): string => $normalizedValue($medium, $field))->unique()->count() > 1
    )->values();
    $visibleFields = $fields->reject(fn (string $field): bool => $field === 'title')->filter(
        fn (string $field): bool => $differentFields->contains($field)
            || in_array($field, ['user_id', 'visibility'], true)
            || $media->contains(fn ($medium): bool => $normalizedValue($medium, $field) !== '')
    )->values();
    $defaultKeeper = (string) $media->first()->name;
    $defaultMetadata = $fields->mapWithKeys(fn (string $field): array => [$field => $defaultKeeper])->all();
    $dialogId = 'advanced-media-merge-'.md5($media->pluck('name')->join('|'));
    $removedCount = $media->count() - 1;
    $fileRows = collect([
        ['key' => 'filename', 'label' => 'Filename', 'value' => fn ($medium): string => (string) $medium->name],
        ['key' => 'type', 'label' => 'Type', 'value' => fn ($medium): string => (string) ($medium->mime_type ?: 'Not set')],
        ['key' => 'size', 'label' => 'Size', 'value' => fn ($medium): string => \App\Helpers::bytesToString((int) $medium->size)],
        ['key' => 'storage', 'label' => 'Storage', 'value' => fn ($medium): string => $medium->storageDiskName()],
    ])->map(function (array $row) use ($media): array {
        $row['values'] = $media->mapWithKeys(fn ($medium): array => [(string) $medium->name => $row['value']($medium)]);
        $row['different'] = $row['values']->unique()->count() > 1;

        return $row;
    });
@endphp

<form
    method="POST"
    action="{{ route('admin.media.duplicates.merge') }}"
    x-data="{
        keeper: @js($defaultKeeper),
        advancedKeeper: @js($defaultKeeper),
        metadata: @js($defaultMetadata),
        advancedMetadata: @js($defaultMetadata),
        advancedOpen: false,
        chooseImage(name) {
            this.keeper = name;
            Object.keys(this.metadata).forEach((field) => { this.metadata[field] = name; });
        },
        openAdvanced() {
            this.advancedKeeper = this.keeper;
            this.advancedMetadata = {...this.metadata};
            this.advancedOpen = true;
        },
        closeAdvanced() {
            this.advancedOpen = false;
            this.keeper = this.advancedKeeper;
            this.metadata = {...this.advancedMetadata};
        },
        submitMerge() {
            SM.confirm('Merge these media records?', @js('All detected links will be moved to the selected image. '.$removedCount.' other '.($removedCount === 1 ? 'record' : 'records').' will be removed.'), 'Merge Records', (isConfirmed) => {
                if (isConfirmed) { this.$root.submit(); }
            });
        }
    }"
    x-on:submit.prevent="submitMerge()"
>
    @csrf
    <input type="hidden" name="keeper" x-bind:value="keeper">
    @foreach($media as $medium)
        <input type="hidden" name="members[]" value="{{ $medium->name }}">
    @endforeach
    @foreach($fields as $field)
        <input type="hidden" name="metadata_sources[{{ $field }}]" x-bind:value="metadata[@js($field)]">
    @endforeach

    <div @class([
        'grid items-stretch gap-4 md:grid-cols-2',
        'xl:grid-cols-2' => $media->count() === 2,
        'xl:grid-cols-3' => $media->count() >= 3,
    ])>
        @foreach($media as $medium)
            <div
                class="min-w-0 cursor-pointer rounded-xl p-4 text-left transition"
                x-on:click="chooseImage(@js((string) $medium->name))"
                x-on:keydown.enter.prevent="chooseImage(@js((string) $medium->name))"
                x-bind:class="keeper === @js((string) $medium->name) ? 'bg-primary-color-light/10 ring-2 ring-primary-color' : 'bg-gray-50 hover:bg-gray-100'"
                role="button"
                tabindex="0"
            >
                <div class="flex gap-4">
                    <img src="{{ $medium->thumbnail }}" class="h-28 w-28 shrink-0 rounded-lg object-cover" alt="">
                    <div class="relative min-w-0 flex-1">
                        <div @class(['break-words text-base text-gray-900', 'font-bold' => $differentFields->contains('title'), 'font-semibold' => ! $differentFields->contains('title')])>{{ $medium->title }}</div>
                        <div class="mt-1 break-all text-xs text-gray-500">{{ $medium->name }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ \App\Helpers::bytesToString((int) $medium->size) }} · {{ (int) $medium->usage_count }} detected {{ (int) $medium->usage_count === 1 ? 'use' : 'uses' }}</div>
                        <div class="mt-3 space-y-1 text-xs text-gray-600">
                            @foreach($visibleFields as $field)
                                <div @class(['break-words', 'font-bold text-gray-900' => $differentFields->contains($field)])>
                                    <span>{{ $labels[$field] }}:</span> {{ $displayValue($medium, $field) }}
                                </div>
                            @endforeach
                        </div>
                        <a href="{{ route('admin.media.edit', $medium) }}" target="_blank" rel="noopener" class="absolute right-0 top-0 text-sm font-medium text-primary-color hover:underline" x-on:click.stop aria-label="Open record"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                    </div>
                </div>
                <div class="mt-4 text-center text-sm font-semibold text-primary-color" x-show="keeper === @js((string) $medium->name)">
                    <i class="fa-solid fa-circle-check mr-1"></i> Image selected to keep
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-5 flex flex-wrap justify-end gap-2">
        @if($differentFields->isNotEmpty())
            <x-ui.button type="button" color="outline" x-on:click="openAdvanced()">Advanced merge</x-ui.button>
        @endif
        <x-ui.button type="submit">Merge</x-ui.button>
    </div>

    @if($differentFields->isNotEmpty())
        <div x-show="advancedOpen" x-cloak class="fixed inset-0 z-[280] flex items-end justify-center bg-black/50 p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="{{ $dialogId }}-title" x-on:click.self="closeAdvanced()" x-on:keydown.escape.window="if (advancedOpen) { closeAdvanced() }">
            <div class="flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5">
                    <div>
                        <h3 id="{{ $dialogId }}-title" class="text-xl font-bold text-gray-900">Advanced merge</h3>
                        <p class="mt-1 text-sm text-gray-600">Choose the image record and metadata values to keep.</p>
                    </div>
                    <button type="button" class="text-gray-500 transition hover:text-gray-900" x-on:click="closeAdvanced()" aria-label="Close advanced merge"><i class="fa-solid fa-xmark text-lg"></i></button>
                </div>
                <div class="overflow-auto px-6 py-5">
                    <div class="min-w-max">
                        <div class="grid items-center gap-3 border-b border-gray-200 pb-4 text-sm" style="grid-template-columns: 7rem repeat({{ $media->count() }}, minmax(12rem, 1fr));">
                            <div></div>
                            @foreach($media as $medium)
                                <div class="font-semibold text-gray-900">{{ $medium->title }}</div>
                            @endforeach
                        </div>

                        <div class="border-b border-gray-200 py-4">
                            <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">File details</h4>
                            <div class="divide-y divide-gray-100">
                                @foreach($fileRows as $row)
                                    <div @class(['grid items-center gap-3 py-2 text-sm', 'text-gray-400' => ! $row['different']]) style="grid-template-columns: 7rem repeat({{ $media->count() }}, minmax(12rem, 1fr));">
                                        <div @class(['font-bold', 'text-gray-700' => $row['different']])>{{ $row['label'] }}</div>
                                        @foreach($media as $medium)
                                            @if($row['key'] === 'filename')
                                                <label for="{{ $dialogId }}-filename-{{ $loop->index }}" class="flex min-w-0 items-center gap-2 text-gray-700">
                                                    <input id="{{ $dialogId }}-filename-{{ $loop->index }}" name="{{ $dialogId }}-filename" type="radio" class="text-primary-color focus:ring-primary-color" value="{{ $medium->name }}" x-model="keeper">
                                                    <span class="min-w-0 break-all">{{ $row['values']->get((string) $medium->name) }}</span>
                                                </label>
                                            @else
                                                <div @class(['flex min-w-0 items-center gap-2', 'text-gray-700' => $row['different']])>
                                                    <span class="w-4 shrink-0" aria-hidden="true"></span>
                                                    <span class="min-w-0 break-all">{{ $row['values']->get((string) $medium->name) }}</span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <h4 class="pt-4 text-xs font-semibold uppercase tracking-wide text-gray-500">Record metadata</h4>
                        <div class="divide-y divide-gray-200">
                            @foreach($fields as $field)
                                @php($fieldDiffers = $differentFields->contains($field))
                                <div @class(['grid items-center gap-3 py-3 text-sm', 'text-gray-400' => ! $fieldDiffers]) style="grid-template-columns: 7rem repeat({{ $media->count() }}, minmax(12rem, 1fr));">
                                    <div @class(['font-bold', 'text-gray-700' => $fieldDiffers])>{{ $labels[$field] }}</div>
                                    @foreach($media as $medium)
                                        <label for="{{ $dialogId }}-metadata-{{ $field }}-{{ $loop->index }}" @class(['flex min-w-0 items-center gap-2', 'text-gray-700' => $fieldDiffers])>
                                            @if($fieldDiffers)
                                                <input id="{{ $dialogId }}-metadata-{{ $field }}-{{ $loop->index }}" name="{{ $dialogId }}-metadata-{{ $field }}" type="radio" class="text-primary-color focus:ring-primary-color" value="{{ $medium->name }}" x-model="metadata[@js($field)]">
                                            @else
                                                <span class="w-4 shrink-0" aria-hidden="true"></span>
                                            @endif
                                            <span class="min-w-0 break-words">{{ $displayValue($medium, $field) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap justify-end gap-2 border-t border-gray-200 px-6 py-4">
                    <x-ui.button type="button" color="outline" x-on:click="closeAdvanced()">Cancel</x-ui.button>
                    <x-ui.button type="submit">Merge into selected image</x-ui.button>
                </div>
            </div>
        </div>
    @endif
</form>
