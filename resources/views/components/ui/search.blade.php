@props(['type' => 'text', 'name', 'label', 'value' => old($name)])

@php
    $classes = 'bg-white flex-grow px-2.5 py-2.5 text-sm text-gray-900 bg-transparent rounded-l-lg border border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer border-gray-300 focus:border-indigo-300 focus:ring-indigo-300';
@endphp

<form method="GET" action="{{ url()->current() }}" class="{{ $attributes->get('class') }}">
    <div class="flex">
        <input class="{{ $classes }}" autocomplete="off" placeholder="{{ $label }}" value="{{ request()->get('search') }}" type="{{ $type }}" name="{{ $name }}" />
        <x-ui.button type="submit" class="rounded-l-none px-6"><i class="fa-solid fa-magnifying-glass"></i></x-ui.button>
    </div>
</form>
