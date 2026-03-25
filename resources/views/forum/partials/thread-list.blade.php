@foreach($topics as $topic)
    @php
        $author = $topic->user;
        $authorName = $author?->forumDisplayName() ?: 'deleted';
        $replyCount = max(0, $topic->posts_count - 1);
        $avatarUrl = $author?->avatarImageUrl();
        $isDeletedAuthor = $authorName === 'deleted';
        $lastPostAuthorName = $topic->lastPostUser?->forumDisplayName() ?: 'deleted';
        $threadUrl = route('forum.topic.show', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug]);
    @endphp
    <a href="{{ $threadUrl }}" class="forum-thread-card group block text-inherit no-underline hover:no-underline">
        <div
            class="forum-thread-card__avatar {{ $isDeletedAuthor ? 'bg-gradient-to-br from-gray-200 to-gray-400 text-gray-600' : 'text-white' }}"
            @if(! $isDeletedAuthor && ! $author?->shouldRenderAvatarImage()) style="background: {{ $author?->resolvedAvatarBackgroundColor() ?? '#374151' }}" @endif
        >
            @if($isDeletedAuthor)
                <i class="fa-solid fa-user-slash text-base"></i>
            @elseif($author?->shouldRenderAvatarImage())
                <img src="{{ $avatarUrl }}" alt="{{ $authorName }}" class="h-full w-full rounded-full object-cover" style="{{ $author->avatarImageStyle() }}" />
            @elseif($author?->resolvedAvatarMode() === \App\Models\User::AVATAR_MODE_ICON && $author->resolvedAvatarIconClass())
                <i class="{{ $author->resolvedAvatarIconClass() }} text-xl"></i>
            @else
                {{ $author?->resolvedAvatarLetters() ?? 'U' }}
            @endif
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-1 font-semibold {{ $isDeletedAuthor ? 'text-gray-500' : ($topic->user?->hasGroup('admin') ? 'text-primary-color-light' : 'text-gray-900') }}">
                    @if($topic->user?->hasGroup('admin'))
                        <img src="{{ asset('toolbox-sm.png') }}" class="w-7 h-auto" alt="STEMMechanics">
                    @endif
                    {{ $authorName }}
                </div>
                <div class="text-sm text-gray-400">{{ $topic->created_at?->format('j M Y g:i a') }}</div>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                @if($topic->is_pinned)
                    <i class="fa-solid fa-thumbtack"></i>
                @endif
                @if($topic->is_locked)
                    <i class="fa-solid fa-lock"></i>
                @endif
                <span class="forum-discussion-title text-lg font-bold text-gray-700 transition group-hover:text-primary-color">
                    {!! $topic->formattedTitle() !!}
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
