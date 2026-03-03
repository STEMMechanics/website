@php
    $children = $childrenByParent->get((string) $post->id, collect());
@endphp

<div class="forum-post-thread">
    @include('forum.partials.post', [
        'post' => $post,
        'isFirstPost' => false,
        'replyLabel' => $replyLabels[(string) $post->id] ?? '',
        'category' => $category,
        'topic' => $topic,
        'replySort' => $replySort,
        'canReply' => $canReply,
        'replyDraftBody' => '',
        'replyDepth' => $depth,
    ])

    @if($children->isNotEmpty())
        <div class="forum-post-children">
            @foreach($children as $childPost)
                @include('forum.partials.post-thread', [
                    'post' => $childPost,
                    'childrenByParent' => $childrenByParent,
                    'replyLabels' => $replyLabels,
                    'category' => $category,
                    'topic' => $topic,
                    'replySort' => $replySort,
                    'canReply' => $canReply,
                    'depth' => $depth + 1,
                ])
            @endforeach
        </div>
    @endif
</div>
