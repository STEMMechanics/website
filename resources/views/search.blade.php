@php($hasSearch = trim((string) $search) !== '')
@php($searchScopeLabel = $storeSearchEnabled && $searchProducts ? ($searchWorkshops ? 'workshops and store products' : 'store products') : 'workshops')
@php($isAdmin = (bool) (auth()->user()?->isAdmin() ?? false))

<x-layout
    :title="$hasSearch ? ('Search: ' . $search) : 'Search'"
    :description="$hasSearch ? ('Search results for ' . $search) : ('Search ' . $searchScopeLabel . ' across STEMMechanics')"
    :canonical="route('search.index', $hasSearch ? ['q' => $search] : [])"
    :noindex="true"
>
    <x-mast title="Search" :description="$hasSearch ? ('Results for \"' . $search . '\"') : ('Search ' . $searchScopeLabel . ' across the site')" />
    <x-container class="py-8">
        <x-ui.dynamic-list name="search">

        <div x-data="{ searchProducts: @js($searchProducts), searchWorkshops: @js($searchWorkshops), searchScopeError: @js($searchScopeError) }">
        <section class="mb-8 rounded-2xl border border-gray-200 bg-gray-50 p-4 sm:p-6">
            <form method="GET" action="{{ route('search.index') }}" x-ref="searchForm" x-on:submit="searchScopeError = !searchProducts && !searchWorkshops; if (searchScopeError) { $event.preventDefault(); $el.querySelector('[data-open-dialog=search-filters]').click(); } else { $el.querySelectorAll('dialog[open]').forEach(dialog => dialog.close()); }">
                <label for="results-search-query" class="sr-only">Search the site</label>
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="relative min-w-0 flex-1">
                        <x-ui.input-control id="results-search-query" type="search" name="q" :value="$search" placeholder="Search the site" class="min-h-11 pr-11!" />
                        <button type="submit" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-500 hover:text-primary-color" aria-label="Search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button>
                    </div>
                    <x-ui.button color="outline" class="h-11 shrink-0 rounded-lg px-3 sm:px-4" data-open-dialog="search-filters" aria-haspopup="dialog"><i class="fa-solid fa-filter hidden sm:inline sm:mr-2" aria-hidden="true"></i>Filters</x-ui.button>
                    <x-ui.button color="outline" class="h-11 shrink-0 rounded-lg px-3 sm:px-4" data-open-dialog="search-sort" aria-haspopup="dialog"><i class="fa-solid fa-arrow-down-short-wide hidden sm:inline sm:mr-2" aria-hidden="true"></i>Sort<i class="fa-solid fa-chevron-down ml-2 text-xs" aria-hidden="true"></i></x-ui.button>
                </div>
                <x-ui.list-dialog id="search-filters" title="Filter results">
                    <div class="space-y-5 p-5">
                        <div>
                            <h3 class="font-semibold text-gray-900">Search in</h3>
                            <x-ui.search-scopes :products="$searchProducts" :workshops="$searchWorkshops" />
                            @if(!$storeSearchEnabled)
                                <p x-show="searchProducts" class="mt-3 text-sm text-gray-600">Store search is currently unavailable.</p>
                            @endif
                        </div>
                        @if($storeSearchEnabled)
                            @include('search.partials.filters', ['scope' => 'search_products', 'selection' => 'searchProducts', 'enabled' => $searchProducts, 'title' => 'Store filters', 'description' => 'store products'])
                        @endif
                        @include('search.partials.filters', ['scope' => 'search_workshops', 'selection' => 'searchWorkshops', 'enabled' => $searchWorkshops, 'title' => 'Workshop filters', 'description' => 'workshops'])
                    </div>
                    <div class="sm-dialog-footer">
                        <x-ui.button color="outline" x-on:click="$refs.searchForm.querySelectorAll('[data-search-filter]').forEach(input => input.value = '');">Clear filters</x-ui.button>
                        <x-ui.button type="submit">Apply filters</x-ui.button>
                    </div>
                </x-ui.list-dialog>
                <x-ui.list-dialog id="search-sort" title="Sort results" kind="sort">
                    <div class="space-y-5 p-5">
                        @if($storeSearchEnabled)
                            @include('search.partials.sort', ['scope' => 'search_products', 'selection' => 'searchProducts', 'enabled' => $searchProducts, 'title' => 'Store products'])
                        @endif
                        @include('search.partials.sort', ['scope' => 'search_workshops', 'selection' => 'searchWorkshops', 'enabled' => $searchWorkshops, 'title' => 'Workshops'])
                        <p x-show="!searchProducts && !searchWorkshops" class="text-sm text-gray-600">Choose Store or Workshops in Filters first.</p>
                    </div>
                    <div class="sm-dialog-footer"><x-ui.button color="outline" data-close-dialog>Cancel</x-ui.button><x-ui.button type="submit">Apply sort</x-ui.button></div>
                </x-ui.list-dialog>
            </form>
        </section>

        @if($searchScopeError)
            <p class="text-sm text-gray-600">Select at least one: Store or Workshops in Filters.</p>
        @elseif(!$hasSearch)
            <section class="rounded-3xl border border-dashed border-gray-300 bg-gray-50 px-6 py-10">
                <x-none-found
                    title="Start a new search"
                    :message="'Use the search bar above to find '.$searchScopeLabel.' across the site.'"
                    search=""
                />
            </section>
        @else
            @if($storeSearchEnabled && $searchProducts)
                <section x-show="searchProducts" class="mb-8 rounded-3xl border border-gray-200 bg-gray-50 p-4 sm:p-6">
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

            @if($searchWorkshops)
            <section x-show="searchWorkshops" class="bg-gray-50 rounded-3xl border border-gray-200 p-4 sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <h2 class="text-2xl font-bold my-1">Workshops</h2>
                        <div class="text-sm text-gray-500">
                            {{ number_format((int) $workshops->total()) }} {{ (int) $workshops->total() === 1 ? 'result' : 'results' }}
                        </div>
                    </div>
                    @if(!$workshops->isEmpty())
                        <x-container data-list-results class="mt-4" inner-class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 w-full">
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
        @endif

        </div>
        </x-ui.dynamic-list>
    </x-container>
</x-layout>
