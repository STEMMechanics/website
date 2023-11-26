@props(['workshop'])

<x-card class="workshop-card grow">
    <div class="flex h-full flex-col">
        <h3 class="mb-4 text-2xl">{{ $workshop->title }}</h3>
        <p class="mb-4 grow">{{ Str::limit(strip_tags($workshop->content), 200, '...') }}</p>

        <a href="/" class="btn btn-block">View</a>
    </div>
</x-card>
