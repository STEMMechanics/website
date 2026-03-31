<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    @foreach($categories as $category)
        @if($category->isDivider())
            <div class="forum-category-divider md:col-span-2 xl:col-span-4">{{ $category->name }}</div>
        @else
            @php($forumCategoryPalette = ['amber', 'blue', 'emerald', 'cyan', 'violet', 'fuchsia', 'rose', 'slate'])
            @php($color = $forumCategoryPalette[$loop->index % count($forumCategoryPalette)])
            @php($iconClass = trim((string) ($category->icon_class ?? '')) ?: 'fa-solid fa-comments')
            @php($usesStemcraftIcon = str_contains($iconClass, 'forum-icon-stemcraft'))
            @php($customColor = trim((string) ($category->color_hex ?? '')))
            @php($unreadCount = (int) ($unreadCategoryCounts[(string) $category->id] ?? 0))
            <a
                href="{{ route('forum.category.show', $category->slug) }}"
                class="forum-category-card {{ $customColor !== '' ? 'forum-category-card--custom' : 'forum-category-card--'.$color }}"
                @if($customColor !== '') style="--forum-category-card-color: {{ $customColor }}" @endif
            >
                <div class="forum-category-card__icon">
                    @if($usesStemcraftIcon)
                        <img src="{{ asset('stemcraft-short-logo.webp') }}" alt="" class="h-5 w-5 object-contain" />
                    @else
                        <i class="{{ $iconClass }}"></i>
                    @endif
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h2 class="truncate text-lg font-bold text-gray-900">{{ $category->name }}</h2>
                        @if($unreadCount > 0)
                            <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-700" aria-label="{{ $unreadCount }} unread discussions" data-unread-count="{{ $unreadCount }}">{{ number_format($unreadCount) }}</span>
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
