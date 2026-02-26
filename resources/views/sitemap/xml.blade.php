<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($pages as $page)
    <url>
        <loc>{{ $page['loc'] }}</loc>
        @if($page['lastmod'])
            <lastmod>{{ \Illuminate\Support\Carbon::parse($page['lastmod'])->toAtomString() }}</lastmod>
        @endif
    </url>
@endforeach
@foreach($workshops as $workshop)
    <url>
        <loc>{{ route('workshop.show', $workshop) }}</loc>
        @if($workshop->updated_at)
            <lastmod>{{ \Illuminate\Support\Carbon::parse($workshop->updated_at)->toAtomString() }}</lastmod>
        @endif
    </url>
@endforeach
</urlset>
