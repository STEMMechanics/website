@props(['tableClass' => '', 'caption' => null, 'variant' => 'default'])

<div {{ $attributes->class(['min-w-0 w-full max-w-full overflow-x-auto', 'rounded-xl border border-slate-200 bg-white' => $variant === 'listing']) }}>
    <table class="{{ twMerge($variant === 'listing' ? 'sm-data-table' : ($variant === 'plain' ? 'w-full' : 'table'), $tableClass) }}">
        @if($caption)<caption class="sr-only">{{ $caption }}</caption>@endif
        @if(isset($header))
        <thead>
            <tr>
                {{ $header }}
            </tr>
        </thead>
        <tbody>
            {{ $body }}
        </tbody>
        @else
            {{ $slot }}
        @endif
    </table>
</div>
