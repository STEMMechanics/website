@php
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
    $dimensionsLabel = function ($medium): string {
        $dimensions = trim((string) $medium->getAttribute('original_dimensions'));

        return $dimensions !== '' && $dimensions !== '-' ? $dimensions.' px' : 'Dimensions unavailable';
    };
    $differentFields = $fields->filter(
        fn (string $field): bool => $normalizedValue($left, $field) !== $normalizedValue($right, $field)
    )->values();
    $visibleFields = $fields->reject(fn (string $field): bool => $field === 'title')->filter(
        fn (string $field): bool => $differentFields->contains($field)
            || in_array($field, ['user_id', 'visibility'], true)
            || $normalizedValue($left, $field) !== ''
            || $normalizedValue($right, $field) !== ''
    )->values();
    $dialogId = 'advanced-media-merge-'.md5((string) $left->name.'|'.(string) $right->name);
@endphp

<div
    data-similar-pair-row
    data-left="{{ $left->name }}"
    data-right="{{ $right->name }}"
    x-data="smCspAdminMediaDuplicateComparison1"
    x-bind:data-action="action || ''"
    x-bind:data-keeper="keeper"
    x-bind:data-metadata-sources="$SM.jsonStringify(metadataSources)"
    class="grid items-stretch gap-3 md:grid-cols-3 {{ $canMerge ? '' : 'opacity-80' }}"
