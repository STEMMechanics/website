@props(['type' => 'text', 'name', 'label', 'value' => old($name)])

@php
    $classes = 'bg-white grow px-2.5 py-2.5 text-sm text-gray-900 bg-transparent rounded-l-lg border border-gray-300 appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 peer border-gray-300 focus:border-indigo-300 focus:ring-indigo-300';
    $currentValue = (string) request()->query($name, (string) ($value ?? ''));
    $queryParams = request()->query();
    unset($queryParams[$name], $queryParams['page']);
@endphp

<form method="GET" action="{{ url()->current() }}" class="{{ $attributes->get('class') }}">
    <div class="flex relative" x-data="{ search: {{ \Illuminate\Support\Js::from($currentValue) }} }">
        @foreach($queryParams as $queryKey => $queryValue)
            @if(is_array($queryValue))
                @foreach($queryValue as $arrayValue)
                    <input type="hidden" name="{{ $queryKey }}[]" value="{{ $arrayValue }}">
                @endforeach
            @elseif($queryValue !== null && $queryValue !== '')
                <input type="hidden" name="{{ $queryKey }}" value="{{ $queryValue }}">
            @endif
        @endforeach
        <input class="{{ $classes }}" autocomplete="off" placeholder="{{ $label }}" x-model="search" type="{{ $type }}" name="{{ $name }}" />
        <x-ui.button type="submit" class="rounded-l-none px-6"><i class="fa-solid fa-magnifying-glass"></i></x-ui.button>
        <i x-show="search" cloak class="absolute z-10 top-1/2 right-[4.5rem] transform -translate-y-1/2 text-gray-300 hover:text-gray-400 cursor-pointer fa-solid fa-circle-xmark" x-data x-on:click="search='';$nextTick(()=>{if({{ \Illuminate\Support\Js::from($currentValue !== '') }}){$el.closest('form').submit();}})"></i>
    </div>
</form>
