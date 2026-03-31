<div class="space-y-8">
    @if($regularCategories->isNotEmpty())
        <section class="space-y-4">
            @include('forum.partials.category-list', [
                'categories' => $regularCategories,
                'unreadCategoryCounts' => $unreadCategoryCounts,
            ])
        </section>
    @endif

    @if($courseCategories->isNotEmpty())
        <section class="space-y-4 mt-12">
            <div>
                <p class="font-semibold uppercase text-gray-400">Course discussions</p>
                <p class="mt-2 text-sm text-gray-600">Discussion spaces linked to active or archived courses.</p>
            </div>
            @include('forum.partials.category-list', [
                'categories' => $courseCategories,
                'unreadCategoryCounts' => $unreadCategoryCounts,
            ])
        </section>
    @endif
</div>
