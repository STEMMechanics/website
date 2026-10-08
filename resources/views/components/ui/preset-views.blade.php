@props(['items', 'label' => 'Preset views'])
<nav data-view-tabs aria-label="{{ $label }}" {{ $attributes->class(['sm-preset-views']) }}>
    @foreach($items as $item)
        @php
            $count = isset($item['count']) ? (int) $item['count'] : null;
            $isAttentionTab = (bool) ($item['attention'] ?? false);
            $needsAttention = $isAttentionTab && $count === null;
        @endphp
        <a href="{{ $item['route'] }}" aria-label="{{ $item['title'] }}{{ $needsAttention ? ', needs attention' : '' }}" @if($item['active']) aria-current="page" @endif class="sm-preset-view">
            {{ $item['title'] }}
            @if($needsAttention)
                <span class="ml-1.5 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full border border-amber-200 bg-amber-50 text-amber-800" title="Needs attention" aria-hidden="true">
                    <i class="fa-solid fa-exclamation text-[10px] leading-none"></i>
                </span>
            @endif
            @if($count !== null && $count > 0)<x-ui.badge :color="$isAttentionTab ? 'warning' : 'slate'">{{ number_format($count) }}</x-ui.badge>@endif
        </a>
    @endforeach
</nav>
