<div class="space-y-8">
    @if($regularCategories->isNotEmpty())
        <section class="space-y-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-gray-400">Other forums</p>
                <p class="mt-2 text-sm text-gray-600">General discussion spaces, STEMCraft updates, and other non-course forums.</p>
            </div>
            @include('forum.partials.category-list', [
                'categories' => $regularCategories,
                'unreadCategoryCounts' => $unreadCategoryCounts,
            ])
        </section>
    @endif

    @if($courseCategories->isNotEmpty())
        <section class="space-y-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-gray-400">Course discussions</p>
                <p class="mt-2 text-sm text-gray-600">Discussion spaces linked to active or archived courses.</p>
            </div>
            @include('forum.partials.category-list', [
                'categories' => $courseCategories,
                'unreadCategoryCounts' => $unreadCategoryCounts,
            ])
        </section>
    @endif
</div>
