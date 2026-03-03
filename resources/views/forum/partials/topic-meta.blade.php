<div class="-mt-8 mb-6 rounded-b-lg border border-gray-200 bg-white p-4 sm:p-5">
    <div class="flex flex-col gap-3 lg:flex-row">
        <div class="items-center gap-3 justify-between hidden flex-1 md:flex lg:justify-start md:gap-12 md:items-start">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">Comments</div>
                <div class="mt-1 text-lg font-semibold text-gray-900">{{ number_format($commentCount) }}</div>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">Last Comment</div>
                @if($lastCommentPost)
                    <a
                        href="{{ route('forum.topic.show', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug, 'sort' => $replySort ?? 'oldest']) }}#post-{{ $lastCommentPost->id }}"
                        class="mt-1 inline-flex flex-wrap items-center gap-2 text-sm text-gray-700 hover:text-primary-color"
                    >
                        <span class="font-semibold">{{ $lastCommentAuthorName }}</span>
                        <span class="text-gray-400">·</span>
                        <span>{{ $lastCommentPost->created_at?->format('j M Y g:i a') }}</span>
                    </a>
                @else
                    <div class="mt-1 text-sm text-gray-500">No comments yet.</div>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-3 justify-between md:justify-end">
            @auth
                <form method="POST" action="{{ route('forum.topic.notifications', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug, 'sort' => $replySort ?? 'oldest']) }}" class="flex items-center gap-3" data-forum-notifications-form>
                    @csrf
                    <input type="hidden" name="notifications_enabled" value="0">
                    <x-ui.checkbox name="notifications_enabled" value="1" label="Notifications" :checked="$notificationsEnabled" inline="true" noWrapper="true" small="true" data-forum-notifications-toggle />
                </form>
            @endauth

            <div class="flex gap-3 items-center">
                @if($canReply && !$topic->is_locked)
                    <x-ui.button
                        type="button"
                        color="primary"
                        data-forum-reply-button
                        data-reply-title="Reply to Thread"
                        data-reply-body=""
                        data-reply-post-id=""
                    >
                        Reply to Thread
                    </x-ui.button>
                @endif

                @if(auth()->user()?->isAdmin())
                    <form method="POST" action="{{ route('forum.topic.pin', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug]) }}">
                        @csrf
                        <x-ui.button type="submit" color="outline" class="!px-3" title="{{ $topic->is_pinned ? 'Unpin Thread' : 'Pin Thread' }}">
                            <i class="fa-solid fa-thumbtack"></i>
                        </x-ui.button>
                    </form>
                    <form method="POST" action="{{ route('forum.topic.lock', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug]) }}">
                        @csrf
                        <x-ui.button type="submit" color="outline" class="!px-3" title="{{ $topic->is_locked ? 'Unlock Thread' : 'Lock Thread' }}">
                            <i class="fa-solid {{ $topic->is_locked ? 'fa-lock-open' : 'fa-lock' }}"></i>
                        </x-ui.button>
                    </form>
                    <form method="POST" action="{{ route('forum.topic.destroy', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug]) }}" x-data x-on:submit.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete thread?', 'Are you sure you want to delete this thread and all replies?', $el)">
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="submit" color="danger" class="!px-3" title="Delete Thread">
                            <i class="fa-solid fa-trash"></i>
                        </x-ui.button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
