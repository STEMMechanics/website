<x-layout>
    <x-mast backRoute="admin.media.index" backTitle="Media">Duplicate Media</x-mast>

    <x-container>
        @if($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="my-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Exact duplicate files</h2>
                    <p class="mt-1 text-sm text-gray-600">The highlighted image will be kept. Select an image, or open Advanced merge to mix metadata values before merging.</p>
                </div>
                <form method="POST" action="{{ route('admin.media.duplicates.scan-similar') }}">
                    @csrf
                    <x-ui.button type="submit" color="outline">Scan Similar Images</x-ui.button>
                </form>
            </div>

            <div class="mt-5 space-y-6">
                @forelse($duplicateGroups as $group)
                    <div class="border-t border-gray-200 pt-6">
                        @include('admin.media._duplicate-group', ['group' => $group])
                    </div>
                @empty
                    <div class="border-t border-gray-200 pt-5 text-center text-green-900">
                        <div class="font-semibold">No exact duplicates found</div>
                        <div class="mt-1 text-sm">Every media record currently points to unique file contents.</div>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="mb-4 mt-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-lg font-semibold text-gray-900">{{ $showIgnored ? 'Ignored similar images' : 'Possible similar images' }}</h2>
                @if($similarPairs->isNotEmpty())
                    <a href="{{ $showIgnored ? route('admin.media.duplicates') : route('admin.media.duplicates', ['show_ignored' => 1]) }}" class="text-sm font-medium text-primary-color hover:underline">
                        {{ $showIgnored ? 'Show possible matches' : 'Show ignored matches' }}
                    </a>
                @endif
            </div>

            @if(!$showIgnored && $similarPairs->isNotEmpty())
                <div
                    x-data="{
                        mergeUrl: @js(route('admin.media.duplicates.merge-similar')),
                        ignoreUrl: @js(route('admin.media.duplicates.ignore-similar')),
                        processing: false,
                        progressOpen: false,
                        complete: false,
                        completed: 0,
                        total: 0,
                        failures: [],
                        queueError: '',
                        progressPercent() {
                            return this.total === 0 ? 0 : Math.round((this.completed / this.total) * 100);
                        },
                        async saveActions() {
                            if (this.processing) return;
                            this.queueError = '';
                            const rows = Array.from(this.$refs.pairs.querySelectorAll('[data-similar-pair-row]'));
                            const actions = rows.map((row) => ({
                                row,
                                action: row.dataset.action,
                                left: row.dataset.left,
                                right: row.dataset.right,
                                keeper: row.dataset.keeper,
                                metadata: JSON.parse(row.dataset.metadataSources || '{}'),
                            })).filter((item) => item.action === 'merge' || item.action === 'ignore');
                            if (actions.length === 0) {
                                this.queueError = 'Choose Advanced merge or Ignore for at least one row.';
                                return;
                            }

                            const mergeKeepers = new Set();
                            const mergeDuplicates = new Set();
                            const metadataByKeeper = new Map();
                            for (const item of actions.filter((candidate) => candidate.action === 'merge')) {
                                if (item.keeper !== item.left && item.keeper !== item.right) {
                                    this.queueError = 'Choose an image to keep in each Advanced merge dialog.';
                                    return;
                                }
                                const duplicate = item.keeper === item.left ? item.right : item.left;
                                if (mergeDuplicates.has(duplicate)) {
                                    this.queueError = 'An image can only be removed once in a save. Clear one of the overlapping merge actions and try again.';
                                    return;
                                }
                                mergeKeepers.add(item.keeper);
                                mergeDuplicates.add(duplicate);
                                const fieldChoices = metadataByKeeper.get(item.keeper) || {};
                                for (const [field, source] of Object.entries(item.metadata)) {
                                    if (fieldChoices[field] && fieldChoices[field] !== source) {
                                        this.queueError = 'Rows that share a keeper need the same metadata choice for each field.';
                                        return;
                                    }
                                    fieldChoices[field] = source;
                                }
                                metadataByKeeper.set(item.keeper, fieldChoices);
                            }
                            if (Array.from(mergeKeepers).some((name) => mergeDuplicates.has(name))) {
                                this.queueError = 'An image selected to keep cannot also be removed by another selected merge.';
                                return;
                            }

                            actions.sort((first, second) => (first.action === 'ignore' ? 0 : 1) - (second.action === 'ignore' ? 0 : 1));
                            this.processing = true;
                            this.progressOpen = true;
                            this.complete = false;
                            this.completed = 0;
                            this.total = actions.length;
                            this.failures = [];

                            for (const item of actions) {
                                const formData = new FormData();
                                formData.append('_token', this.$refs.csrf.value);
                                if (item.action === 'merge') {
                                    formData.append('first', item.keeper);
                                    formData.append('second', item.keeper === item.left ? item.right : item.left);
                                    Object.entries(item.metadata).forEach(([field, source]) => formData.append('metadata_sources['+field+']', source));
                                } else {
                                    formData.append('first', item.left);
                                    formData.append('second', item.right);
                                }

                                try {
                                    const response = await fetch(item.action === 'merge' ? this.mergeUrl : this.ignoreUrl, {
                                        method: 'POST',
                                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                        body: formData,
                                        credentials: 'same-origin',
                                    });
                                    const result = await response.json().catch(() => ({}));
                                    if (!response.ok || result.success !== true) {
                                        const validationMessage = Object.values(result.errors || {}).flat()[0];
                                        throw new Error(validationMessage || result.message || 'The action could not be saved.');
                                    }
                                } catch (error) {
                                    this.failures.push({ row: item.left+' / '+item.right, message: error.message || 'The action could not be saved.' });
                                }
                                this.completed++;
                            }

                            this.processing = false;
                            this.complete = true;
                        },
                        finish() {
                            window.location.reload();
                        }
                    }"
                >
                    <input x-ref="csrf" type="hidden" value="{{ csrf_token() }}">
                    <div x-ref="pairs" class="mt-5 space-y-5">
                        @foreach($similarPairs as $pair)
                            <div class="border-t border-gray-200 pt-5">
                                <div class="mb-4">
                                    <h3 class="font-semibold text-gray-900">{{ number_format($pair['similarity'], 1) }}% visually similar</h3>
                                </div>
                                @include('admin.media._duplicate-comparison', [
                                    'left' => $pair['first'],
                                    'right' => $pair['second'],
                                    'canMerge' => true,
                                ])
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6 flex justify-end border-t border-gray-200 pt-5">
                        <x-ui.button type="button" x-on:click="saveActions()" x-bind:disabled="processing">Save</x-ui.button>
                    </div>
                    <p x-show="queueError" x-cloak x-text="queueError" class="mt-3 text-sm text-red-700" role="alert"></p>

                    <div x-show="progressOpen" x-cloak class="fixed inset-0 z-280 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="similar-progress-title">
                        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                            <h3 id="similar-progress-title" class="text-lg font-semibold text-gray-900" x-text="complete ? 'Save complete' : 'Saving selected actions'"></h3>
                            <p class="mt-2 text-sm text-gray-600" x-text="completed+' of '+total+' actions processed'"></p>
                            <div class="mt-4 h-3 overflow-hidden rounded-full bg-gray-200" role="progressbar" aria-label="Duplicate actions progress" aria-valuemin="0" x-bind:aria-valuemax="total" x-bind:aria-valuenow="completed">
                                <div class="h-full rounded-full bg-primary-color transition-all duration-300" x-bind:style="'width: '+progressPercent()+'%'" x-bind:class="processing ? 'animate-pulse' : ''"></div>
                            </div>
                            <div x-show="complete && failures.length === 0" x-cloak class="mt-4 text-sm text-green-800">All selected actions were processed.</div>
                            <div x-show="complete && failures.length > 0" x-cloak class="mt-4">
                                <p class="text-sm text-amber-800" x-text="failures.length+' action(s) could not be processed. Reload to review those rows.'"></p>
                                <ul class="mt-2 max-h-40 space-y-1 overflow-y-auto text-xs text-gray-600">
                                    <template x-for="failure in failures" :key="failure.row">
                                        <li><span class="font-medium" x-text="failure.row"></span>: <span x-text="failure.message"></span></li>
                                    </template>
                                </ul>
                            </div>
                            <div class="mt-6 flex justify-end">
                                <x-ui.button type="button" x-show="complete" x-on:click="finish()">Finish</x-ui.button>
                                <span x-show="processing" class="text-sm font-medium text-gray-600">Please keep this page open while it saves.</span>
                            </div>
                        </div>
                    </div>
                </div>
            @elseif($showIgnored && $similarPairs->isNotEmpty())
                <div class="mt-5 space-y-5">
                    @foreach($similarPairs as $pair)
                        <div class="border-t border-gray-200 pt-5">
                            <div class="mb-4 flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="font-semibold text-gray-900">{{ number_format($pair['similarity'], 1) }}% visually similar</h3>
                                </div>
                                <form method="POST" action="{{ route('admin.media.duplicates.restore-similar') }}">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="first" value="{{ $pair['first']->name }}">
                                    <input type="hidden" name="second" value="{{ $pair['second']->name }}">
                                    <x-ui.button type="submit" color="outline">Restore match</x-ui.button>
                                </form>
                            </div>
                            @include('admin.media._duplicate-comparison', [
                                'left' => $pair['first'],
                                'right' => $pair['second'],
                                'canMerge' => false,
                            ])
                        </div>
                    @endforeach
                </div>
            @elseif($similarPairs->isEmpty())
                <div class="mt-5 border-t border-gray-200 pt-5 text-center text-sm text-gray-700">
                    {{ $showIgnored ? 'No ignored similar images found.' : 'No similar images found.' }}
                    <a href="{{ $showIgnored ? route('admin.media.duplicates') : route('admin.media.duplicates', ['show_ignored' => 1]) }}" class="font-medium text-primary-color hover:underline">
                        {{ $showIgnored ? 'Show possible matches' : 'Show ignored matches' }}
                    </a>
                </div>
            @endif
        </div>
    </x-container>
</x-layout>
