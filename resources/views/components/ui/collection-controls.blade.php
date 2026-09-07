@props(['searchName' => 'search', 'label' => 'Search', 'value' => '', 'action' => null, 'scope' => null])
@php
    $controls = $scope ? new \App\Services\SiteListControls($scope) : app(\App\Services\SiteListControls::class);
    if ($scope) $searchName = $controls->searchParameter();
    $sortName = $controls->parameter('sort');
    $directionName = $controls->parameter('direction');
    $pageReset = $controls->paginationReset();
    $fields = $controls->filterFields();
    $id = 'collection-'.str_replace('.', '-', request()->route()->getName()).($scope ? '-'.$scope : '').'-'.Str::uuid();
    $active = collect(request()->only(array_keys($fields)))->filter(fn ($value, $key) => (is_array($value) ? count($value) > 0 : (is_scalar($value) && (string) $value !== '')) && (!array_key_exists('default', $fields[$key]) || (string) $value !== (string) $fields[$key]['default']));
@endphp
<div data-filter-controls data-filter-schema="{{ json_encode($fields) }}" data-filter-search="{{ $searchName }}" {{ $attributes->class(['min-w-0 w-full my-5 first:mt-0']) }}>
    <div class="flex flex-wrap items-center gap-3">
        <form method="GET" action="{{ $action ?? url()->current() }}" class="flex min-w-0 flex-1 basis-full items-center gap-3 lg:basis-auto">
            <x-ui.query-inputs :values="request()->except([$searchName, ...array_keys($pageReset)])" />
            <div class="relative min-w-0 flex-1"><i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-3.5 text-slate-400" aria-hidden="true"></i><x-ui.input-control type="search" :name="$searchName" :value="request($searchName, $value)" :placeholder="$label" :aria-label="$label" class="pl-10! min-h-11" /></div>
            <button type="submit" class="sr-only">Search</button>
        </form>
        <x-ui.button color="outline" class="h-11 rounded-lg border-gray-300! shadow-none px-4" data-open-dialog="{{ $id }}-filters" aria-haspopup="dialog"><i class="fa-solid fa-filter mr-2" aria-hidden="true"></i>Filters <x-ui.badge data-filter-count class="ml-2" color="sky" :hidden="$active->isEmpty()">{{ $active->count() }}</x-ui.badge></x-ui.button>
        <x-ui.button color="outline" class="h-11 rounded-lg border-gray-300! shadow-none px-4" data-open-dialog="{{ $id }}-sort" aria-haspopup="dialog"><i class="fa-solid fa-arrow-down-short-wide mr-2" aria-hidden="true"></i>Sort</x-ui.button>
    </div>
        <div data-filter-chips @if($active->isEmpty()) hidden @endif class="mt-3 flex flex-wrap items-center gap-2">
            @foreach($active as $key => $filterValue)
                <x-ui.filter-chip :label="$fields[$key]['label'].': '.(is_array($filterValue) ? (isset($fields[$key]['options']) ? collect($filterValue)->map(fn ($option) => $fields[$key]['options'][$option] ?? $option)->join(', ') : count($filterValue).' selected') : ($fields[$key]['options'][$filterValue] ?? ($fields[$key]['type'] === 'boolean' ? ($filterValue ? 'Yes' : 'No') : $filterValue)))" :url="request()->fullUrlWithQuery([$key => $fields[$key]['clear'] ?? null, ...$pageReset])" />
            @endforeach
            <a data-dynamic-link class="text-sm text-primary-color underline" href="{{ request()->fullUrlWithQuery(array_merge(collect($fields)->map(fn ($field) => $field['clear'] ?? null)->all(), [...$pageReset])) }}">Clear filters</a>
        </div>
    <x-ui.list-dialog :id="$id.'-filters'" title="Filter results">
        @if(isset($filterForm))
            {{ $filterForm }}
        @else
        <form method="GET" action="{{ $action ?? url()->current() }}">
            <x-ui.query-inputs :values="request()->except([...array_keys($fields), ...array_keys($pageReset)])" />
            <div class="sm-filter-sections">
                @foreach(collect($fields)->groupBy(fn ($field) => in_array($field['type'], ['date', 'number']) ? ($field['type'] === 'date' ? 'Dates' : 'Amounts') : 'General', preserveKeys: true) as $section => $sectionFields)
                    <details @if($loop->first) open @endif>
                        <summary><i class="fa-solid {{ $section === 'Dates' ? 'fa-calendar' : ($section === 'Amounts' ? 'fa-hashtag' : 'fa-sliders') }}" aria-hidden="true"></i><span>{{ $section }}</span></summary>
                        <div class="grid gap-4">
                            @foreach($sectionFields as $key => $field)
                                @if($field['type'] === 'array' && isset($field['options']))
                                    <fieldset class="space-y-2">
                                        <legend class="mb-2 text-sm font-medium text-gray-700">{{ $field['label'] }}</legend>
                                        @foreach($field['options'] as $option => $optionLabel)
                                            <x-ui.checkbox :name="$key.'[]'" :value="$option" :label="$optionLabel" :checked="in_array((string) $option, (array) request($key, []), true)" noWrapper inline />
                                        @endforeach
                                    </fieldset>
                                @elseif($field['type'] === 'select')
                                    <x-ui.select :name="$key" :label="$field['label']" :aria-label="$field['label']" :data-clear-value="$field['clear'] ?? ''" class="mb-0"><option value="">Any</option>@foreach($field['options'] as $option => $optionLabel)<option value="{{ $option }}" @selected(request($key) === (string) $option)>{{ $optionLabel }}</option>@endforeach</x-ui.select>
                                @elseif($field['type'] === 'boolean')
                                    <x-ui.select :name="$key" :label="$field['label']" :aria-label="$field['label']" :data-clear-value="$field['clear'] ?? ''" class="mb-0"><option value="">Any</option><option value="1" @selected(request($key) === '1')>Yes</option><option value="0" @selected(request($key) === '0')>No</option></x-ui.select>
                                @else
                                    <x-ui.input :type="$field['type'] === 'number' ? 'number' : ($field['type'] === 'date' ? 'date' : 'text')" :name="$key" :label="$field['label']" :aria-label="$field['label']" :value="request($key)" step="any" class="mb-0" />
                                @endif
                            @endforeach
                            @if($section === 'General')<p class="text-sm text-slate-500">Text matches part of a value. Use * for any characters or ? for one character. All filters must match.</p>@endif
                        </div>
                    </details>
                @endforeach
            </div>
            <div class="sm-dialog-footer"><x-ui.button color="outline" data-clear-filter-fields>Clear all</x-ui.button><x-ui.button type="submit">Apply filters</x-ui.button></div>
        </form>
        @endif
    </x-ui.list-dialog>
    <x-ui.list-dialog :id="$id.'-sort'" title="Sort results" kind="sort">
        <form method="GET" action="{{ $action ?? url()->current() }}">
            <x-ui.query-inputs :values="request()->except([$sortName, $directionName, ...array_keys($pageReset)])" />
            <div class="grid gap-4 p-5">
                <x-ui.select :name="$sortName" label="Sort by" aria-label="Sort by" class="mb-0"><option value="">Default order</option>@foreach($controls->fields() as $key => $field)<option value="{{ $key }}" @selected(request($sortName) === $key)>{{ $field['label'] }}</option>@endforeach</x-ui.select>
                <x-ui.select :name="$directionName" label="Order" aria-label="Order" class="mb-0"><option value="asc" @selected(request($directionName, 'asc') === 'asc')>Ascending</option><option value="desc" @selected(request($directionName) === 'desc')>Descending</option></x-ui.select>
            </div>
            <div class="sm-dialog-footer"><x-ui.button color="outline" data-close-dialog>Cancel</x-ui.button><x-ui.button type="submit">Apply</x-ui.button></div>
        </form>
    </x-ui.list-dialog>
</div>
