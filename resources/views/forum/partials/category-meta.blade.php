<div id="forum-category-meta" class="rounded-2xl border border-gray-200 bg-gray-50 px-5 py-4 text-xs lg:text-sm text-gray-600">
    @if(! empty($courseDateLabel ?? ''))
        <div class="text-sm font-semibold text-gray-900">Course date: {{ $courseDateLabel }}</div>
    @endif
    <div class="font-semibold text-gray-900">{{ number_format($threadCount) }} {{ \Illuminate\Support\Str::plural('thread', $threadCount) }}</div>
    <div class="mt-2 space-y-1">
        <p class="mb-4">Pinned threads stay at the top. Active threads update automatically while you are here.</p>
        <p>{{ number_format($commentCount) }} {{ \Illuminate\Support\Str::plural('comment', $commentCount) }} and {{ number_format($viewCount) }} {{ \Illuminate\Support\Str::plural('view', $viewCount) }} across this category.</p>
        <p>
            @if($latestActivityAt)
                Latest activity was {{ $latestActivityAt->format('j M Y g:i a') }} by {{ $latestActivityAuthorName ?? 'deleted' }}.
            @else
                Create the first thread to start activity here.
            @endif
        </p>
    </div>
</div>
