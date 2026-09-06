@props(['hint' => null, 'total' => null])
<div {{ $attributes->class(['sm-selection-toolbar sm-list-footer-selection flex flex-wrap items-center gap-3']) }} data-selection-toolbar data-selected="false" aria-live="polite">
    <span class="whitespace-nowrap"><strong>{{ $count }}</strong> selected</span>
    {{ $clear }}
    <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
</div>
