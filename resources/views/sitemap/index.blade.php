{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@for($page = 1; $page <= $pageCount; $page++)
    <sitemap><loc>{{ route('sitemap.xml', ['page' => $page]) }}</loc></sitemap>
@endfor
</sitemapindex>
