@php
    $removedAttachmentIds = collect($removedAttachmentIds ?? [])
        ->map(fn ($id): string => (string) $id)
        ->filter()
        ->all();

    $attachments = $post->attachments
        ->reject(fn ($attachment): bool => in_array((string) $attachment->id, $removedAttachmentIds, true))
        ->values();
@endphp

@if($attachments->isNotEmpty())
    @foreach($attachments as $attachment)
        <div
            class="flex items-center gap-3 text-sm text-gray-700"
            data-forum-attachment-row
            data-attachment-id="{{ $attachment->id }}"
        >
            <a
                href="{{ route('forum.post.attachment.download', ['categorySlug' => $category->slug, 'topicSlug' => $topic->slug, 'forumPost' => $post->id, 'attachment' => $attachment->id]) }}"
                class="flex min-w-0 flex-1 items-center gap-3 transition hover:text-primary-color"
            >
                <div class="flex-1 flex gap-3 items-center">
                    <i class="{{ $attachment->iconClass() }} text-base text-gray-500"></i>
                    <div class="truncate font-medium">{{ $attachment->displayName() }}</div>
                    <div class="text-xs text-gray-500">{{ $attachment->sizeHuman() }}</div>
                </div>
            </a>
            <button
                type="button"
                class="text-red-600 transition hover:text-red-700"
                data-forum-attachment-remove
                data-attachment-id="{{ $attachment->id }}"
                title="Remove attachment"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    @endforeach
@endif
