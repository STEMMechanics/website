@props(['products' => true, 'workshops' => true, 'autoSubmit' => false])
<fieldset class="mt-3" x-on:change="searchScopeError = !searchProducts && !searchWorkshops; @if($autoSubmit) if (!searchScopeError) $nextTick(() => $refs.searchForm.requestSubmit()); @endif">
    <input type="hidden" name="include_products" value="{{ $products ? 1 : 0 }}" x-bind:value="searchProducts ? 1 : 0">
    <input type="hidden" name="include_workshops" value="{{ $workshops ? 1 : 0 }}" x-bind:value="searchWorkshops ? 1 : 0">
    <div class="flex flex-wrap gap-x-6 gap-y-3 items-center">
        <x-ui.checkbox :id="'search-products-'.Str::uuid()" label="Store" :checked="$products" x-model="searchProducts" noWrapper inline />
        <x-ui.checkbox :id="'search-workshops-'.Str::uuid()" label="Workshops" :checked="$workshops" x-model="searchWorkshops" noWrapper inline />
        <p role="alert" class="text-sm text-red-700" x-show="searchScopeError" x-cloak>Select at least one: Store or Workshops.</p>
    </div>
</fieldset>
