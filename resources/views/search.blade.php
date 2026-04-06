@php($hasSearch = trim((string) $search) !== '')
@php($searchScopeLabel = $storeSearchEnabled ? 'workshops and store products' : 'workshops')
@php($isAdmin = (bool) (auth()->user()?->isAdmin() ?? false))

<x-layout
    :title="$hasSearch ? ('Search: ' . $search) : 'Search'"
    :description="$hasSearch ? ('Search results for ' . $search) : ('Search ' . $searchScopeLabel . ' across STEMMechanics')"
    :canonical="route('search.index', $hasSearch ? ['q' => $search] : [])"
    :noindex="true"
>
    <x-mast title="Search" :description="$hasSearch ? ('Results for \"' . $search . '\"') : ('Search ' . $searchScopeLabel . ' across the site')" />
    <x-container class="py-8">
        <section class="mb-8">
            <x-ui.search name="q" label="Search the site" />
        </section>

        @if(!$hasSearch)
            <section class="rounded-3xl border border-dashed border-gray-300 bg-gray-50 px-6 py-10">
                <x-none-found
                    title="Start a new search"
                    :message="'Use the search bar above to find '.$searchScopeLabel.' across the site.'"
                    search=""
                />
            </section>
        @else
            @if($storeSearchEnabled)
                <section class="mb-8 rounded-3xl border border-gray-200 bg-gray-50 p-6">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <h2 class="text-2xl font-bold my-1">Store Products</h2>
                        <div class="text-sm text-gray-500">
                            {{ number_format((int) $products->total()) }} {{ (int) $products->total() === 1 ? 'result' : 'results' }}
                        </div>
                    </div>
                    @if(!$products->isEmpty())
                        @include('search.partials.store-products', [
                            'products' => $products,
                            'cartPayload' => $cartPayload,
                            'isAdmin' => $isAdmin,
                            'bestSellerProductIds' => $bestSellerProductIds,
                        ])
                    @endif
                </section>
            @endif

            <section class="bg-gray-50 rounded-3xl border border-gray-200 p-6">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <h2 class="text-2xl font-bold my-1">Workshops</h2>
                        <div class="text-sm text-gray-500">
                            {{ number_format((int) $workshops->total()) }} {{ (int) $workshops->total() === 1 ? 'result' : 'results' }}
                        </div>
                    </div>
                    @if(!$workshops->isEmpty())
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
