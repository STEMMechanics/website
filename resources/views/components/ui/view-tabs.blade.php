@props(['items' => [], 'label' => 'Views'])
<nav data-view-tabs aria-label="{{ $label }}" {{ $attributes->class(['sm-view-tabs min-w-0 w-full flex-1 max-w-full overflow-x-auto']) }}>
    <div class="flex w-max min-w-full items-stretch gap-1">
        @foreach($items as $item)
            <a href="{{ $item['route'] }}"
               aria-label="{{ $item['label'] ?? $item['title'] }}"
               @if($item['active'] ?? false) aria-current="page" @endif
               class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-t-xl border-2 px-4 py-2 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-color {{ ($item['active'] ?? false) ? 'border-slate-300 border-b-white bg-white text-primary-color' : 'border-transparent border-b-slate-300 text-slate-600 hover:bg-slate-100 hover:text-primary-color' }}">
                @if(!empty($item['icon']))<i class="{{ $item['icon'] }}" aria-hidden="true"></i>@endif
                {{ $item['title'] }}
            </a>
        @endforeach
    </div>
</nav>
