<x-layout
    :title="$page->seo_title ?: $page->title"
    :description="$page->seo_description"
    :canonical="$page->path"
    :ogImage="$page->hero?->url"
    :noindex="$page->seo_noindex"
>
    @php
        $mastBackTitle = $page->resolvedMastBackTitle();
        $mastBackUrl = $page->resolvedMastBackUrl();
    @endphp

    @if($page->show_mast)
        <x-mast :backUrl="$mastBackUrl" :backTitle="$mastBackTitle">{{ $page->title }}</x-mast>
    @endif

    <x-container class="py-8">
    @if($page->hero?->url)
            <x-ui.image-hero :image="$page->hero->url" class="mb-8" />
        @endif

        @unless($page->show_mast)
            <h1 class="text-3xl font-bold mb-4">{{ $page->title }}</h1>
        @endunless
        <article class="content">{!! \App\Support\HtmlContentTransformer::collapseSectionsForDisplay((string) ($page->content ?? '')) !!}</article>
    </x-container>
</x-layout>
