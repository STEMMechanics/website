@if($posts->isEmpty())
    <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-6 text-sm text-gray-500">
        No replies yet.
    </div>
@else
    @php
        $postCollection = $posts->getCollection();
        $postsById = $postCollection->keyBy(fn ($post) => (string) $post->id);
        $childrenByParent = $postCollection->groupBy(function ($post) use ($postsById) {
            $parentId = trim((string) ($post->parent_forum_post_id ?? ''));

            return $parentId !== '' && $postsById->has($parentId) ? $parentId : 'root';
        });

        $replyLabels = $postCollection
            ->values()
            ->mapWithKeys(function ($post, $index) use ($posts, $replySort) {
                $label = 'Reply #'.($replySort === 'oldest'
                    ? (($posts->firstItem() ?? 1) + $index)
                    : (($posts->total() - (($posts->firstItem() ?? 1) - 1)) - $index));

                return [(string) $post->id => $label];
            });
    @endphp
    <div class="forum-post-stack">
        @foreach($childrenByParent->get('root', collect()) as $post)
            @include('forum.partials.post-thread', [
                'post' => $post,
                'childrenByParent' => $childrenByParent,
                'replyLabels' => $replyLabels,
                'category' => $category,
                'topic' => $topic,
                'replySort' => $replySort,
                'canReply' => $canReply,
                'depth' => 1,
            ])
        @endforeach
    </div>
@endif
