<div class="grid gap-4 md:grid-cols-2">
    @foreach($categories as $category)
        @if($category->isDivider())
            <div class="forum-category-divider md:col-span-2">{{ $category->name }}</div>
        @else
            @php
                $forumCategoryPalette = ['amber', 'blue', 'emerald', 'cyan', 'violet', 'fuchsia', 'rose', 'slate'];
                $color = $forumCategoryPalette[$loop->index % count($forumCategoryPalette)];
                $iconClass = trim((string) ($category->icon_class ?? '')) ?: 'fa-solid fa-comments';
                $usesStemcraftIcon = str_contains($iconClass, 'forum-icon-stemcraft');
                $customColor = trim((string) ($category->color_hex ?? ''));
                $unreadCount = (int) ($unreadCategoryCounts[(string) $category->id] ?? 0);
                $courseDateLabel = null;
                $classSession = $category->classSession;

                if ($classSession && $classSession->starts_at) {
                    $courseDateLabel = $classSession->ends_at && ! $classSession->starts_at->isSameDay($classSession->ends_at)
                        ? $classSession->starts_at->format('j M Y').' - '.$classSession->ends_at->format('j M Y')
                        : $classSession->starts_at->format('j M Y');
                }
            @endphp
            <a
                href="{{ route('forum.category.show', $category->slug) }}"
                class="forum-category-card {{ $customColor !== '' ? 'forum-category-card--custom' : 'forum-category-card--'.$color }}"
            >
                <div class="forum-category-card__icon" @if($customColor !== '') style="background-color: {{ $customColor }};" @endif>
                    @if($usesStemcraftIcon)
                        <img src="{{ asset('stemcraft-short-logo.webp') }}" alt="" class="h-5 w-5 object-contain" />
                    @else
                        <i class="{{ $iconClass }}"></i>
                    @endif
                </div>
                <div class="min-w-0 flex flex-1 flex-col self-stretch">
                    <div class="flex items-center gap-2">
                        <h2 class="truncate text-lg font-bold text-gray-900">{{ $category->name }}</h2>
                        @if($unreadCount > 0)
                            <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-700" aria-label="{{ $unreadCount }} unread discussions" data-unread-count="{{ $unreadCount }}">{{ number_format($unreadCount) }}</span>
                        @endif
                    </div>
                    @if(! empty($courseDateLabel))
                        <div class="mt-1 text-sm text-gray-500">{{ $courseDateLabel }}</div>
                    @endif
                    <div class="mt-1 text-sm text-gray-500">
                        {{ number_format($category->topics_count) }} {{ \Illuminate\Support\Str::plural('thread', (int) $category->topics_count) }}
                    </div>
                    @if($category->description)
                        <div class="mt-2 min-h-10 flex-1 line-clamp-2 text-sm leading-5 text-gray-600">{{ $category->description }}</div>
                    @endif
                </div>
                <div class="forum-category-card__chevron">
                    <i class="fa-solid fa-chevron-right"></i>
                </div>
            </a>
        @endif
    @endforeach
</div>
