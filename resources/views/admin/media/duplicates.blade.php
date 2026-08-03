<x-layout>
    <x-mast>Duplicate Media</x-mast>

    <x-container>
        <div class="my-4 flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Exact duplicate files</h2>
                <p class="mt-1 text-sm text-gray-600">These items have identical file contents. Choose the record to keep; all detected links will be transferred before the other records are removed.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.media.duplicates.scan-similar') }}">
                    @csrf
                    <x-ui.button type="submit" color="outline">Scan Similar Images</x-ui.button>
                </form>
                <x-ui.button href="{{ route('admin.media.index') }}" color="outline">Back to Media</x-ui.button>
            </div>
        </div>

        @if($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ $errors->first() }}
            </div>
        @endif

        @forelse($duplicateGroups as $groupIndex => $group)
            <form method="POST" action="{{ route('admin.media.duplicates.merge') }}" class="mb-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm" x-data x-on:submit.prevent="SM.confirm('Merge exact duplicates?', 'Transfer all detected links to the selected media record and remove the other records?', 'Merge Duplicates', (isConfirmed) => { if (isConfirmed) { $el.submit(); } })">
                @csrf
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ $group['media']->count() }} identical media records</h3>
                        <p class="mt-1 text-xs text-gray-500">{{ $group['storage_disk'] }} · {{ substr($group['hash'], 0, 16) }}…</p>
                    </div>
                    <x-ui.button type="submit">Merge Duplicates</x-ui.button>
                </div>

                <div class="mt-4 grid gap-3 lg:grid-cols-2">
                    @foreach($group['media'] as $mediaIndex => $medium)
                        <label class="flex cursor-pointer gap-3 rounded-lg border border-gray-200 p-3 transition hover:border-sky-300 hover:bg-sky-50">
                            <input type="radio" name="keeper" value="{{ $medium->name }}" class="mt-1 text-primary-color" @checked($mediaIndex === 0) required>
                            <input type="hidden" name="members[]" value="{{ $medium->name }}">
                            <img src="{{ $medium->thumbnail }}" class="h-16 w-16 shrink-0 rounded object-cover" alt="">
                            <span class="min-w-0">
                                <span class="block break-words font-semibold text-gray-900">{{ $medium->title }}</span>
                                <span class="mt-1 block break-all text-xs text-gray-500">{{ $medium->name }}</span>
                                <span class="mt-1 block text-xs text-gray-500">
                                    {{ number_format((int) $medium->size) }} bytes · {{ $medium->created_at?->format('j M Y') ?? 'Unknown date' }} · {{ (int) $medium->usage_count }} detected {{ (int) $medium->usage_count === 1 ? 'use' : 'uses' }}
                                </span>
                                <span class="mt-1 block text-xs text-gray-500">
                                    Owner: {{ $medium->user?->getName() ?: ($medium->user?->email ?: 'None') }} · Visibility: {{ ucfirst((string) ($medium->visibility ?: 'private')) }}
                                </span>
                                @if(trim((string) $medium->tags) !== '' || trim((string) $medium->caption) !== '' || trim((string) $medium->consent_notes) !== '')
                                    <span class="mt-1 block text-xs font-medium text-amber-700">Contains descriptive or consent metadata—review before merging.</span>
                                @endif
                                <span class="mt-1 block text-xs font-medium text-sky-700">Keep this record</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </form>
        @empty
            <div class="rounded-xl border border-green-200 bg-green-50 px-5 py-8 text-center text-green-900">
                <div class="font-semibold">No exact duplicates found</div>
                <div class="mt-1 text-sm">Every media record currently points to unique file contents.</div>
            </div>
        @endforelse

        <div class="mb-4 mt-6 flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">{{ $showIgnored ? 'Ignored similar images' : 'Possible similar images' }}</h2>
                <p class="mt-1 text-sm text-gray-600">
                    {{ number_format($hashedImageCount) }} of {{ number_format($imageCount) }} images scanned. Similarity is a visual estimate, so review each suggestion before merging.
                </p>
            </div>
            @if($showIgnored)
                <x-ui.button href="{{ route('admin.media.duplicates') }}" color="outline">Show Suggestions</x-ui.button>
            @else
                <x-ui.button href="{{ route('admin.media.duplicates', ['show_ignored' => 1]) }}" color="outline">Show Ignored Matches</x-ui.button>
            @endif
        </div>

        @forelse($similarPairs as $pair)
            <div class="mb-4 rounded-xl border border-amber-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ number_format($pair['similarity'], 1) }}% visually similar</h3>
                        <p class="mt-1 text-xs text-gray-500">Perceptual distance: {{ $pair['distance'] }} of 64</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if($pair['ignored'])
                            <form method="POST" action="{{ route('admin.media.duplicates.restore-similar') }}">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="first" value="{{ $pair['first']->name }}">
                                <input type="hidden" name="second" value="{{ $pair['second']->name }}">
                                <x-ui.button type="submit" color="outline">Restore Suggestion</x-ui.button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.media.duplicates.ignore-similar') }}">
                                @csrf
                                <input type="hidden" name="first" value="{{ $pair['first']->name }}">
                                <input type="hidden" name="second" value="{{ $pair['second']->name }}">
                                <x-ui.button type="submit" color="outline">Ignore Match</x-ui.button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    @foreach([$pair['first'], $pair['second']] as $index => $medium)
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="flex gap-3">
                                <img src="{{ $medium->thumbnail }}" class="h-28 w-28 shrink-0 rounded object-cover" alt="">
                                <div class="min-w-0">
                                    <a href="{{ route('admin.media.edit', $medium) }}" class="block break-words font-semibold text-gray-900 hover:text-primary-color">{{ $medium->title }}</a>
                                    <div class="mt-1 break-all text-xs text-gray-500">{{ $medium->name }}</div>
                                    <div class="mt-1 text-xs text-gray-500">{{ (int) $medium->usage_count }} detected {{ (int) $medium->usage_count === 1 ? 'use' : 'uses' }} · {{ $medium->created_at?->format('j M Y') ?? 'Unknown date' }}</div>
                                    <div class="mt-1 text-xs text-gray-500">Owner: {{ $medium->user?->getName() ?: ($medium->user?->email ?: 'None') }} · {{ ucfirst((string) ($medium->visibility ?: 'private')) }}</div>
                                </div>
                            </div>
                            @unless($pair['ignored'])
                                @php($other = $index === 0 ? $pair['second'] : $pair['first'])
                                <form method="POST" action="{{ route('admin.media.duplicates.merge-similar') }}" class="mt-3" x-data x-on:submit.prevent="SM.confirm('Merge similar images?', 'Keep this image and remove the other similar image after transferring its detected links?', 'Merge Images', (isConfirmed) => { if (isConfirmed) { $el.submit(); } })">
                                    @csrf
                                    <input type="hidden" name="first" value="{{ $medium->name }}">
                                    <input type="hidden" name="second" value="{{ $other->name }}">
                                    <x-ui.button type="submit" color="outline" class="w-full">Keep This and Merge Other</x-ui.button>
                                </form>
                            @endunless
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-gray-200 bg-gray-50 px-5 py-8 text-center text-gray-700">
                <div class="font-semibold">{{ $showIgnored ? 'No ignored matches' : 'No similar-image suggestions found' }}</div>
                <div class="mt-1 text-sm">{{ $hashedImageCount < $imageCount ? 'Run the similarity scan and refresh this page after the media queue finishes.' : 'No scanned image pairs are currently within the similarity threshold.' }}</div>
            </div>
        @endforelse
    </x-container>
</x-layout>
