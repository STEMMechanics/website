<div class="-mt-4 flex flex-col mb-4">
    <div class="flex flex-col sm:flex-row gap-3">
        @auth
            <form method="POST" action="{{ route('forum.topic.notifications', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug, 'sort' => $replySort ?? 'oldest']) }}" class="flex items-center gap-3" data-forum-notifications-form>
                @csrf
                <input type="hidden" name="notifications_enabled" value="0">
                <x-ui.checkbox name="notifications_enabled" value="1" label="Notifications" :checked="$notificationsEnabled" inline="true" noWrapper="true" small="true" data-forum-notifications-toggle />
            </form>
        @endauth

        <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:justify-end">
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

            @if($topic->canEditTitle(auth()->user()))
                <x-ui.button type="button" color="outline" class="sm:px-4!" data-forum-title-button>
                    <i class="fa-solid fa-pen mr-2 sm:mr-0 sm:py-1.25!"></i><span class="sm:hidden">Edit Title</span>
                </x-ui.button>
            @endif

            @if(auth()->user()?->isAdmin() || (string) $topic->user_id === (string) auth()->id())
                @if(auth()->user()?->isAdmin())
                    <div class="flex gap-3">
                        <form class="flex-1 flex" method="POST" action="{{ route('forum.topic.pin', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug]) }}">
                            @csrf
                            <x-ui.button type="submit" color="outline" class="w-full sm:px-4! sm:w-auto" title="{{ $topic->is_pinned ? 'Unpin Thread' : 'Pin Thread' }}">
                                <i class="fa-solid fa-thumbtack mr-2 sm:mr-0 sm:py-1.25!"></i><span class="sm:hidden">{{ $topic->is_pinned ? 'Unpin Thread' : 'Pin Thread' }}</span>
                            </x-ui.button>
                        </form>
                        <form class="flex-1 flex" method="POST" action="{{ route('forum.topic.lock', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug]) }}">
                            @csrf
                            <x-ui.button type="submit" color="outline" class="w-full sm:px-4! sm:w-auto" title="{{ $topic->is_locked ? 'Unlock Thread' : 'Lock Thread' }}">
                                <i class="fa-solid {{ $topic->is_locked ? 'fa-lock-open' : 'fa-lock' }} mr-2 sm:mr-0 sm:py-1.25!"></i><span class="sm:hidden">{{ $topic->is_locked ? 'Unlock Thread' : 'Lock Thread' }}</span>
                            </x-ui.button>
                        </form>
                    </div>
                @endif
                <form class="w-full sm:w-auto" method="POST" action="{{ route('forum.topic.destroy', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug]) }}" x-data x-on:submit.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete thread?', 'Are you sure you want to delete this thread and all replies?', $el)">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" color="danger" class="w-full sm:px-4! sm:w-auto" title="Delete Thread">
                        <i class="fa-solid fa-trash mr-2 sm:mr-0 sm:py-1.25!"></i><span class="sm:hidden">Delete Thread</span>
                    </x-ui.button>
                </form>
            @endif
        </div>
    </div>
</div>
