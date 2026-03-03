<x-layout>
    <x-mast>Discussions</x-mast>

    @php($unreadCategoryLookup = array_flip($unreadCategoryIds ?? []))

    <x-container class="py-8" id="forum-index-page">
        <div class="mb-8 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-gray-400">Categories</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-gray-900">Browse discussion spaces</h1>
                <p class="mt-2 max-w-2xl text-base text-gray-600">Explore discussion spaces for workshops, ideas, STEMCraft updates, and general conversation, with new replies easy to spot as you browse.</p>
            </div>
        </div>

        @if($categories->isEmpty())
            <div id="forum-index-empty" class="rounded-lg border border-gray-200 bg-white p-6 text-gray-600">
                There are no discussions available for your account yet.
            </div>
        @else
            <div id="forum-index-categories" class="space-y-4">
                @include('forum.partials.category-list', ['categories' => $categories, 'unreadCategoryLookup' => $unreadCategoryLookup])
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

        if (!root || !list || !empty || !snapshotUrl) {
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
                    empty.classList.remove('hidden');
                } else {
                    list.innerHTML = html;
                    empty.classList.add('hidden');
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
