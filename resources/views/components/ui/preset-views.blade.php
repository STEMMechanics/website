@props(['items', 'label' => 'Preset views'])
<div {{ $attributes->class(['sm-preset-controls']) }} x-data="{
    overflow: false, atStart: true, atEnd: false,
    refresh() { const nav = this.$refs.views; this.overflow = nav.scrollWidth > nav.clientWidth + 2; this.atStart = nav.scrollLeft < 2; this.atEnd = nav.scrollLeft + nav.clientWidth >= nav.scrollWidth - 2; },
    move(direction) { this.$refs.views.scrollBy({ left: direction * this.$refs.views.clientWidth * .8, behavior: 'smooth' }); },
}" x-init="$nextTick(() => refresh())" x-on:resize.window.debounce.150ms="refresh()">
<x-ui.button size="icon" color="outline" class="sm:hidden" x-show="overflow" x-cloak x-bind:disabled="atStart" x-on:click="move(-1)" aria-label="Show previous views"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></x-ui.button>
<nav data-view-tabs x-ref="views" x-on:scroll.passive="refresh()" aria-label="{{ $label }}" class="sm-preset-views min-w-0 flex-1">
    @foreach($items as $item)
        @php
            $count = isset($item['count']) ? (int) $item['count'] : null;
            $isAttentionTab = (bool) ($item['attention'] ?? false);
            $needsAttention = $isAttentionTab && $count === null;
        @endphp
        <a href="{{ $item['route'] }}" aria-label="{{ $item['title'] }}{{ $count !== null ? ', '.number_format($count) : '' }}{{ $isAttentionTab && ($count === null || $count > 0) ? ', needs attention' : '' }}" @if($item['active']) aria-current="page" @endif class="sm-preset-view">
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
<x-ui.button size="icon" color="outline" class="sm:hidden" x-show="overflow" x-cloak x-bind:disabled="atEnd" x-on:click="move(1)" aria-label="Show more views"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></x-ui.button>
</div>
