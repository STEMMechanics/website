@props(['class' => ''])

<div class="{{ twMerge('sm-ui-toolbar my-4 flex flex-col sm:flex-row items-center gap-3 sm:gap-4', $class) }}">
    @if(isset($left))
    <div class="{{ twMerge(['sm-ui-toolbar-left','w-full','flex','flex-1','justify-start','items-center','gap-3'], $left->attributes->get('class')) }}">
        {{ $left ?? '' }}
    </div>
    @endif
    @if(isset($right))
    <div class="{{ twMerge(['sm-ui-toolbar-right','w-full','flex','flex-col','flex-1','justify-end','sm:flex-row','sm:items-center','gap-3'], $right->attributes->get('class')) }}">
        {{ $right ?? '' }}
    </div>
    @endif
</div>
