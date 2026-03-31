@if($post->attachments->isNotEmpty())
    <div class="mt-5">
        <div class="text-sm font-semibold text-gray-700 border-t border-gray-200 pt-4">Attachments</div>
        <div class="mt-1 space-y-2" data-forum-attachments-list>
            @foreach($post->attachments as $attachment)
                <a
                    href="{{ route('forum.post.attachment.download', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug, 'forumPost' => $post->id, 'attachment' => $attachment->id]) }}"
                    class="flex items-center gap-3 text-sm text-gray-700 transition hover:border-primary-color hover:text-primary-color"
                >
                    <div class="flex-1 flex gap-3 items-center">
                        <i class="{{ $attachment->iconClass() }} text-base text-gray-500"></i>
                        <div class="truncate font-medium">{{ $attachment->displayName() }}</div>
                        <div class="text-xs text-gray-500">({{ $attachment->sizeHuman() }})</div>
                    </div>
                    <i class="fa-solid fa-download text-xs text-gray-400"></i>
                </a>
            @endforeach
        </div>
    </div>
@endif
