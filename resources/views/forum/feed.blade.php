<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:sm="https://stemmechanics.com/ns/rss/forum">
<channel>
    <title>STEMMechanics Discussions</title>
    <link>{{ route('forum.index') }}</link>
    <description>Public STEMMechanics discussion topics and recent activity.</description>
    <language>en-au</language>
    <lastBuildDate>{{ \Illuminate\Support\Carbon::parse($generatedAt)->toRssString() }}</lastBuildDate>
    <atom:link href="{{ route('forum.feed') }}" rel="self" type="application/rss+xml" />
@foreach($items as $item)
    <item>
        <title>{{ $item['title'] }}</title>
        <link>{{ $item['link'] }}</link>
        <guid isPermaLink="true">{{ $item['guid'] }}</guid>
        <pubDate>{{ \Illuminate\Support\Carbon::parse($item['pubDate'])->toRssString() }}</pubDate>
        <description>{{ $item['description'] }}</description>
        @if(! empty($item['enclosure']['url']) && ! empty($item['enclosure']['type']))
        <enclosure url="{{ $item['enclosure']['url'] }}" type="{{ $item['enclosure']['type'] }}" />
        @endif
        <sm:category>{{ $item['category'] }}</sm:category>
        <sm:author>{{ $item['author'] }}</sm:author>
        <sm:excerpt>{{ $item['excerpt'] }}</sm:excerpt>
        <sm:replyCount>{{ $item['replyCount'] }}</sm:replyCount>
        <sm:locked>{{ $item['locked'] }}</sm:locked>
        <sm:pinned>{{ $item['pinned'] }}</sm:pinned>
        <sm:updatedAt>{{ \Illuminate\Support\Carbon::parse($item['updatedAt'])->toAtomString() }}</sm:updatedAt>
    </item>
@endforeach
</channel>
</rss>
