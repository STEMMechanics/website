<x-mail::message>
@php
    $displayName = trim((string) ($user->firstname ?? ''));
    if ($displayName === '') {
        $displayName = trim((string) ($user->username ?? ''));
    }
    $greetingName = $displayName !== '' ? $displayName : 'there';
    $latestDigest = $threadDigests->first();
    $latestPost = $latestDigest['posts']->last();
    $totalUnreadThreads = $threadDigests->count();
    $additionalUnreadPosts = max(0, $totalUnreadPosts - 1);
    $threadPreview = $threadDigests->take(5);
    $threadsByCategory = $threadPreview->groupBy(function (array $threadDigest): string {
        return (string) ($threadDigest['topic']->category->name ?? 'Other');
    });
    $additionalUnreadThreads = max(0, $threadDigests->count() - $threadPreview->count());
    $latestPreview = \Illuminate\Support\Str::limit(
        \App\Support\ForumContent::plainText((string) $latestPost->body),
        280
    );
@endphp

Hi {{ $greetingName }},

There are **{{ $totalUnreadPosts }} unread {{ \Illuminate\Support\Str::plural('reply', $totalUnreadPosts) }}** across **{{ $totalUnreadThreads }} unread {{ \Illuminate\Support\Str::plural('discussion', $totalUnreadThreads) }}**.

@foreach($threadsByCategory as $categoryName => $threads)
### {{ $categoryName }}

@foreach($threads as $threadDigest)
@php
    $threadUnreadCount = (int) $threadDigest['posts']->count();
    $threadLatestAt = $threadDigest['posts']->last()?->created_at?->format('j M Y g:i a') ?? '-';
@endphp
- [{{ $threadDigest['topic']->title }}]({{ $threadDigest['url'] }}) - {{ $threadUnreadCount }} unread {{ \Illuminate\Support\Str::plural('reply', $threadUnreadCount) }} - latest {{ $threadLatestAt }}
@endforeach
@endforeach

@if($additionalUnreadThreads > 0)
There are also {{ $additionalUnreadThreads }} other unread {{ \Illuminate\Support\Str::plural('discussion', $additionalUnreadThreads) }}.
@endif


@if(! empty($unsubscribeLink))
If you no longer want discussion notifications, [unsubscribe from all]({{ $unsubscribeLink }}).
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
