@php
    $reactionMeta = [
        \App\Models\ForumPostReaction::TYPE_LOVE => ['label' => 'Love', 'icon' => 'fa-solid fa-heart', 'activeClasses' => 'bg-pink-100 text-pink-700 border-pink-200'],
        \App\Models\ForumPostReaction::TYPE_LIKE => ['label' => 'Like', 'icon' => 'fa-solid fa-thumbs-up', 'activeClasses' => 'bg-green-100 text-green-700 border-green-200'],
        \App\Models\ForumPostReaction::TYPE_DISLIKE => ['label' => 'Dislike', 'icon' => 'fa-solid fa-thumbs-down', 'activeClasses' => 'bg-amber-100 text-amber-700 border-amber-200'],
    ];
    $reactionBaseButtonClasses = 'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition';
    $reactionIdleClasses = 'border-gray-200 bg-white text-gray-700 hover:border-primary-color hover:text-primary-color';
    $displayName = $post->user?->forumDisplayName() ?: 'deleted';
    $canEditPost = $post->canEdit(auth()->user());
    $isAdmin = auth()->user()?->isAdmin();
    $canDeletePost = $isAdmin || (string) ($post->user_id ?? '') === (string) auth()->id();
    $isDeletedPost = $post->isDeleted();
    $avatarUrl = $post->user?->avatarMedia?->thumbnail;
    $parentDisplayName = $post->parentPost?->user?->forumDisplayName() ?: 'deleted';
@endphp

@php($authorInitial = strtoupper(mb_substr($displayName, 0, 1)))

<article
    id="post-{{ $post->id }}"
    class="{{ $isFirstPost ? 'mb-6 forum-post-card' : 'forum-post-card forum-post-card--reply' }}"
>
    <div class="forum-post-card__avatar">
        @if($avatarUrl)
            <img src="{{ $avatarUrl }}" alt="{{ $displayName }}" class="h-full w-full rounded-full object-cover" style="{{ $post->user?->avatarImageStyle() }}" />
        @else
            {{ $authorInitial }}
        @endif
    </div>
    <div class="min-w-0 flex-1">
        <div class="flex flex-col gap-3 border-b border-gray-200 pb-4 md:flex-row md:items-start md:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <div class="flex items-center gap-1 font-semibold {{ $topic->user?->hasGroup('admin') ? 'text-primary-color-light' : 'text-gray-900' }}">
                        @if($topic->user?->hasGroup('admin'))
                            <img src="{{ asset('toolbox-sm.png') }}" class="w-7 h-auto" alt="STEMMechanics">
                        @endif
                        {{ $displayName }}
                    </div>
                    <div class="text-sm text-gray-500">
                        {{ $post->created_at?->format('j M Y g:i a') }}
                    </div>
                    @if($post->edited_at)
                        <span class="text-sm text-gray-500">Edited</span>
                    @endif
                </div>
                <div class="mt-1 flex flex-wrap items-center gap-2 text-sm">
{{--                    <span class="font-medium text-gray-700">{{ $replyLabel }}</span>--}}
                    @if(! $isFirstPost && $post->parentPost)
                        <span class="text-gray-400">·</span>
                        <span class="text-gray-500">Replying to {{ $parentDisplayName }}</span>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-4">
                @if(!$isFirstPost && $canReply && !$topic->is_locked && ! $isDeletedPost)
                    <x-ui.button
                        type="button"
                        color="outline"
                        data-forum-reply-button
                        data-reply-title="{{ 'Reply to '.$displayName }}"
                        data-reply-body=""
                        data-reply-post-id="{{ $post->id }}"
                        class="!rounded-full !text-xs !border-0 !shadow-none !bg-gray-200 !py-0 !px-3 !font-semi-bold hover:!bg-gray-300 hover:!text-black"
                    >
                        <i class="fa-solid fa-reply mr-2"></i>Reply
                    </x-ui.button>
                @endif
            </div>
        </div>
        <div class="content mt-4">
            {!! $post->body !!}
        </div>
        <div class="mt-5 pr-2 flex items-center justify-between border-t border-gray-200 pt-4">
            @auth
                <div class="flex flex-wrap gap-2">
                @foreach($reactionMeta as $type => $meta)
                    @if(! $isDeletedPost)
                        <button
                            type="button"
                            data-forum-reaction-button
                            data-url="{{ route('forum.post.reaction', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug, 'forumPost' => $post->id, 'sort' => $replySort]) }}"
                            data-post-id="{{ $post->id }}"
                            data-type="{{ $type }}"
                            data-active-classes="{{ $meta['activeClasses'] }}"
                            data-label="{{ $meta['label'] }}"
                            title="{{ $meta['label'] }}: {{ $post->reactionTooltipFor($type) }}"
                            class="{{ $reactionBaseButtonClasses }} {{ $post->reactionTypeFor(auth()->user()) === $type ? $meta['activeClasses'] : $reactionIdleClasses }}"
                        >
                            <i class="{{ $meta['icon'] }}"></i>
                            <span data-forum-reaction-count>{{ $post->reactionCountFor($type) }}</span>
                        </button>
                    @endif
                @endforeach
                </div>
                <div class="flex flex-wrap gap-4">
                @if(! $isDeletedPost)
                    <button
                            type="button"
                            class="text-sm text-gray-600 hover:text-primary-color"
                            data-forum-report-button
                            data-report-post-id="{{ $post->id }}"
                            data-report-action="{{ route('forum.post.report', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug, 'forumPost' => $post->id, 'sort' => $replySort]) }}"
                            data-report-author="{{ $displayName }}"
                    ><i class="fa-regular fa-flag"></i></button>
                @endif
                @if($canEditPost && ! $isDeletedPost)
                    <button
                            type="button"
                            class="text-sm text-gray-600 hover:text-primary-color"
                            data-forum-edit-button
                            data-edit-title="{{ $isFirstPost ? 'Edit Thread Post' : 'Edit Reply' }}"
                            data-edit-body="{{ $post->body }}"
                            data-edit-action="{{ route('forum.post.update', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug, 'forumPost' => $post->id, 'sort' => $replySort]) }}"
                            data-edit-method="PUT"
                            data-edit-post-id="{{ $post->id }}"
                            data-submit-label="Save Changes"
                    ><i class="fa-solid fa-pen-to-square"></i></button>
                @endif
                @if($canDeletePost && ! $isFirstPost)
                    <form method="POST" action="{{ route('forum.post.destroy', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug, 'forumPost' => $post->id, 'sort' => $replySort]) }}" x-data x-on:submit.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete post?', 'Are you sure you want to delete this reply?', $el)">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-600 hover:text-red-700"><i class="fa-solid fa-trash"></i></button>
                    </form>
                @endif
                </div>
            @else
                <div class="flex flex-wrap gap-2 text-sm text-gray-500">
                    @foreach($reactionMeta as $type => $meta)
                        <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5" title="{{ $meta['label'] }}: {{ $post->reactionTooltipFor($type) }}">
                            <i class="{{ $meta['icon'] }}"></i>
                            <span>{{ $post->reactionCountFor($type) }}</span>
                        </span>
                    @endforeach
                </div>
            @endauth
        </div>
    </div>
</article>
