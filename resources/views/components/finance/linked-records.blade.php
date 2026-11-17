@props(['title', 'records', 'emptyMessage', 'helpText' => null])
@php($records = collect($records))

<details {{ $attributes->class(['sm-disclosure', 'sm-linked-records', 'overflow-hidden rounded-lg border border-gray-200 bg-white']) }} @if($records->isNotEmpty()) open @endif>
    <summary class="flex min-h-11 cursor-pointer items-center justify-between gap-3 bg-gray-50 px-3 py-2 transition hover:bg-gray-100">
        <span class="min-w-0 text-sm font-semibold text-gray-900">{{ $title }}</span>
        <span class="ml-auto shrink-0 rounded-full bg-white px-2 py-0.5 text-xs font-medium text-gray-600">{{ $records->count() }} linked</span>
        <i class="fa-solid fa-chevron-down shrink-0 text-xs text-gray-500" aria-hidden="true"></i>
    </summary>
    <div class="border-t border-gray-200 bg-white">
        @if($records->isEmpty())
            <p class="px-3 py-3 text-sm text-gray-600">{{ $emptyMessage }}</p>
        @else
            <div class="divide-y divide-gray-100">{{ $slot }}</div>
        @endif
        @if($helpText)
            <p class="border-t border-gray-100 px-3 py-2 text-xs text-gray-500">{{ $helpText }}</p>
        @endif
    </div>
</details>
