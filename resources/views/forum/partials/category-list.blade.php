@php($forumCategoryPalette = ['amber', 'blue', 'emerald', 'cyan', 'violet', 'fuchsia', 'rose', 'slate'])

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    @foreach($categories as $category)
        @if($category->isDivider())
            <div class="forum-category-divider md:col-span-2 xl:col-span-4">{{ $category->name }}</div>
        @else
            @php($color = $forumCategoryPalette[$loop->index % count($forumCategoryPalette)])
            @php($iconClass = trim((string) ($category->icon_class ?? '')) ?: 'fa-solid fa-comments')
            @php($customColor = trim((string) ($category->color_hex ?? '')))
            <a
                href="{{ route('forum.category.show', $category->slug) }}"
                class="forum-category-card {{ $customColor !== '' ? 'forum-category-card--custom' : 'forum-category-card--'.$color }}"
                @if($customColor !== '') style="--forum-category-card-color: {{ $customColor }}" @endif
            >
                <div class="forum-category-card__icon">
                    <i class="{{ $iconClass }}"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h2 class="truncate text-lg font-bold text-gray-900">{{ $category->name }}</h2>
                        @if(isset($unreadCategoryLookup[(string) $category->id]))
                            <span class="rounded-full bg-red-100 px-2 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-red-700">New</span>
                        @endif
                    </div>
                    <div class="mt-1 text-sm text-gray-500">
                        {{ number_format($category->topics_count) }} {{ \Illuminate\Support\Str::plural('thread', (int) $category->topics_count) }}
                    </div>
                    @if($category->description)
                        <div class="mt-2 line-clamp-2 text-sm text-gray-600">{{ $category->description }}</div>
                    @endif
                </div>
                <div class="forum-category-card__chevron">
                    <i class="fa-solid fa-chevron-right"></i>
                </div>
            </a>
        @endif
    @endforeach
</div>