>
    @foreach([$left, $right] as $medium)
        <div class="min-w-0 rounded-xl border p-4 transition" x-bind:class="!(action === 'merge' && advancedMerge) && keeper === {{ \Illuminate\Support\Js::encode((string) $medium->name) }} ? 'border-primary-color bg-primary-color-light/10 ring-2 ring-primary-color' : 'border-gray-200 bg-gray-50'">
            <div class="flex gap-4">
                <img src="{{ $medium->thumbnail }}" class="h-24 w-24 shrink-0 rounded-lg object-cover sm:h-28 sm:w-28" alt="">
                <div class="relative min-w-0 flex-1">
                    <div @class(['wrap-break-word text-base text-gray-900', 'font-bold' => $differentFields->contains('title'), 'font-semibold' => ! $differentFields->contains('title')])>{{ $medium->title }}</div>
                    <div class="mt-1 break-all text-xs text-gray-500">{{ $medium->name }}</div>
                    <div class="mt-1 text-xs text-gray-500">{{ $dimensionsLabel($medium) }} · {{ \App\Helpers::bytesToString((int) $medium->size) }} · {{ (int) $medium->usage_count }} {{ (int) $medium->usage_count === 1 ? 'use' : 'uses' }}</div>
                    <div class="mt-3 space-y-1 text-xs text-gray-600">
                        @foreach($visibleFields as $field)
                            <div @class(['wrap-break-word', 'font-bold text-gray-900' => $differentFields->contains($field)])>
                                <span>{{ $labels[$field] }}:</span> {{ $displayValue($medium, $field) }}
                            </div>
                        @endforeach
                    </div>
                    <a href="{{ route('admin.media.edit', $medium) }}" target="_blank" rel="noopener" class="absolute right-0 top-0 text-sm font-medium text-primary-color hover:underline" x-on:click.stop aria-label="Open record"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                </div>
            </div>
            @if($canMerge)
                <button type="button" class="mt-3 w-full rounded-lg border px-3 py-2 text-sm font-semibold transition" x-bind:class="!(action === 'merge' && advancedMerge) && keeper === {{ \Illuminate\Support\Js::encode((string) $medium->name) }} ? 'border-primary-color bg-primary-color text-white' : 'border-gray-300 bg-white text-gray-800 hover:bg-gray-100'" x-on:click="selectKeeper({{ \Illuminate\Support\Js::encode((string) $medium->name) }})">
                    <span x-show="action === 'merge' && advancedMerge || keeper !== {{ \Illuminate\Support\Js::encode((string) $medium->name) }}">Select to keep</span>
                    <span x-show="!(action === 'merge' && advancedMerge) && keeper === {{ \Illuminate\Support\Js::encode((string) $medium->name) }}" x-cloak><i class="fa-solid fa-circle-check mr-1" aria-hidden="true"></i>Selected to keep</span>
                </button>
            @endif
        </div>
    @endforeach

    <div class="flex flex-col gap-2 px-3">
        @if($canMerge)
            <button
                type="button"
                class="min-h-12 w-full flex-1 rounded-lg border px-3 py-2 text-sm font-semibold transition"
                x-bind:class="action === 'merge' && advancedMerge ? 'border-primary-color bg-primary-color text-white' : 'border-gray-300 bg-white text-gray-800 hover:bg-gray-50'"
                x-on:click="openAdvanced()"
            >
                <span x-show="action === 'merge' && advancedMerge" x-cloak class="mr-1"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
                Advanced merge
            </button>
            <button
                type="button"
                class="min-h-12 w-full flex-1 rounded-lg border px-3 py-2 text-sm font-semibold transition"
                x-bind:class="action === 'ignore' ? 'border-gray-700 bg-gray-700 text-white' : 'border-gray-300 bg-white text-gray-800 hover:bg-gray-50'"
                x-on:click="selectIgnore()"
            >
                <span x-show="action === 'ignore'" x-cloak class="mr-1"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
                Ignore
            </button>
            <div class="flex h-6 shrink-0 items-center justify-center">
                <button type="button" x-show="action" x-cloak x-on:click="clearSelection()" class="text-center text-xs font-medium text-gray-500 underline hover:text-gray-800">Clear selection</button>
            </div>
        @else
            <div class="text-center text-sm font-medium text-gray-500">Ignored match</div>
        @endif
    </div>

    @if($canMerge)
        <div x-show="advancedOpen" x-cloak class="fixed inset-0 z-280 flex items-end justify-center bg-black/50 p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="{{ $dialogId }}-title" x-on:click.self="closeAdvanced()" x-on:keydown.escape.window="advancedOpen &amp;&amp; closeAdvanced()">
            <div class="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5">
                    <div>
                        <h3 id="{{ $dialogId }}-title" class="text-xl font-bold text-gray-900">Advanced merge</h3>
                        <p class="mt-1 text-sm text-gray-600">Choose an image to keep, then select the source for any differing metadata.</p>
                    </div>
                    <x-ui.button variant="plain" type="button" class="text-gray-500 transition hover:text-gray-900" x-on:click="closeAdvanced()" aria-label="Close advanced merge"><i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i></x-ui.button>
                </div>

                <div class="overflow-y-auto overscroll-contain px-6 py-5">
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-sm" style="min-width: 36rem; table-layout: fixed;">
                            <colgroup>
                                <col style="width: 8rem;">
                                <col>
                                <col>
                            </colgroup>
                            <tbody>
                                <tr class="border-b border-gray-200">
                                    <th scope="row" class="px-3 py-2 text-left font-semibold text-gray-700">Image</th>
                                    @foreach([$left, $right] as $medium)
                                        <td class="px-3 py-2 align-middle">
                                            <label class="flex min-w-0 cursor-pointer items-center gap-2">
                                                <x-ui.input-control name="{{ $dialogId }}-keeper" type="radio" class="text-primary-color focus:ring-primary-color" value="{{ $medium->name }}" x-model="draftKeeper" x-on:change="chooseDraftKeeper($event.target.value)" />
                                                <span class="flex min-w-0 items-center gap-3">
                                                    <img src="{{ $medium->thumbnail }}" class="h-16 w-16 shrink-0 rounded-lg object-cover" alt="">
                                                    <span class="min-w-0">
                                                        <span class="block break-all text-xs text-gray-600">{{ $medium->name }}</span>
                                                        <span class="mt-1 block text-xs text-gray-600">{{ $dimensionsLabel($medium) }} · {{ \App\Helpers::bytesToString((int) $medium->size) }}</span>
                                                    </span>
                                                </span>
                                            </label>
                                        </td>
                                    @endforeach
                                </tr>
                                @foreach($fields as $field)
                                    @php($fieldDiffers = $differentFields->contains($field))
                                    <tr class="border-b border-gray-100">
                                        <th scope="row" @class(['px-3 py-2 text-left font-semibold', 'text-gray-700' => $fieldDiffers, 'text-gray-400' => ! $fieldDiffers])>{{ $labels[$field] }}</th>
                                        @if($fieldDiffers)
                                            @foreach([$left, $right] as $medium)
                                                <td class="px-3 py-2 align-top text-gray-700">
                                                    <label class="flex min-w-0 cursor-pointer items-center gap-2">
                                                        <x-ui.input-control name="{{ $dialogId }}-metadata-{{ $field }}" type="radio" class="text-primary-color focus:ring-primary-color" value="{{ $medium->name }}" x-model="draftMetadata.{{ $field }}" />
                                                        <span class="min-w-0 wrap-break-word">{{ $displayValue($medium, $field) }}</span>
                                                    </label>
                                                </td>
                                            @endforeach
                                        @else
                                            <td colspan="2" class="px-3 py-2 text-center text-gray-500">{{ $displayValue($left, $field) }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex flex-wrap justify-end gap-2 border-t border-gray-200 px-6 py-4">
                    <x-ui.button type="button" color="outline" x-on:click="closeAdvanced()">Cancel</x-ui.button>
                    <x-ui.button type="button" x-on:click="applyAdvanced()" x-bind:disabled="!canApplyAdvanced()">Merge</x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>

<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
document.addEventListener('alpine:init', function () {
    Alpine.data('smCspAdminMediaDuplicateComparison1', () => ({
        canMerge: @js((bool) $canMerge),
        action: null,
        advancedMerge: false,
        keeper: '',
        metadataSources: {},
        draftKeeper: '',
        draftMetadata: {},
        advancedOpen: false,
        pageScrollState: null,
        differingMetadataFields: @js($differentFields->all()),
        chooseDraftKeeper(name) {
            this.draftKeeper = name;
        },
        canApplyAdvanced() {
            return Boolean(this.draftKeeper)
                && this.differingMetadataFields.every((field) => Boolean(this.draftMetadata[field]));
        },
        selectKeeper(name) {
            this.action = 'merge';
            this.advancedMerge = false;
            this.keeper = name;
            this.metadataSources = {};
            this.notifyActionChange();
        },
        notifyActionChange() {
            const key = JSON.stringify([this.$el.dataset.left, this.$el.dataset.right]);
            window.dispatchEvent(new CustomEvent('similar-pair-action-change', {
                detail: {key, action: this.action},
            }));
        },
        openAdvanced() {
            if (!this.canMerge) return;
            this.draftKeeper = this.keeper;
            this.draftMetadata = this.action === 'merge' && this.advancedMerge
                ? {...this.metadataSources}
                : {};
            this.lockPageScroll();
            this.advancedOpen = true;
        },
        lockPageScroll() {
            if (this.pageScrollState) return;
            const body = document.body;
            const root = document.documentElement;
            const scrollY = window.scrollY || window.pageYOffset || 0;
            this.pageScrollState = {
                scrollY,
                rootOverflow: root.style.overflow,
                bodyPosition: body.style.position,
                bodyTop: body.style.top,
                bodyLeft: body.style.left,
                bodyRight: body.style.right,
                bodyWidth: body.style.width,
                bodyOverflow: body.style.overflow,
            };
            root.style.overflow = 'hidden';
            body.style.position = 'fixed';
            body.style.top = '-'+scrollY+'px';
            body.style.left = '0';
            body.style.right = '0';
            body.style.width = '100%';
            body.style.overflow = 'hidden';
        },
        unlockPageScroll() {
            if (!this.pageScrollState) return;
            const body = document.body;
            const root = document.documentElement;
            const state = this.pageScrollState;
            root.style.overflow = state.rootOverflow;
            body.style.position = state.bodyPosition;
            body.style.top = state.bodyTop;
            body.style.left = state.bodyLeft;
            body.style.right = state.bodyRight;
            body.style.width = state.bodyWidth;
            body.style.overflow = state.bodyOverflow;
            this.pageScrollState = null;
            window.scrollTo(0, state.scrollY);
        },
        closeAdvanced() {
            this.advancedOpen = false;
            this.unlockPageScroll();
        },
        applyAdvanced() {
            if (!this.canApplyAdvanced()) return;
            this.action = 'merge';
            this.advancedMerge = true;
            this.keeper = this.draftKeeper;
            this.metadataSources = {...this.draftMetadata};
            this.closeAdvanced();
            this.notifyActionChange();
        },
        selectIgnore() {
            this.action = 'ignore';
            this.advancedMerge = false;
            this.keeper = '';
            this.metadataSources = {};
            this.notifyActionChange();
        },
        clearSelection() {
            this.action = null;
            this.advancedMerge = false;
            this.keeper = '';
            this.metadataSources = {};
            this.notifyActionChange();
        }
    }));
});
</script>
