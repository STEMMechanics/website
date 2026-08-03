<x-layout>
    <x-mast backRoute="admin.media.index" backTitle="Media">Duplicate Media</x-mast>

    <x-container>
        <div class="my-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Exact duplicate files</h2>
                    <p class="mt-1 text-sm text-gray-600">These items have identical file contents. Choose the record to keep; all detected links will be transferred before the other records are removed.</p>
                </div>
                <form method="POST" action="{{ route('admin.media.duplicates.scan-similar') }}">
                    @csrf
                    <x-ui.button type="submit" color="outline">Scan Similar Images</x-ui.button>
                </form>
            </div>

            @if($errors->any())
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="mt-5 space-y-5">
                @forelse($duplicateGroups as $group)
                    <div class="grid gap-3 border-t border-gray-200 pt-5 lg:grid-cols-2">
                        @foreach($group['media'] as $medium)
                            <div class="flex gap-3 rounded-lg border border-gray-200 p-3">
                                <img src="{{ $medium->thumbnail }}" class="h-16 w-16 shrink-0 rounded object-cover" alt="">
                                <div class="min-w-0 flex-1">
                                    <div class="break-words font-semibold text-gray-900">{{ $medium->title }}</div>
                                    <div class="mt-1 break-all text-xs text-gray-500">{{ $medium->name }}</div>
                                    <div class="mt-1 text-xs text-gray-500">
                                        {{ \App\Helpers::bytesToString((int) $medium->size) }} · {{ $medium->created_at?->format('j M Y') ?? 'Unknown date' }} · {{ (int) $medium->usage_count }} detected {{ (int) $medium->usage_count === 1 ? 'use' : 'uses' }}
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500">
                                        Owner: {{ $medium->user?->getName() ?: ($medium->user?->email ?: 'None') }} · Visibility: {{ ucfirst((string) ($medium->visibility ?: 'private')) }}
                                    </div>
                                    @if(trim((string) $medium->tags) !== '' || trim((string) $medium->caption) !== '' || trim((string) $medium->consent_notes) !== '')
                                        <div class="mt-1 text-xs font-medium text-amber-700">Contains descriptive or consent metadata—review before merging.</div>
                                    @endif
                                    <form method="POST" action="{{ route('admin.media.duplicates.merge') }}" class="mt-3" x-data x-on:submit.prevent="SM.confirm('Keep this media record?', 'Transfer all detected links to this record and remove the other exact duplicates?', 'Keep this record', (isConfirmed) => { if (isConfirmed) { $el.submit(); } })">
                                        @csrf
                                        <input type="hidden" name="keeper" value="{{ $medium->name }}">
                                        @foreach($group['media'] as $member)
                                            <input type="hidden" name="members[]" value="{{ $member->name }}">
                                        @endforeach
                                        <x-ui.button type="submit" color="outline">Keep this record</x-ui.button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
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
                        {{ $showIgnored ? 'Show suggestions' : 'Show ignored matches' }}
                    </a>
                @endif
            </div>

            @foreach($similarPairs as $pair)
            <div class="mt-4 rounded-xl border border-amber-200 p-5">
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
            @endforeach

            @if($similarPairs->isEmpty())
                <div class="mt-5 border-t border-gray-200 pt-5 text-center text-sm text-gray-700">
                    {{ $showIgnored ? 'No ignored similar images found.' : 'No similar images found.' }}
                    <a href="{{ $showIgnored ? route('admin.media.duplicates') : route('admin.media.duplicates', ['show_ignored' => 1]) }}" class="font-medium text-primary-color hover:underline">
                        {{ $showIgnored ? 'Show suggestions' : 'Show ignored matches' }}
                    </a>
                </div>
            @endif
        </div>
    </x-container>
</x-layout>
