@php
    $fields = collect(\App\Services\MediaDuplicateService::MERGEABLE_METADATA_FIELDS)
        ->reject(fn (string $field): bool => $field === 'status')
        ->values()
        ->all();
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
        if ($field === 'photographed_at' || $field === 'created_at') {
            return $medium->{$field}?->format('j M Y, g:i a') ?? 'Not set';
        }
        if ($field === 'password') {
            return trim((string) $medium->password) !== '' ? 'Set' : 'Not set';
        }

        $value = $normalizedValue($medium, $field);
        return $value !== '' ? ($field === 'visibility' || $field === 'status' ? ucfirst($value) : $value) : 'Not set';
    };
    $differentFields = collect($fields)->filter(
        fn (string $field): bool => $normalizedValue($left, $field) !== $normalizedValue($right, $field)
    )->values();
    $visibleFields = collect($fields)->reject(fn (string $field): bool => $field === 'title')->filter(
        fn (string $field): bool => $differentFields->contains($field)
            || in_array($field, ['user_id', 'visibility'], true)
            || $normalizedValue($left, $field) !== ''
            || $normalizedValue($right, $field) !== ''
    )->values();
    $defaultMetadata = collect($fields)->mapWithKeys(fn (string $field): array => [$field => (string) $left->name])->all();
    $dialogId = 'advanced-media-merge-'.md5((string) $left->name.'|'.(string) $right->name);
    $fileRows = collect([
        ['label' => 'Filename', 'left' => (string) $left->name, 'right' => (string) $right->name],
        ['label' => 'Type', 'left' => (string) ($left->mime_type ?: 'Not set'), 'right' => (string) ($right->mime_type ?: 'Not set')],
        ['label' => 'Size', 'left' => \App\Helpers::bytesToString((int) $left->size), 'right' => \App\Helpers::bytesToString((int) $right->size)],
        ['label' => 'Storage', 'left' => $left->storageDiskName(), 'right' => $right->storageDiskName()],
    ])->map(fn (array $row): array => [...$row, 'different' => $row['left'] !== $row['right']]);
@endphp

@if($canMerge)
    <form
        method="POST"
        action="{{ $mergeRoute }}"
        x-data="{
            keeper: @js((string) $left->name),
            advancedKeeper: @js((string) $left->name),
            metadata: @js($defaultMetadata),
            advancedOpen: false,
            chooseImage(name) {
                this.keeper = name;
                Object.keys(this.metadata).forEach((field) => { this.metadata[field] = name; });
            },
            openAdvanced() {
                this.advancedKeeper = this.keeper;
                Object.keys(this.metadata).forEach((field) => { this.metadata[field] = this.keeper; });
                this.advancedOpen = true;
            },
            closeAdvanced() {
                this.advancedOpen = false;
                this.keeper = this.advancedKeeper;
                Object.keys(this.metadata).forEach((field) => { this.metadata[field] = this.keeper; });
            },
            submitMerge() {
                SM.confirm('Merge these media records?', 'All detected links will be moved to the selected record. The other record will be removed.', 'Merge Records', (isConfirmed) => {
                    if (isConfirmed) { this.$root.submit(); }
                });
            }
        }"
        x-on:submit.prevent="submitMerge()"
    >
        @csrf
        @if($isSimilar)
            <input type="hidden" name="first" x-bind:value="keeper">
            <input type="hidden" name="second" x-bind:value="keeper === @js((string) $left->name) ? @js((string) $right->name) : @js((string) $left->name)">
        @else
            <input type="hidden" name="keeper" x-bind:value="keeper">
            <input type="hidden" name="members[]" value="{{ $left->name }}">
            <input type="hidden" name="members[]" value="{{ $right->name }}">
        @endif
        @foreach($fields as $field)
            <input type="hidden" name="metadata_sources[{{ $field }}]" x-bind:value="metadata[@js($field)]">
        @endforeach
@endif

