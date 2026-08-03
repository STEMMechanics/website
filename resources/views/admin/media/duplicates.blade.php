<x-layout>
    <x-mast backRoute="admin.media.index" backTitle="Media">Duplicate Media</x-mast>

    <x-container>
        <div class="my-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Exact duplicate files</h2>
                    <p class="mt-1 text-sm text-gray-600">The first record is used by default. Open Advanced merge to choose a different filename or mix metadata values before merging.</p>
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

            <div class="mt-5 space-y-6">
                @forelse($duplicateGroups as $group)
                    @php($primary = $group['media']->first())
                    @foreach($group['media']->slice(1) as $duplicate)
                        <div class="border-t border-gray-200 pt-6">
                            @include('admin.media._duplicate-comparison', [
                                'left' => $primary,
                                'right' => $duplicate,
                                'mergeRoute' => route('admin.media.duplicates.merge'),
                                'isSimilar' => false,
                                'canMerge' => true,
                            ])
                        </div>
                    @endforeach
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
                <div class="mt-5 border-t border-gray-200 pt-5">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ number_format($pair['similarity'], 1) }}% visually similar</h3>
                            <p class="mt-1 text-xs text-gray-500">Perceptual distance: {{ $pair['distance'] }} of 64</p>
                        </div>
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

                    @include('admin.media._duplicate-comparison', [
                        'left' => $pair['first'],
                        'right' => $pair['second'],
                        'mergeRoute' => route('admin.media.duplicates.merge-similar'),
                        'isSimilar' => true,
                        'canMerge' => ! $pair['ignored'],
                    ])
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
