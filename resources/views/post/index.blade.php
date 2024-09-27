<x-layout>
    <x-slot name="title">Blog</x-slot>
    <x-mast>Blog</x-mast>
    <section class="bg-gray-100">
        @if($posts->isEmpty())
            <x-container class="mt-8">
                <x-none-found item="posts" />
            </x-container>
        @else
            <x-container class="mt-4" inner-class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 w-full">
                @foreach ($posts as $post)
                    <x-panel-post :post="$post" />
                @endforeach
            </x-container>
            <x-container>
                {{ $posts->appends(request()->query())->links() }}
            </x-container>
        @endif
    </section>
</x-layout>