<div class="grid items-stretch gap-4 md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
    @foreach([$left, $right] as $index => $medium)
        <div
            @class([
                'min-w-0 rounded-xl p-4 text-left transition',
                'md:order-3' => $index === 1,
                'cursor-pointer' => $canMerge,
                'bg-gray-50' => ! $canMerge,
            ])
            @if($canMerge)
                x-on:click="chooseImage(@js((string) $medium->name))"
                x-on:keydown.enter.prevent="chooseImage(@js((string) $medium->name))"
                x-bind:class="keeper === @js((string) $medium->name) ? 'bg-primary-color-light/10 ring-2 ring-primary-color' : 'bg-gray-50 hover:bg-gray-100'"
                role="button"
                tabindex="0"
            @endif
        >
            <div class="flex gap-4">
                <img src="{{ $medium->thumbnail }}" class="h-28 w-28 shrink-0 rounded-lg object-cover" alt="">
                <div class="min-w-0 flex-1 relative">
                    <div @class(['break-words text-base text-gray-900', 'font-bold' => $differentFields->contains('title'), 'font-semibold' => ! $differentFields->contains('title')])>{{ $medium->title }}</div>
                    <div class="mt-1 break-all text-xs text-gray-500">{{ $medium->name }}</div>
                    <div class="mt-1 text-xs text-gray-500">
                        {{ \App\Helpers::bytesToString((int) $medium->size) }} · {{ (int) $medium->usage_count }} detected {{ (int) $medium->usage_count === 1 ? 'use' : 'uses' }}
                    </div>
                    <div class="mt-3 space-y-1 text-xs text-gray-600">
                        @foreach($visibleFields as $field)
                            <div @class(['break-words', 'font-bold text-gray-900' => $differentFields->contains($field)])>
                                <span>{{ $labels[$field] }}:</span> {{ $displayValue($medium, $field) }}
                            </div>
                        @endforeach
                    </div>
                    <a href="{{ route('admin.media.edit', $medium) }}" target="_blank" rel="noopener" class="absolute top-0 right-0 text-sm font-medium text-primary-color hover:underline" x-on:click.stop aria-label="Open record"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                </div>
            </div>
            @if($canMerge)
                <div class="mt-4 text-center text-sm font-semibold text-primary-color" x-show="keeper === @js((string) $medium->name)">
                    <i class="fa-solid fa-circle-check mr-1"></i> Image selected to keep
                </div>
            @endif
        </div>

        @if($index === 0)
            <div class="flex items-center justify-center py-1 text-2xl text-gray-400 md:order-2 md:py-0" aria-hidden="true">
                <i class="fa-solid fa-right-left rotate-90 md:rotate-0"></i>
            </div>
        @endif
    @endforeach
</div>

