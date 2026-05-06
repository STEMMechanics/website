@push('head')
    <link rel="alternate" type="application/rss+xml" title="STEMMechanics Discussions RSS feed" href="{{ route('forum.feed') }}">
@endpush

<x-layout>
    <x-mast>Discussions</x-mast>

    <x-container class="py-8" id="forum-index-page">
        <div class="mb-8 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-gray-400">Categories</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-gray-900">Browse discussion spaces</h1>
                <p class="mt-2 max-w-2xl text-base text-gray-600">Explore discussion spaces for workshops, ideas, STEMCraft updates, and general conversation, with new replies easy to spot as you browse.</p>
            </div>
            <div class="flex shrink-0 justify-start lg:justify-end">
                <x-ui.button
                    href="{{ route('forum.feed') }}"
                    color="secondary"
                    class="!w-11 !px-0 !py-2 !rounded-full"
                    title="RSS feed"
                    aria-label="RSS feed"
                >
                    <i class="fa-solid fa-rss"></i>
                    <span class="sr-only">RSS feed</span>
                </x-ui.button>
            </div>
        </div>

        @if($regularCategories->isEmpty() && $courseCategories->isEmpty())
            <div id="forum-index-empty" class="rounded-lg border border-gray-200 bg-white p-6 text-gray-600">
                There are no discussions available for your account yet.
            </div>
        @else
            <div id="forum-index-categories" class="space-y-4">
                @include('forum.partials.category-groups', [
                    'regularCategories' => $regularCategories,
                    'courseCategories' => $courseCategories,
                    'unreadCategoryCounts' => $unreadCategoryCounts,
                ])
            </div>
        @endif
    </x-container>
</x-layout>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('forum-index-page');
        const list = document.getElementById('forum-index-categories');
        const empty = document.getElementById('forum-index-empty');
        const snapshotUrl = @js(route('forum.index.snapshot'));

        if (!root || !list || !snapshotUrl) {
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
                const html = String(payload?.categoriesHtml || '').trim();

                if (html === '') {
                    list.innerHTML = '';
                    if (empty) {
                        empty.classList.remove('hidden');
                    }
                } else {
                    list.innerHTML = html;
                    if (empty) {
                        empty.classList.add('hidden');
                    }
                    if (window.Alpine?.initTree) {
                        window.Alpine.initTree(list);
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
