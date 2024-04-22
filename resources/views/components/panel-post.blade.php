@props(['post'])

<a href="{{ route('post.show', $post) }}" class="flex flex-col bg-white border rounded-lg overflow-hidden hover:shadow-lg hover:scale-[101%] transition-all flex-1 {{ $attributes->get('class') }}">
    <img src="{{ $post->hero?->url }}?md" alt="{{ $post->title }}" class="w-full h-48 object-cover object-center">
    <div class="p-4 flex flex-col">
        <p class="text-gray-600 text-xs mb-2">{{ $post->created_at->format('j M Y') }}</p>
        <h2 class="text-xl font-bold mb-4">{{ $post->title }}</h2>
        <p class="text-sm flex-grow">{{ Str::words(strip_tags($post->content), 20) }}</p>
        <p class="flex text-sm mt-6 mr-3 justify-end items-center">Read More <i class="fa-solid fa-angle-right ml-2"></i></p>
    </div>
</a>