@if($canMerge)
        <div class="mt-5 flex flex-wrap justify-end gap-2">
            @if($differentFields->isNotEmpty())
                <x-ui.button type="button" color="outline" x-on:click="openAdvanced()">Advanced merge</x-ui.button>
            @endif
            <x-ui.button type="submit">Merge into selected image</x-ui.button>
        </div>

        @if($differentFields->isNotEmpty())
            <div
                x-show="advancedOpen"
                x-cloak
                class="fixed inset-0 z-[280] flex items-end justify-center bg-black/50 p-4 sm:items-center"
                role="dialog"
                aria-modal="true"
                aria-labelledby="{{ $dialogId }}-title"
                x-on:click.self="closeAdvanced()"
                x-on:keydown.escape.window="if (advancedOpen) { closeAdvanced() }"
            >
                <div class="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5">
                        <div>
                            <h3 id="{{ $dialogId }}-title" class="text-xl font-bold text-gray-900">Advanced merge</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                {{ $isSimilar ? 'Choose the image file and metadata values to keep. The filename selection determines which physical image remains.' : 'Choose which value to keep for each difference.' }}
                            </p>
                        </div>
                        <button type="button" class="text-gray-500 transition hover:text-gray-900" x-on:click="closeAdvanced()" aria-label="Close advanced merge">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>

                    <div class="overflow-y-auto px-6 py-5">
                        <div class="grid grid-cols-[7rem_minmax(0,1fr)_2rem_minmax(0,1fr)] items-center gap-3 border-b border-gray-200 pb-4 text-sm">
                            <div></div>
                            <div class="font-semibold text-gray-900">{{ $left->title }}</div>
                            <div></div>
                            <div class="font-semibold text-gray-900">{{ $right->title }}</div>
                        </div>

                        <div class="border-b border-gray-200 py-4">
                            <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">File details</h4>
                            <div class="divide-y divide-gray-100">
                                @foreach($fileRows as $row)
                                    <div @class(['grid grid-cols-[7rem_minmax(0,1fr)_2rem_minmax(0,1fr)] items-center gap-3 py-2 text-sm', 'text-gray-400' => ! $row['different']])>
                                        <div @class(['font-bold', 'text-gray-700' => $row['different']])>{{ $row['label'] }}</div>
                                        @if($row['label'] === 'Filename')
                                            <label for="{{ $dialogId }}-filename-left" class="flex min-w-0 items-center gap-2 text-gray-700">
                                                <input id="{{ $dialogId }}-filename-left" name="{{ $dialogId }}-filename" type="radio" class="text-primary-color focus:ring-primary-color" value="{{ $left->name }}" x-model="keeper">
                                                <span class="min-w-0 break-all">{{ $row['left'] }}</span>
                                            </label>
                                        @else
                                            <div @class(['flex min-w-0 items-center gap-2', 'text-gray-700' => $row['different']])>
                                                <span class="w-4 shrink-0" aria-hidden="true"></span>
                                                <span class="min-w-0 break-all">{{ $row['left'] }}</span>
                                            </div>
                                        @endif
                                        <div @class(['text-center font-semibold', 'text-gray-500' => $row['different']])>
                                            @if($row['different'])
                                                <i class="fa-solid fa-arrow-left" x-show="keeper === @js((string) $left->name)" aria-label="Keep left value"></i>
                                                <i class="fa-solid fa-arrow-right" x-show="keeper === @js((string) $right->name)" aria-label="Keep right value"></i>
                                            @else
                                                =
                                            @endif
                                        </div>
                                        @if($row['label'] === 'Filename')
                                            <label for="{{ $dialogId }}-filename-right" class="flex min-w-0 items-center gap-2 text-gray-700">
                                                <input id="{{ $dialogId }}-filename-right" name="{{ $dialogId }}-filename" type="radio" class="text-primary-color focus:ring-primary-color" value="{{ $right->name }}" x-model="keeper">
                                                <span class="min-w-0 break-all">{{ $row['right'] }}</span>
                                            </label>
                                        @else
                                            <div @class(['flex min-w-0 items-center gap-2', 'text-gray-700' => $row['different']])>
                                                <span class="w-4 shrink-0" aria-hidden="true"></span>
                                                <span class="min-w-0 break-all">{{ $row['right'] }}</span>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <h4 class="pt-4 text-xs font-semibold uppercase tracking-wide text-gray-500">Record metadata</h4>
                        <div class="divide-y divide-gray-200">
                            @foreach($fields as $field)
                                @php($fieldDiffers = $differentFields->contains($field))
                                <div @class(['grid grid-cols-[7rem_minmax(0,1fr)_2rem_minmax(0,1fr)] items-center gap-3 py-3 text-sm', 'text-gray-400' => ! $fieldDiffers])>
                                    <div @class(['font-bold', 'text-gray-700' => $fieldDiffers])>{{ $labels[$field] }}</div>
                                    <label for="{{ $dialogId }}-metadata-{{ $field }}-left" @class(['flex min-w-0 items-center gap-2', 'text-gray-700' => $fieldDiffers])>
                                        @if($fieldDiffers)
                                            <input id="{{ $dialogId }}-metadata-{{ $field }}-left" name="{{ $dialogId }}-metadata-{{ $field }}" type="radio" class="text-primary-color focus:ring-primary-color" value="{{ $left->name }}" x-model="metadata[@js($field)]">
                                        @else
                                            <span class="w-4 shrink-0" aria-hidden="true"></span>
                                        @endif
                                        <span class="min-w-0 break-words">{{ $displayValue($left, $field) }}</span>
                                    </label>
                                    <div @class(['text-center font-semibold', 'text-gray-500' => $fieldDiffers])>
                                        @if($fieldDiffers)
                                            <i class="fa-solid fa-arrow-left" x-show="metadata[@js($field)] === @js((string) $left->name)" aria-label="Keep left value"></i>
                                            <i class="fa-solid fa-arrow-right" x-show="metadata[@js($field)] === @js((string) $right->name)" aria-label="Keep right value"></i>
                                        @else
                                            =
                                        @endif
                                    </div>
                                    <label for="{{ $dialogId }}-metadata-{{ $field }}-right" @class(['flex min-w-0 items-center gap-2', 'text-gray-700' => $fieldDiffers])>
                                        @if($fieldDiffers)
                                            <input id="{{ $dialogId }}-metadata-{{ $field }}-right" name="{{ $dialogId }}-metadata-{{ $field }}" type="radio" class="text-primary-color focus:ring-primary-color" value="{{ $right->name }}" x-model="metadata[@js($field)]">
                                        @else
                                            <span class="w-4 shrink-0" aria-hidden="true"></span>
                                        @endif
                                        <span class="min-w-0 break-words">{{ $displayValue($right, $field) }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2 border-t border-gray-200 px-6 py-4">
                        <x-ui.button type="button" color="outline" x-on:click="closeAdvanced()">Cancel</x-ui.button>
                        <x-ui.button type="submit">Merge</x-ui.button>
                    </div>
                </div>
            </div>
        @endif
    </form>
@endif
