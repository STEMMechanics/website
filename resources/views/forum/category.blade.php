@php
    $tabs = [];
    $classSession = $category->classSession ?? null;
    if ($classSession) {
        $tabs[] = [
            'title' => 'Course',
            'route' => route('class.show', $classSession),
        ];
        $tabs[] = [
            'title' => 'Forum',
            'route' => route('forum.category.show', $category->slug),
        ];
    }
@endphp

<x-layout>
    <x-mast backRoute="forum.index" backTitle="Discussions" :tabs="$tabs">{{ $category->name }}</x-mast>

    @php($unreadTopicLookup = array_flip($unreadTopicIds ?? []))

    <x-container class="py-8" id="forum-category-page">
        <div class="mb-8 -mt-8 rounded-b-lg border border-gray-200 bg-white p-6 shadow-[0_18px_48px_rgba(15,23,42,0.05)] sm:p-8">
            <div class="flex flex-col gap-12 md:flex-row md:justify-between">
                <div class="max-w-3xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.22em] text-gray-400">Category</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-gray-900 whitespace-nowrap">{{ $category->name }}</h1>
                    @if($category->description)
                        <p class="mt-3 whitespace-pre-line text-base leading-7 text-gray-600">{{ $category->description }}</p>
                    @endif
                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        @auth
                            @if($canWrite)
                                <x-ui.button href="{{ route('forum.topic.create', $category->slug) }}" class="!px-5">Create Thread</x-ui.button>
                            @else
                                <span class="text-sm text-gray-500">You can read this category, but you do not have permission to create threads here.</span>
                            @endif
                        @else
                            <span class="text-sm text-gray-500">Log in to create a thread.</span>
                            <x-ui.button color="outline" href="{{ route('login') }}" class="!rounded-full !px-5">Log In</x-ui.button>
                        @endauth
                    </div>
                </div>

                <div id="forum-category-meta-panel" class="hidden md:block">
                    @include('forum.partials.category-meta', [
                        'threadCount' => $threadCount,
                        'commentCount' => $commentCount,
                        'viewCount' => $viewCount,
                        'latestActivityAt' => $latestActivityAt,
                        'latestActivityAuthorName' => $latestActivityAuthorName,
                    ])
                </div>
            </div>
        </div>

        @if($topics->isEmpty())
            <div id="forum-category-empty" class="rounded-lg border border-gray-200 bg-white p-6 text-gray-600">
                No threads have been created in this category yet.
            </div>
        @else
            <div id="forum-category-threads" class="space-y-4">
                @include('forum.partials.thread-list', ['topics' => $topics, 'category' => $category, 'unreadTopicLookup' => $unreadTopicLookup])
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
        const meta = document.getElementById('forum-category-meta-panel');
        const list = document.getElementById('forum-category-threads');
        const pagination = document.getElementById('forum-category-pagination');
        const empty = document.getElementById('forum-category-empty');
        const snapshotUrl = @js(route('forum.category.snapshot', $category->slug));

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

                if (meta && typeof payload?.metaHtml === 'string' && payload.metaHtml.trim() !== '') {
                    meta.innerHTML = payload.metaHtml;
                }

                if (threadsHtml === '') {
                    if (list) {
                        list.innerHTML = '';
                    }
                    if (pagination) {
                        pagination.innerHTML = '';
                    }
                    if (empty) {
                        empty.innerHTML = payload?.emptyHtml || 'No threads have been created in this category yet.';
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
