@props([
    'type' => 'text',
    'name',
    'label',
    'action' => null,
    'value' => old($name),
    'advancedFields' => [],
    'advancedOpen' => false,
    'advancedExternal' => false,
    'advancedActive' => false,
])

@php
    $classes = 'bg-white grow px-2.5 py-2.5 text-sm text-gray-900 bg-transparent rounded-l-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 peer border-gray-300 focus:border-indigo-300 focus:ring-indigo-300';
    $currentValue = (string) request()->query($name, (string) ($value ?? ''));
    $queryParams = $action === null || $action === url()->current() ? request()->query() : [];
    $excludedQueryParams = [$name, 'page', ...(isset($advanced) ? $advancedFields : [])];
    foreach ($excludedQueryParams as $excludedQueryParam) {
        unset($queryParams[$excludedQueryParam]);
    }
    $hasAdvancedSearch = isset($advanced) || (bool) $advancedExternal;
@endphp

@if(app(\App\Services\SiteListControls::class)->definition() && !$hasAdvancedSearch && ($action === null || $action === url()->current()))
    <x-ui.collection-controls :search-name="$name" :label="$label" :value="$currentValue" :action="$action" {{ $attributes }} />
@else
<form
    method="GET"
    action="{{ $action ?? url()->current() }}"
    class="{{ $attributes->get('class') }}"
    x-data="{ search: {{ \Illuminate\Support\Js::from($currentValue) }}, advancedOpen: {{ \Illuminate\Support\Js::from((bool) $advancedOpen) }}, advancedActive: {{ \Illuminate\Support\Js::from((bool) $advancedActive) }} }"
>
    <div class="flex relative">
        @foreach($queryParams as $queryKey => $queryValue)
            @if(is_array($queryValue))
                @foreach($queryValue as $arrayValue)
                    <input type="hidden" name="{{ $queryKey }}[]" value="{{ $arrayValue }}" @if(in_array($queryKey, $advancedFields, true)) data-advanced-search-param @endif>
                @endforeach
            @elseif($queryValue !== null && $queryValue !== '')
                <input type="hidden" name="{{ $queryKey }}" value="{{ $queryValue }}" @if(in_array($queryKey, $advancedFields, true)) data-advanced-search-param @endif>
            @endif
        @endforeach
        <x-ui.input-control class="{{ twMerge($classes, 'rounded-r-none') }}" aria-label="{{ $label }}" autocomplete="off" x-bind:placeholder="advancedActive && !search ? 'Advanced Search' : {{ \Illuminate\Support\Js::from($label) }}" x-model="search" type="{{ $type }}" name="{{ $name }}" />
        @if($hasAdvancedSearch)
            <x-ui.button
                type="button"
                color="outline"
                class="rounded-none px-4 border-gray-300! border-x-0"
                x-on:click="{{ $advancedExternal ? '$dispatch(\'toggle-advanced-search\')' : 'advancedOpen = !advancedOpen' }}"
                title="Advanced search"
            >
                <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                <span class="sr-only">Advanced search</span>
            </x-ui.button>
        @endif
        <x-ui.button type="submit" class="rounded-l-none px-6" aria-label="Search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></x-ui.button>
        <x-ui.button variant="plain" size="icon-small" aria-label="Clear search"
            x-show="search || advancedActive"
            x-cloak
            class="absolute z-10 top-1/2 {{ $hasAdvancedSearch ? 'right-31' : 'right-18' }} transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
            x-on:click="
                search = '';
                if (advancedActive) {
                    $el.closest('form').querySelectorAll('[data-advanced-search-param]').forEach((input) => input.remove());
                    advancedActive = false;
                    $dispatch('clear-advanced-search');
                    $nextTick(() => $el.closest('form').requestSubmit());
                } else if ({{ \Illuminate\Support\Js::from($currentValue !== '') }}) {
                    $nextTick(() => $el.closest('form').requestSubmit());
                }
            "
        ><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i></x-ui.button>
    </div>
    @if(isset($advanced))
        <div x-show="advancedOpen" x-cloak class="mt-2 rounded-lg border border-gray-200 bg-gray-50 p-4 shadow-sm">
            {{ $advanced }}
        </div>
    @endif
</form>

@endif
