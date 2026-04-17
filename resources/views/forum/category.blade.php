@php
    $tabs = [];
    $classSession = $category->classSession ?? null;
    $courseDateLabel = null;
    if ($classSession) {
        $tabs[] = [
            'title' => 'Course',
            'route' => route('class.show', $classSession),
        ];
        $tabs[] = [
            'title' => 'Forum',
            'route' => route('forum.category.show', $category->slug),
        ];

        if ($classSession->starts_at) {
            $courseDateLabel = $classSession->ends_at && ! $classSession->starts_at->isSameDay($classSession->ends_at)
                ? $classSession->starts_at->format('j M Y').' - '.$classSession->ends_at->format('j M Y')
                : $classSession->starts_at->format('j M Y');
        }
    }
@endphp

<x-layout>
    <x-mast backRoute="forum.index" backTitle="Discussions" :tabs="$tabs">{{ $category->name }}</x-mast>

    @php($unreadTopicLookup = array_flip($unreadTopicIds ?? []))
    @php($topicSort = $topicSort ?? ($category->classSession ? 'oldest' : 'latest'))

    <x-container class="py-8" id="forum-category-page">
        <div class="mb-6 -mt-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            @if($canWrite)
                <div class="flex justify-start">
                    <x-ui.button href="{{ route('forum.topic.create', array_merge(['categorySlug' => $category->slug], $topicSort !== '' ? ['topicSort' => $topicSort] : [])) }}" class="px-5!">Create Thread</x-ui.button>
                </div>
            @else
                <div></div>
            @endif

            <form method="GET" action="{{ route('forum.category.show', $category->slug) }}" class="flex items-center gap-3">
                <x-ui.select
                    id="topicSort"
                    name="topicSort"
                    label="Topic order"
                    :value="$topicSort"
                    inline-label
                    class="mb-0"
                    inner-class="w-44"
                    onchange="this.form.submit()"
                >
                    <option value="latest">Latest activity</option>
                    <option value="oldest">Oldest first</option>
                </x-ui.select>
            </form>
        </div>

        @if($topics->isEmpty())
            <div id="forum-category-empty" class="rounded-lg border border-gray-200 bg-white p-6 text-gray-600">
                No threads have been created in this category yet.
            </div>
        @else
            @include('forum.partials.category-meta', [
                'category' => $category,
                'threadCount' => $threadCount,
                'commentCount' => $commentCount,
                'viewCount' => $viewCount,
                'latestActivityAt' => $latestActivityAt,
                'latestActivityAuthorName' => $latestActivityAuthorName,
                'courseDateLabel' => $courseDateLabel,
            ])

            <div id="forum-category-threads" class="space-y-4">
                @include('forum.partials.thread-list', ['topics' => $topics, 'category' => $category, 'unreadTopicLookup' => $unreadTopicLookup, 'topicSort' => $topicSort])
            </div>

            <div id="forum-category-pagination" class="mt-6">
                {{ $topics->appends(request()->query())->links() }}
            </div>
        @endif
    </x-container>
</x-layout>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('forum-category-page');
        const list = document.getElementById('forum-category-threads');
        const pagination = document.getElementById('forum-category-pagination');
        const empty = document.getElementById('forum-category-empty');
        const snapshotUrl = @js(route('forum.category.snapshot', array_merge(['categorySlug' => $category->slug], request()->query())));

        if (!root || !snapshotUrl) {
            return;
        }

        const refresh = async () => {
            try {
                const response = await fetch(snapshotUrl, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                const threadsHtml = String(payload?.threadsHtml || '').trim();

                if (threadsHtml === '') {
                    if (list) {
                        list.innerHTML = '';
                    }
                    if (pagination) {
                        pagination.innerHTML = '';
                    }
                    if (empty) {
                        empty.textContent = payload?.emptyText || 'No threads have been created in this category yet.';
                        empty.classList.remove('hidden');
                    }
                } else {
                    if (list) {
                        list.innerHTML = threadsHtml;
                    }
                    if (pagination) {
                        pagination.innerHTML = payload?.paginationHtml || '';
                    }
                    if (empty) {
                        empty.classList.add('hidden');
                    }
                    if (window.Alpine?.initTree) {
                        if (list) {
                            window.Alpine.initTree(list);
                        }
                        if (pagination) {
                            window.Alpine.initTree(pagination);
                        }
                    }
                }

                window.dispatchEvent(new CustomEvent('forum-notifications-refresh'));
            } catch (_error) {
            }
        };

        window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                refresh();
            }
        }, 15000);

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                refresh();
            }
        });
    });
</script>
