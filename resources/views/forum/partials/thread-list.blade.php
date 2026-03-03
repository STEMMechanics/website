@foreach($topics as $topic)
    @php
        $authorName = $topic->user?->username ?: $topic->user?->getName() ?: 'Deleted user';
        $authorInitial = strtoupper(mb_substr($authorName, 0, 1));
        $replyCount = max(0, $topic->posts_count - 1);
        $avatarUrl = $topic->user?->avatarMedia?->thumbnail;
        $lastPostAuthorName = $topic->lastPostUser?->username ?: $topic->lastPostUser?->getName() ?: 'Deleted user';
        $threadUrl = route('forum.topic.show', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug]);
    @endphp
    <a href="{{ $threadUrl }}" class="forum-thread-card group block text-inherit no-underline hover:no-underline">
        <div class="forum-thread-card__avatar">
            @if($avatarUrl)
                <img src="{{ $avatarUrl }}" alt="{{ $authorName }}" class="h-full w-full rounded-full object-cover" style="{{ $topic->user?->avatarImageStyle() }}" />
            @else
                {{ $authorInitial }}
            @endif
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <div class="font-semibold {{ $topic->user?->hasGroup('admin') ? 'text-primary-color-light' : 'text-gray-900' }}">{{ $authorName }}
                    @if($topic->user?->hasGroup('admin'))
                    <span class="font-normal text-gray-400 text-xs">(STEMMechanics)</span>
                    @endif
                </div>
                <div class="text-sm text-gray-400">{{ $topic->created_at?->format('j M Y g:i a') }}</div>
            </div>
            <div class="mt-1 flex flex-wrap items-center gap-2">
                @if($topic->is_pinned)
                    <i class="fa-solid fa-thumbtack"></i>
                @endif
                @if($topic->is_locked)
                    <i class="fa-solid fa-lock"></i>
                @endif
                <span class="text-lg font-bold text-gray-900 transition group-hover:text-primary-color">
                    {{ $topic->title }}
                </span>
                @if(isset($unreadTopicLookup[(string) $topic->id]))
                    <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-700">New</span>
                @endif
            </div>
            <div class="mt-2 flex flex-wrap gap-4 text-sm text-gray-500 items-center justify-between">
                <div class="flex gap-6">
                    <span class="inline-flex items-center gap-2 py-1.5 text-gray-600">
                        <i class="fa-regular fa-eye text-sky-500"></i>
                        <span>{{ number_format((int) $topic->view_count) }}</span>
                    </span>
                    <span class="inline-flex items-center gap-2 py-1.5 text-gray-600">
                        <i class="fa-regular fa-comment text-blue-500"></i>
                        <span>{{ $replyCount }}</span>
                    </span>
                </div>
                <span class="text-xs">Last post by {{ $lastPostAuthorName }} {{ $topic->last_post_at?->format('j M Y g:i a') ?? '-' }}</span>
            </div>
        </div>
    </a>
@endforeach
