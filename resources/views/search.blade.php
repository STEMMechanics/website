@php($hasSearch = trim((string) $search) !== '')

<x-layout
    :title="$hasSearch ? ('Search: ' . $search) : 'Search'"
    :description="$hasSearch ? ('Search results for ' . $search) : 'Search workshops across STEMMechanics'"
    :canonical="route('search.index', $hasSearch ? ['q' => $search] : [])"
    :noindex="true"
>
    <x-mast title="Search" :description="$hasSearch ? ('Results for \"' . $search . '\"') : 'Search workshops across the site'" />
    <x-container class="py-8">
        <section class="mb-8 rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Refine Search</div>
            <div class="mt-3 max-w-3xl">
                <x-ui.search name="q" label="Search the site" />
            </div>
        </section>

        @if(!$hasSearch)
            <section class="rounded-3xl border border-dashed border-gray-300 bg-gray-50 px-6 py-10">
                <x-none-found
                    title="Start a new search"
                    message="Use the search bar above to find workshops across the site."
                    search=""
                />
            </section>
        @else
{{--        <section class="bg-gray-100">--}}
{{--            <h2 class="text-2xl font-bold my-6">Posts</h2>--}}
{{--            @if($posts->isEmpty())--}}
{{--                <x-container class="mt-8">--}}
{{--                    <x-none-found item="posts" search="{{ request()->get('search') }}" />--}}
{{--                </x-container>--}}
{{--            @else--}}
{{--                <x-container class="mt-4" inner-class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 w-full">--}}
{{--                    @foreach ($posts as $post)--}}
{{--                        <x-panel-post :post="$post" />--}}
{{--                    @endforeach--}}
{{--                </x-container>--}}
{{--                <x-container>--}}
{{--                    {{ $posts->appends(request()->except('post'))->links('', ['pageName' => 'post']) }}--}}
{{--                </x-container>--}}
{{--            @endif--}}
{{--        </section>--}}

        <section class="bg-gray-100 rounded-3xl border border-gray-200 px-6 py-2">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <h2 class="text-2xl font-bold my-6">Workshops</h2>
                <div class="mb-6 text-sm text-gray-500">
                    {{ number_format((int) $workshops->total()) }} {{ (int) $workshops->total() === 1 ? 'result' : 'results' }}
                </div>
            </div>
            @if($workshops->isEmpty())
                <div class="mt-8">
                    <x-none-found item="workshops" :search="$search" />
                </div>
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
        @endif
    </x-container>
</x-layout>
