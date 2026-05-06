<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:sm="https://stemmechanics.com/ns/rss/workshop">
<channel>
    <title>STEMMechanics Workshops</title>
    <link>{{ route('workshop.index') }}</link>
    <description>Upcoming STEMMechanics workshops and public event updates.</description>
    <language>en-au</language>
    <lastBuildDate>{{ \Illuminate\Support\Carbon::parse($generatedAt)->toRssString() }}</lastBuildDate>
    <atom:link href="{{ route('workshop.feed') }}" rel="self" type="application/rss+xml" />
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
        @if(! empty($item['startDate']))
        <sm:startDate>{{ \Illuminate\Support\Carbon::parse($item['startDate'])->toAtomString() }}</sm:startDate>
        @endif
        @if(! empty($item['endDate']))
        <sm:endDate>{{ \Illuminate\Support\Carbon::parse($item['endDate'])->toAtomString() }}</sm:endDate>
        @endif
        <sm:location>{{ $item['location'] }}</sm:location>
        <sm:price>{{ $item['price'] }}</sm:price>
        <sm:ages>{{ $item['ages'] !== '' ? $item['ages'] : 'All ages' }}</sm:ages>
        <sm:status>{{ $item['status'] }}</sm:status>
    </item>
@endforeach
</channel>
</rss>
