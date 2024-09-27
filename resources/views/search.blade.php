<x-layout>
    <x-mast title="Search" description='Results for "{{ $search }}"' />
    <x-container>
        <section class="bg-gray-100">
            <h2 class="text-2xl font-bold my-6">Posts</h2>
            @if($posts->isEmpty())
                <x-container class="mt-8">
                    <x-none-found item="posts" search="{{ request()->get('search') }}" />
                </x-container>
            @else
                <x-container class="mt-4" inner-class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 w-full">
                    @foreach ($posts as $post)
                        <x-panel-post :post="$post" />
                    @endforeach
                </x-container>
                <x-container>
                    {{ $posts->appends(request()->except('post'))->links('', ['pageName' => 'post']) }}
                </x-container>
            @endif
        </section>

        <section class="bg-gray-100">
            <h2 class="text-2xl font-bold my-6">Workshops</h2>
            @if($workshops->isEmpty())
                <x-container class="mt-8">
                    <x-none-found item="workshops" search="{{ request()->get('search') }}" />
                </x-container>
            @else
                <x-container class="mt-4" inner-class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 w-full">
                    @foreach ($workshops as $workshop)
                        <x-panel-workshop :workshop="$workshop" />
                    @endforeach
                </x-container>
                <x-container>
                    {{ $workshops->appends(request()->except('workshop'))->links('', ['pageName' => 'workshop']) }}
                </x-container>
            @endif
        </section>
    </x-container>
</x-layout>
