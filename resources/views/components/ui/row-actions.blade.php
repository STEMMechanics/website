@props(['title' => 'Item actions', 'menu' => true])
<div {{ $attributes->class(['sm-row-actions flex flex-nowrap items-center justify-center gap-2']) }}>
    @if($menu && app(\App\Services\SiteListControls::class)->definition() && preg_match_all('/<(?:button|a)\b/i', (string) $slot) > 1)
        <x-ui.action-menu :id="'row-actions-'.\Illuminate\Support\Str::uuid()" :title="$title">{{ $slot }}</x-ui.action-menu>
    @else
        {{ $slot }}
    @endif
</div>
