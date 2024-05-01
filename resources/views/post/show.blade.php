<x-layout>
    <x-container>
        <x-ui.image-hero :image="$post->hero?->url" class="my-8" />
        <h1 class="text-3xl font-bold mb-2">{{ $post->title }}</h1>
        <div class="flex justify-between align-middle mb-4">
            <p class="text-gray-500 font-semibold">{{ $post->created_at->format('F j, Y') }}</p>
            @if(auth()->user()?->admin)
                <x-ui.button href="{{ route('admin.post.edit', $post) }}">Edit Article</x-ui.button>
            @endif
        </div>
        <article class="content mb-4">{!! $post->content !!}</article>
        <x-ui.gallery class="mt-16" value="{{ \App\Helpers::arrayToString($post->files('gallery')->pluck('name')->toArray()) }}" />
        <x-ui.filelist class="mt-16" label="Videos" value="{!! $post->files('videos')->orderBy('name')->get() !!}" />
        <x-ui.filelist class="mt-16" value="{!! $post->files()->orderBy('name')->get() !!}" />
    </x-container>
</x-layout>
