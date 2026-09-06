@props(['items', 'label' => 'Preset views'])
<nav data-view-tabs aria-label="{{ $label }}" {{ $attributes->class(['sm-preset-views']) }}>
    @foreach($items as $item)
        <a href="{{ $item['route'] }}" aria-label="{{ $item['title'] }}" @if($item['active']) aria-current="page" @endif class="sm-preset-view">
            {{ $item['title'] }} @if(isset($item['count']))<x-ui.badge color="slate">{{ number_format($item['count']) }}</x-ui.badge>@endif
        </a>
    @endforeach
</nav>
