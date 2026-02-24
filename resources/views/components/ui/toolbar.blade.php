@props(['class' => ''])

<div class="{{ twMerge('sm-ui-toolbar my-4 flex flex-col sm:flex-row items-center gap-3 sm:gap-4', $class) }}">
    @if(isset($left))
    <div class="sm-ui-toolbar-left w-full flex flex-1 justify-start items-center gap-3">
        {{ $left ?? '' }}
    </div>
    @endif
    @if(isset($right))
    <div class="sm-ui-toolbar-right w-full flex flex-col flex-1 justify-end gap-3 sm:flex-row sm:items-center">
        {{ $right ?? '' }}
    </div>
    @endif
</div>
