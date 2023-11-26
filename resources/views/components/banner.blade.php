@props(['heading', 'back'])

@php
    $backTitle = '';
    if (isset($back)) {
        $parts = explode('.', $back);
        if (count($parts) > 1) {
            $backTitle = ucwords($parts[count($parts) - 2]);
        } else {
            $backTitle = ucwords($parts[0]);
        }
    }
@endphp

<div class="px42 -mt-4 mb-8 bg-blue px-6 py-10 text-white">
    <h1 class="text-4xl font-bold">{{ $heading }}</h1>
    @if (isset($back))
        <a href="{{ route($back) }}" class="mt-1 text-sm hover:text-inherit hover:underline"><i
                class="fa-solid fa-chevron-left mr-2"></i>Back
            to
            {{ $backTitle }}</a>
    @endif
</div>
